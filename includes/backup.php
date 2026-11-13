<?php
/**
 * Sauvegardes : copie cohérente de la base (réservations, factures, réglages, textes…) dans
 * data/backups/, compressée, automatiquement chaque jour (tâche planifiée) et à la demande.
 * Archive complète téléchargeable (base + photos + documents) et restauration de la base.
 *
 * Les sauvegardes contiennent des données personnelles et des clés (Stripe, SMTP…) :
 * le dossier data/ n'est jamais accessible depuis le web, et seul un administrateur
 * (mot de passe redemandé) peut les télécharger ou les restaurer.
 */

const BACKUP_DIR = DATA_DIR . '/backups';
const BACKUP_RE = '/^sauvegarde-(\d{4}-\d{2}-\d{2})-(\d{4})(?:-([a-z]+)(?:-[0-9a-f]{3})?)?\.sqlite\.gz$/';

function backup_dir(): string
{
    if (!is_dir(BACKUP_DIR)) {
        @mkdir(BACKUP_DIR, 0770, true);
    }
    if (!is_file(BACKUP_DIR . '/.htaccess')) {
        @file_put_contents(BACKUP_DIR . '/.htaccess', "Require all denied\n");
    }
    return BACKUP_DIR;
}

/** Sauvegardes existantes, la plus récente d'abord : [nom, date (timestamp), taille, origine]. */
function backup_list(): array
{
    $out = [];
    foreach (glob(backup_dir() . '/sauvegarde-*.sqlite.gz') ?: [] as $f) {
        if (preg_match(BACKUP_RE, basename($f), $m)) {
            $out[] = ['name' => basename($f), 'time' => (int)filemtime($f), 'size' => (int)filesize($f), 'kind' => $m[3] ?? 'manuelle'];
        }
    }
    usort($out, fn($a, $b) => $b['time'] <=> $a['time']);
    return $out;
}

/** Date de la dernière sauvegarde (d'après les fichiers : reste juste après une restauration). $kind = 'auto' pour les automatiques. */
function backup_last_time(?string $kind = null): int
{
    foreach (backup_list() as $b) {
        if ($kind === null || $b['kind'] === $kind) {
            return $b['time'];
        }
    }
    return 0;
}

function backup_path(string $name): ?string
{
    return preg_match(BACKUP_RE, $name) && is_file(BACKUP_DIR . '/' . $name) ? BACKUP_DIR . '/' . $name : null;
}

/** Copie cohérente de la base dans un fichier SQLite autonome (même pendant que le site tourne). */
function backup_snapshot(string $dest): void
{
    @unlink($dest);
    $pdo = db();
    try {
        $pdo->prepare('VACUUM INTO ?')->execute([$dest]); // SQLite ≥ 3.27 : copie propre et compacte
    } catch (Throwable) {
        // Secours : on bloque les écritures le temps de copier le fichier
        $pdo->exec('BEGIN IMMEDIATE');
        try {
            if (!copy(db_file(), $dest)) {
                throw new RuntimeException('copie de la base impossible');
            }
        } finally {
            $pdo->exec('COMMIT');
        }
    }
    if (!is_file($dest) || filesize($dest) < 1024) {
        throw new RuntimeException('la copie de la base est vide');
    }
}

/** Crée une sauvegarde compressée. $kind : manuelle, auto, avant-restauration. Retourne le nom du fichier. */
function backup_create(string $kind = 'manuelle'): string
{
    $dir = backup_dir();
    $kind = preg_replace('/[^a-z]/', '', strtolower($kind)) ?: 'manuelle';
    $name = 'sauvegarde-' . date('Y-m-d-Hi') . '-' . $kind . '.sqlite.gz';
    if (is_file($dir . '/' . $name)) {
        $name = 'sauvegarde-' . date('Y-m-d-Hi') . '-' . $kind . '-' . substr(bin2hex(random_bytes(2)), 0, 3) . '.sqlite.gz';
    }
    $tmp = $dir . '/.tmp-' . bin2hex(random_bytes(6)) . '.sqlite';
    try {
        backup_snapshot($tmp);
        $in = fopen($tmp, 'rb');
        $out = gzopen($dir . '/' . $name . '.part', 'wb6');
        if (!$in || !$out) {
            throw new RuntimeException('écriture de la sauvegarde impossible (dossier data/backups)');
        }
        while (!feof($in)) {
            gzwrite($out, (string)fread($in, 1 << 20));
        }
        fclose($in);
        gzclose($out);
        rename($dir . '/' . $name . '.part', $dir . '/' . $name);
        @chmod($dir . '/' . $name, 0640);
    } finally {
        @unlink($tmp);
        @unlink($dir . '/' . $name . '.part');
    }
    save_setting('_backup_last', (string)time());
    save_setting('_backup_error', '');
    settings(true);
    backup_prune();
    return $name;
}

