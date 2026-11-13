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

function hero_filename(): string
{
    $f = s('hero_photo');
    return $f !== '' && is_file(UPLOAD_DIR . '/' . $f) ? $f : 'photo-02.jpg';
}

function hero_image(): string
{
    return photo_src(hero_filename());
}

function plan_image(): string
{
    $f = s('plan_image');
    return $f !== '' && is_file(UPLOAD_DIR . '/' . $f) ? photo_src($f) : 'plan-salle-rn6.jpg';
}

/** Version légère du plan pour les petits écrans (WebP ~500-800 px), ou null. */
function plan_image_small(): ?string
{
    $f = s('plan_image');
    if ($f !== '' && is_file(UPLOAD_DIR . '/' . $f)) {
        make_variants($f);
        return variant_src($f, 'thumb');
    }
    return is_file(ROOT . '/plan-salle-rn6-500.webp') ? 'plan-salle-rn6-500.webp' : null;
}

/** Dimensions [largeur, hauteur] d'une image locale (évite le décalage de mise en page), ou null. */
function image_size(string $src): ?array
{
    $path = ROOT . '/' . rawurldecode(strtok($src, '?'));
    $i = is_file($path) ? @getimagesize($path) : false;
    return $i ? [(int)$i[0], (int)$i[1]] : null;
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

/** Limite : 5 essais ratés par IP sur 15 minutes. */
function login_blocked(): bool
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    db()->prepare('DELETE FROM login_attempts WHERE at < ?')->execute([time() - 900]);
    $st = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ?');
    $st->execute([$ip]);
    return (int)$st->fetchColumn() >= 5;
}

/** Limite par compte : 10 essais ratés en 15 minutes, quelle que soit l'adresse IP. */
function account_blocked(string $key): bool
{
    $st = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND at > ?');
    $st->execute([$key, time() - 900]);
    return (int)$st->fetchColumn() >= 10;
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

/** Empreinte anonyme de l'adresse IP seule (sel quotidien), pour les limites anti-abus. */
function ip_fingerprint(): string
{
    visitor_id(); // garantit l'existence du sel
    return 'ip' . substr(hash('sha256', s('_stats_salt') . date('Y-m-d') . ($_SERVER['REMOTE_ADDR'] ?? '')), 0, 14);
}

function track_visit(string $page): void
{
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (is_bot($ua) || !empty($_SESSION['admin_id'])) {
        return; // robots et administrateur non comptés
    }
    $ref = (string)parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_HOST);
    $ref = substr((string)preg_replace('/[^a-z0-9.\-]/', '', strtolower($ref)), 0, 100); // un nom de domaine, rien d'autre
    if ($ref === (string)parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)) {
        $ref = ''; // navigation interne
    }
    $device = preg_match('/tablet|ipad/i', $ua) ? 'tablette'
            : (preg_match('/mobi|android|iphone/i', $ua) ? 'mobile' : 'ordinateur');
    try {
        if (stats_quota_reached('visits', 30, 20000)) {
            return; // protection contre le gonflage artificiel des statistiques
        }
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
    $p = json_encode($page, JSON_HEX_TAG);
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
    if ($size[0] * $size[1] > 50_000_000) {
        throw new RuntimeException($file['name'] . ' : image trop grande (50 mégapixels maximum).');
    }
    $name = 'upload-' . date('Ymd') . '-' . bin2hex(random_bytes(5)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . '/' . $name)) {
        throw new RuntimeException('Impossible d’enregistrer le fichier (vérifiez les droits du dossier photos/).');
    }
    // Réencodage obligatoire : supprime les métadonnées (GPS, appareil…) et tout
    // contenu caché dans le fichier ; un fichier illisible est refusé.
    $dims = optimize_image(UPLOAD_DIR . '/' . $name, 2400, true);
    if ($dims === null) {
        @unlink(UPLOAD_DIR . '/' . $name);
        throw new RuntimeException($file['name'] . ' : image illisible ou corrompue.');
    }
    [$w, $h] = $dims;
    make_variants($name);
    return [$name, $w, $h];
}

