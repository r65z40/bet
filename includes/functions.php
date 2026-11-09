<?php
/** Helpers : échappement, contenus, CSRF, authentification, statistiques, uploads. */

function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Texte échappé + **gras** + retours à la ligne. */
function rich(?string $s): string
{
    $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', e($s));
    return nl2br($html, false);
}

/* ---------- Contenus ---------- */

function settings(bool $reload = false): array
{
    static $cache = null;
    if ($cache === null || $reload) {
        $cache = [];
        foreach (db()->query('SELECT key, value FROM settings') as $row) {
            $cache[$row['key']] = $row['value'];
        }
    }
    return $cache;
}

function s(string $key): string
{
    return settings()[$key] ?? '';
}

function save_setting(string $key, string $value): void
{
    db()->prepare('INSERT INTO settings(key, value) VALUES(?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value')
        ->execute([$key, $value]);
}

/** Éléments visibles d'une liste, dans l'ordre. */
function items(string $grp, bool $all = false): array
{
    $sql = 'SELECT * FROM items WHERE grp = ?' . ($all ? '' : ' AND visible = 1') . ' ORDER BY position, id';
    $st = db()->prepare($sql);
    $st->execute([$grp]);
    return $st->fetchAll();
}

function photos(bool $all = false): array
{
    return db()->query('SELECT * FROM photos' . ($all ? '' : ' WHERE visible = 1') . ' ORDER BY position, id')->fetchAll();
}

/**
 * URL d'une photo avec un numéro de version (date du fichier) : si un cache
 * (navigateur, CDN, hébergeur) a gardé une ancienne réponse en erreur, la
 * nouvelle URL l'ignore automatiquement après chaque remplacement du fichier.
 */
function photo_src(string $filename): string
{
    $v = @filemtime(UPLOAD_DIR . '/' . $filename);
    return 'photos/' . rawurlencode($filename) . ($v ? '?v=' . $v : '');
}

function hero_image(): string
{
    $f = s('hero_photo');
    return photo_src($f !== '' && is_file(UPLOAD_DIR . '/' . $f) ? $f : 'photo-02.jpg');
}

function plan_image(): string
{
    $f = s('plan_image');
    return $f !== '' && is_file(UPLOAD_DIR . '/' . $f) ? photo_src($f) : 'plan-salle-rn6.jpg';
}

function mailto(): string
{
    return 'mailto:' . s('contact_email') . '?subject=' . rawurlencode(s('mail_subject')) . '&body=' . rawurlencode(str_replace("\r", '', s('mail_body')));
}

/* ---------- CSRF ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function check_csrf(): void
{
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(400);
        exit('Jeton de sécurité invalide. Rechargez la page et réessayez.');
    }
}

/* ---------- Messages ---------- */

function flash(string $msg, string $type = 'ok'): void
{
    $_SESSION['flash'][] = [$type, $msg];
}

function render_flash(): string
{
    $out = '';
    foreach ($_SESSION['flash'] ?? [] as [$type, $msg]) {
        $out .= '<div class="flash flash-' . e($type) . '">' . e($msg) . '</div>';
    }
    unset($_SESSION['flash']);
    return $out;
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/* ---------- Authentification admin ---------- */

function admin_count(): int
{
    return (int)db()->query('SELECT COUNT(*) FROM admins')->fetchColumn();
}

function current_admin(): ?array
{
    if (empty($_SESSION['admin_id'])) {
        return null;
    }
    $st = db()->prepare('SELECT id, username FROM admins WHERE id = ?');
    $st->execute([$_SESSION['admin_id']]);
    return $st->fetch() ?: null;
}

function require_admin(): array
{
    $admin = current_admin();
    if (!$admin) {
        redirect(admin_count() === 0 ? 'setup.php' : 'login.php');
    }
    return $admin;
}

function login_admin(int $id): void
{
    session_regenerate_id(true);
    $_SESSION['admin_id'] = $id;
}

/** Limite : 5 essais ratés par IP sur 15 minutes. */
function login_blocked(): bool
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    db()->prepare('DELETE FROM login_attempts WHERE at < ?')->execute([time() - 900]);
    $st = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ?');
    $st->execute([$ip]);
    return (int)$st->fetchColumn() >= 5;
}

function login_failed(): void
{
    db()->prepare('INSERT INTO login_attempts(ip, at) VALUES(?, ?)')->execute([$_SERVER['REMOTE_ADDR'] ?? '', time()]);
}

/* ---------- Statistiques ---------- */

function is_bot(string $ua): bool
{
    return $ua === '' || (bool)preg_match('/bot|crawl|spider|slurp|preview|monitor|curl|wget|headless|lighthouse/i', $ua);
}

/**
 * Identifiant anonyme du visiteur : hash salé de l'IP + navigateur qui change
 * chaque jour. L'IP n'est jamais stockée, aucun cookie n'est déposé.
 */