/** Conserve les N dernières sauvegardes automatiques et 10 manuelles / de sécurité. */
function backup_prune(): void
{
    $keep = ['auto' => max(3, (int)bk_num('bk_backup_keep', 14)), 'other' => 10];
    $seen = ['auto' => 0, 'other' => 0];
    foreach (backup_list() as $b) {
        $k = $b['kind'] === 'auto' ? 'auto' : 'other';
        if (++$seen[$k] > $keep[$k]) {
            @unlink(BACKUP_DIR . '/' . $b['name']);
        }
    }
}

/** Décompresse une sauvegarde dans un fichier temporaire et vérifie qu'il s'agit bien d'une base du site. */
function backup_extract_checked(string $gzPath): string
{
    $tmp = backup_dir() . '/.restore-' . bin2hex(random_bytes(6)) . '.sqlite';
    $in = gzopen($gzPath, 'rb');
    $out = fopen($tmp, 'wb');
    if (!$in || !$out) {
        throw new RuntimeException('lecture de la sauvegarde impossible');
    }
    $total = 0;
    while (!gzeof($in)) {
        $chunk = (string)gzread($in, 1 << 20);
        $total += strlen($chunk);
        if ($total > 500 * 1024 * 1024) {
            fclose($out); gzclose($in); @unlink($tmp);
            throw new RuntimeException('fichier trop volumineux');
        }
        fwrite($out, $chunk);
    }
    gzclose($in);
    fclose($out);
    backup_check_db($tmp);
    return $tmp;
}

/** La base doit être intacte et contenir les tables du site. */
function backup_check_db(string $file): void
{
    try {
        if (file_get_contents($file, false, null, 0, 16) !== "SQLite format 3\0") {
            throw new RuntimeException('ce n’est pas une base SQLite');
        }
        $p = new PDO('sqlite:' . $file, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        if (strtolower((string)$p->query('PRAGMA integrity_check')->fetchColumn()) !== 'ok') {
            throw new RuntimeException('base endommagée');
        }
        $tables = $p->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN);
        foreach (['settings', 'bookings', 'admins'] as $t) {
            if (!in_array($t, $tables, true)) {
                throw new RuntimeException('ce n’est pas une sauvegarde de ce site (table « ' . $t . ' » absente)');
            }
        }
        if (!(int)$p->query('SELECT COUNT(*) FROM admins WHERE active = 1')->fetchColumn()) {
            throw new RuntimeException('aucun compte administrateur actif dans cette sauvegarde');
        }
        $p = null;
    } catch (Throwable $e) {
        @unlink($file);
        throw new RuntimeException('Sauvegarde refusée : ' . $e->getMessage());
    }
}

/**
 * Remplace la base par celle d'une sauvegarde vérifiée ($sqliteFile, décompressée).
 * Une sauvegarde « avant-restauration » de l'état actuel est faite juste avant.
 */