/** Supprime un fichier du dossier photos/ en toute sécurité. */
function delete_photo_file(string $filename): void
{
    $path = UPLOAD_DIR . '/' . basename($filename);
    if ($filename !== '' && is_file($path)) {
        @unlink($path);
    }
    if ($filename !== '') {
        delete_variants(basename($filename));
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
function optimize_image(string $path, int $max = 2400, bool $force = false): ?array
{
    $info = @getimagesize($path);
    if (!$info) {
        return $force ? null : [1200, 900];
    }
    [$w, $h, $type] = $info;
    if (!function_exists('imagecreatetruecolor')) {
        return $force ? null : [$w, $h];
    }
    $img = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
        IMAGETYPE_PNG  => @imagecreatefrompng($path),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
        default => false,
    };
    if (!$img) {
        return $force ? null : [$w, $h];
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
    if ($force || $ratio < 1 || $rotated || ($type === IMAGETYPE_JPEG && filesize($path) > 600 * 1024)) {
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

/* ---------- Variantes WebP (vignette + grand format) ---------- */

const PHOTO_SIZES = ['thumb' => 800, 'large' => 1920];

function variant_path(string $filename, string $size): string
{
    return UPLOAD_DIR . '/cache/' . pathinfo($filename, PATHINFO_FILENAME) . '-' . PHOTO_SIZES[$size] . '.webp';
}

/** URL de la variante WebP si elle existe, sinon de l'original. */
function variant_src(string $filename, string $size): ?string
{
    $path = variant_path($filename, $size);
    if (!is_file($path)) {
        return null;
    }
    return 'photos/cache/' . rawurlencode(basename($path)) . '?v=' . filemtime($path);
}

/** Crée les variantes WebP manquantes ou périmées d'une photo. Retourne true si au moins une a été créée. */
function make_variants(string $filename): bool
{
    $src = UPLOAD_DIR . '/' . basename($filename);
    if (!is_file($src) || !function_exists('imagewebp')) {
        return false;
    }
    if (!is_dir(UPLOAD_DIR . '/cache')) {
        @mkdir(UPLOAD_DIR . '/cache', 0775, true);
    }
    $todo = array_filter(array_keys(PHOTO_SIZES), function ($size) use ($filename, $src) {
        $p = variant_path($filename, $size);
        return !is_file($p) || filemtime($p) < filemtime($src);
    });
    if (!$todo) {
        return false;
    }
    $info = @getimagesize($src);
    $img = match ($info[2] ?? 0) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($src),
        IMAGETYPE_PNG  => @imagecreatefrompng($src),
        IMAGETYPE_WEBP => @imagecreatefromwebp($src),
        default => false,
    };
    if (!$img) {
        return false;
    }
    $w = imagesx($img);
    $h = imagesy($img);
    foreach ($todo as $size) {
        $ratio = min(1, PHOTO_SIZES[$size] / $w);
        $nw = (int)round($w * $ratio);
        $nh = (int)round($h * $ratio);
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagewebp($dst, variant_path($filename, $size), $size === 'thumb' ? 72 : 80);
    }
    return true;
}

function delete_variants(string $filename): void
{
    foreach (array_keys(PHOTO_SIZES) as $size) {
        @unlink(variant_path($filename, $size));
    }
}

/**
 * Génère les variantes manquantes (appelé depuis l'administration, jamais
 * sur le site public pour ne pas le ralentir). Limité en temps par appel.
 */
function ensure_all_variants(float $budgetSeconds = 8.0): int
{
    $start = microtime(true);
    $n = 0;
    foreach (db()->query('SELECT filename FROM photos')->fetchAll(PDO::FETCH_COLUMN) as $f) {
        if (microtime(true) - $start > $budgetSeconds) {
            break;
        }
        $n += (int)make_variants($f);
    }
    $hero = s('hero_photo') ?: 'photo-02.jpg';
    make_variants($hero);
    return $n;
}

/* ---------- Avis ---------- */

function reviews(bool $all = false): array
{
    return db()->query('SELECT * FROM reviews' . ($all ? '' : ' WHERE visible = 1') . ' ORDER BY position, id')->fetchAll();
}

function stars(int $n): string
{
    $n = max(0, min(5, $n));
    return '<span class="stars" aria-label="' . $n . ' sur 5">' . str_repeat('★', $n) . '<span class="stars-off">' . str_repeat('★', 5 - $n) . '</span></span>';
}

/** Affiche une note « 4,9 » à partir de la saisie « 4.9 » ou « 4,9 ». */
function rating_label(string $r): string
{
    return str_replace('.', ',', trim($r));
}

/* ---------- Ordre des sections de l'accueil ---------- */

/** Sections dans l'ordre choisi : [clé => visible (bool)]. Les nouvelles sections s'ajoutent à la fin. */
function home_sections(): array
{
    $order = array_values(array_intersect(array_filter(explode(',', s('sections_order'))), array_keys(HOME_SECTIONS)));
    foreach (array_keys(HOME_SECTIONS) as $k) {
        if (!in_array($k, $order, true)) {
            $order[] = $k;
        }
    }
    $hidden = array_filter(explode(',', s('sections_hidden')));
    $out = [];
    foreach ($order as $k) {
        $out[$k] = !in_array($k, $hidden, true);
    }
    return $out;
}

/**
 * Plafonds anti-abus pour les statistiques : par visiteur et par jour, et
 * global par jour (protège la base contre un envoi massif de fausses données).
 */
function stats_quota_reached(string $table, int $perVisitor, int $perDay): bool
{
    $table = $table === 'clicks' ? 'clicks' : 'visits';
    $day = date('Y-m-d');
    $st = db()->prepare("SELECT COUNT(*) FROM $table WHERE day = ? AND visitor = ?");
    $st->execute([$day, visitor_id()]);
    if ((int)$st->fetchColumn() >= $perVisitor) {
        return true;
    }
    $st = db()->prepare("SELECT COUNT(*) FROM $table WHERE day = ?");
    $st->execute([$day]);
    return (int)$st->fetchColumn() >= $perDay;
}