function visitor_id(): string
{
    $salt = s('_stats_salt');
    if ($salt === '') {
        $salt = bin2hex(random_bytes(16));
        save_setting('_stats_salt', $salt);
        settings(true);
    }
    return substr(hash('sha256', $salt . date('Y-m-d') . ($_SERVER['REMOTE_ADDR'] ?? '') . ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 16);
}

function track_visit(string $page): void
{
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (is_bot($ua) || !empty($_SESSION['admin_id'])) {
        return; // robots et administrateur non comptés
    }
    $ref = (string)parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_HOST);
    if ($ref === (string)parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)) {
        $ref = ''; // navigation interne
    }
    $device = preg_match('/tablet|ipad/i', $ua) ? 'tablette'
            : (preg_match('/mobi|android|iphone/i', $ua) ? 'mobile' : 'ordinateur');
    try {
        db()->prepare('INSERT INTO visits(day, visited_at, page, visitor, referrer, device) VALUES(?,?,?,?,?,?)')
            ->execute([date('Y-m-d'), date('Y-m-d H:i:s'), $page, visitor_id(), preg_replace('/^(www|m|l|lm)\./', '', strtolower($ref)), $device]);
    } catch (Throwable) {
        // les statistiques ne doivent jamais empêcher l'affichage du site
    }
}

/** Script de suivi des clics (à inclure en bas des pages publiques). */
function tracking_script(string $page): string
{
    if (!empty($_SESSION['admin_id'])) {
        return '';
    }
    $p = json_encode($page);
    return <<<JS
<script>
document.addEventListener('click', function (e) {
  var el = e.target.closest('[data-track]');
  if (!el || !navigator.sendBeacon) return;
  var d = new FormData(); d.append('k', el.dataset.track); d.append('p', $p);
  navigator.sendBeacon('track.php', d);
}, true);
</script>
JS;
}

/* ---------- Upload d'images ---------- */

/** Vérifie et enregistre une image envoyée ; retourne [nom, largeur, hauteur]. */
function store_uploaded_image(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $msg = match ($file['error'] ?? -1) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'fichier trop lourd pour le serveur',
            UPLOAD_ERR_NO_FILE => 'aucun fichier',
            default => 'erreur ' . (int)($file['error'] ?? -1),
        };
        throw new RuntimeException(($file['name'] ?? 'Fichier') . ' : ' . $msg . '.');
    }
    if ($file['size'] > MAX_UPLOAD_BYTES) {
        throw new RuntimeException($file['name'] . ' : fichier trop lourd (8 Mo maximum).');
    }
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $size = @getimagesize($file['tmp_name']);
    if (!isset($allowed[$mime]) || $size === false) {
        throw new RuntimeException($file['name'] . ' : format non accepté (JPG, PNG ou WebP).');
    }
    $name = 'upload-' . date('Ymd') . '-' . bin2hex(random_bytes(5)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . '/' . $name)) {
        throw new RuntimeException('Impossible d’enregistrer le fichier (vérifiez les droits du dossier photos/).');
    }
    [$w, $h] = optimize_image(UPLOAD_DIR . '/' . $name);
    return [$name, $w, $h];
}

/** Supprime un fichier du dossier photos/ en toute sécurité. */
function delete_photo_file(string $filename): void
{
    $path = UPLOAD_DIR . '/' . basename($filename);
    if ($filename !== '' && is_file($path)) {
        @unlink($path);
    }
}

/** Réorganise les $_FILES multiples en liste de fichiers. */
function normalize_files(array $f): array
{
    if (!is_array($f['name'])) {
        return [$f];
    }
    $out = [];
    foreach ($f['name'] as $i => $n) {
        $out[] = ['name' => $n, 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
    }
    return $out;
}

/** Taille max d'envoi réellement acceptée par le serveur (php.ini). */
function max_upload_bytes(): int
{
    $toBytes = function (string $v): int {
        $n = (int)$v;
        return match (strtolower(substr(trim($v), -1))) {
            'g' => $n * 1073741824, 'm' => $n * 1048576, 'k' => $n * 1024, default => $n,
        };
    };
    $limits = array_filter([MAX_UPLOAD_BYTES, $toBytes((string)ini_get('upload_max_filesize')), $toBytes((string)ini_get('post_max_size'))]);
    return min($limits);
}

/**
 * Réduit une image trop grande (max 2400 px) et la recompresse, en tenant
 * compte de l'orientation des photos de téléphone. Retourne [largeur, hauteur].
 * Sans l'extension GD, l'image est laissée telle quelle.
 */
function optimize_image(string $path, int $max = 2400): array
{
    $info = @getimagesize($path);
    if (!$info) {
        return [1200, 900];
    }
    [$w, $h, $type] = $info;
    if (!function_exists('imagecreatetruecolor')) {
        return [$w, $h];
    }
    $img = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
        IMAGETYPE_PNG  => @imagecreatefrompng($path),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
        default => false,
    };
    if (!$img) {
        return [$w, $h];
    }
    $rotated = false;
    if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $o = (int)(@exif_read_data($path)['Orientation'] ?? 1);
        $angle = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
        if ($angle) {
            $img = imagerotate($img, $angle, 0);
            $rotated = true;
        }
    }
    $w = imagesx($img);
    $h = imagesy($img);
    $ratio = min(1, $max / max($w, $h));
    if ($ratio < 1 || $rotated || ($type === IMAGETYPE_JPEG && filesize($path) > 600 * 1024)) {
        $nw = (int)round($w * $ratio);
        $nh = (int)round($h * $ratio);
        $dst = imagecreatetruecolor($nw, $nh);
        if ($type !== IMAGETYPE_JPEG) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }
        imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        match ($type) {
            IMAGETYPE_PNG  => imagepng($dst, $path, 8),
            IMAGETYPE_WEBP => imagewebp($dst, $path, 82),
            default        => (imageinterlace($dst, true) && imagejpeg($dst, $path, 82)),
        };
        [$w, $h] = [$nw, $nh];
    }
    return [$w, $h];
}