function backup_restore_file(string $sqliteFile): string
{
    $safety = backup_create('avantrestauration');
    $target = db_file();
    $lock = fopen(DATA_DIR . '/.lock', 'c');
    flock($lock, LOCK_EX);
    try {
        if (!rename($sqliteFile, $target . '.new')) {
            throw new RuntimeException('impossible de placer la base restaurée');
        }
        // Journal éventuel de l'ancienne base : il ne doit pas être rejoué sur la nouvelle
        foreach (['-journal', '-wal', '-shm'] as $sfx) {
            @unlink($target . $sfx);
        }
        rename($target . '.new', $target);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
    // Trace dans la base restaurée (la connexion de cette page pointe encore sur l'ancienne)
    try {
        $a = current_admin();
        $p = new PDO('sqlite:' . $target, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $p->prepare('INSERT INTO admin_log(admin_id, username, at, ip, action) VALUES(?,?,?,?,?)')
            ->execute([$a['id'] ?? null, $a['username'] ?? '—', date('Y-m-d H:i:s'), ip_fingerprint(), 'Base restaurée depuis une sauvegarde (état précédent conservé : ' . $safety . ')']);
    } catch (Throwable) {
    }
    return $safety;
}

/**
 * Archive ZIP complète à télécharger : la base (sauvegarde choisie ou instantané), les photos envoyées,
 * les documents, et un mode d'emploi. Retourne le chemin du fichier temporaire.
 */
function backup_zip(?string $name = null): string
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('l’extension ZIP de PHP n’est pas disponible : téléchargez la base seule');
    }
    $dir = backup_dir();
    $zipPath = $dir . '/.zip-' . bin2hex(random_bytes(6)) . '.zip';
    $dbTmp = $name ? backup_extract_checked(backup_path($name) ?? throw new RuntimeException('sauvegarde introuvable')) : $dir . '/.snap-' . bin2hex(random_bytes(6)) . '.sqlite';
    try {
        if (!$name) {
            backup_snapshot($dbTmp);
        }
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('création de l’archive impossible');
        }
        $zip->addFile($dbTmp, 'base-de-donnees.sqlite');
        foreach (glob(UPLOAD_DIR . '/*') ?: [] as $f) {
            if (is_file($f) && preg_match('/\.(jpe?g|png|webp|gif)$/i', $f)) {
                $zip->addFile($f, 'photos/' . basename($f));
            }
        }
        foreach (glob(ROOT . '/documents/*') ?: [] as $f) {
            if (is_file($f) && preg_match('/\.(pdf|docx)$/i', $f)) {
                $zip->addFile($f, 'documents/' . basename($f));
            }
        }
        $zip->addFromString('LISEZ-MOI.txt', "Sauvegarde du site " . s('site_name') . " — " . date('d/m/Y H:i') . "\r\n\r\n"
            . "- base-de-donnees.sqlite : réservations, factures, clients, réglages, textes, comptes d'administration.\r\n"
            . "- photos/ et documents/ : fichiers envoyés depuis l'administration.\r\n\r\n"
            . "CONFIDENTIEL : contient des données personnelles de clients et les clés des services (Stripe, e-mail…).\r\n"
            . "Conservez ce fichier en lieu sûr (disque chiffré, coffre en ligne), ne l'envoyez pas par e-mail.\r\n\r\n"
            . "Restaurer : Administration › Réglages › Sauvegardes › « Restaurer depuis un fichier » (la base seule),\r\n"
            . "puis renvoyer les dossiers photos/ et documents/ par FTP si besoin.\r\n");
        $zip->close();
    } finally {
        @unlink($dbTmp);
    }
    return $zipPath;
}

/** Envoie un fichier en téléchargement puis le supprime s'il est temporaire. */
function backup_send(string $path, string $downloadName, string $type, bool $delete): void
{
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: ' . $type);
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: no-store');
    readfile($path);
    if ($delete) {
        @unlink($path);
    }
    exit;
}

/** Tâche planifiée : une sauvegarde automatique par jour. */
function backup_cron(array &$errors): bool
{
    if (s('bk_backup_auto') === '0' || time() - backup_last_time('auto') < 20 * 3600) {
        return false;
    }
    try {
        backup_create('auto');
        return true;
    } catch (Throwable $e) {
        save_setting('_backup_error', date('d/m/Y H:i') . ' — ' . $e->getMessage());
        $errors[] = 'sauvegarde : ' . $e->getMessage();
        admin_notify('conflict', '⚠ Sauvegarde automatique impossible', [$e->getMessage(), 'Vérifiez Réglages › Sauvegardes.'], 'sauvegardes.php');
        return false;
    } finally {
        // nettoyage des fichiers temporaires oubliés (coupure pendant une sauvegarde)
        foreach (['tmp', 'restore', 'zip', 'snap'] as $prefix) {
            foreach (glob(BACKUP_DIR . '/.' . $prefix . '-*') ?: [] as $f) {
                if (time() - (int)@filemtime($f) > 3600) {
                    @unlink($f);
                }
            }
        }
    }
}
