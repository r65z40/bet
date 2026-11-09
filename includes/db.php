<?php
/** Connexion SQLite + création automatique du schéma et des contenus initiaux. */

function db(): PDO
{
    static $pdo = null;
    if ($pdo) {
        return $pdo;
    }
    foreach ([DATA_DIR, UPLOAD_DIR] as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }
    $pdo = new PDO('sqlite:' . DB_FILE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA journal_mode = WAL');
    install_schema($pdo);
    return $pdo;
}

function install_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS login_attempts (
            ip TEXT NOT NULL,
            at INTEGER NOT NULL
        );
        CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL DEFAULT ''
        );
        -- Listes éditables (cartes, équipements, tarifs…) regroupées par « grp »
        CREATE TABLE IF NOT EXISTS items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            grp TEXT NOT NULL,
            icon TEXT NOT NULL DEFAULT '',
            title TEXT NOT NULL DEFAULT '',
            text TEXT NOT NULL DEFAULT '',
            value TEXT NOT NULL DEFAULT '',
            position INTEGER NOT NULL DEFAULT 0,
            visible INTEGER NOT NULL DEFAULT 1
        );
        CREATE INDEX IF NOT EXISTS idx_items_grp ON items(grp, position);
        CREATE TABLE IF NOT EXISTS photos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            filename TEXT NOT NULL,
            alt TEXT NOT NULL DEFAULT '',
            width INTEGER NOT NULL DEFAULT 1200,
            height INTEGER NOT NULL DEFAULT 900,
            position INTEGER NOT NULL DEFAULT 0,
            visible INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS visits (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            day TEXT NOT NULL,
            visited_at TEXT NOT NULL,
            page TEXT NOT NULL,
            visitor TEXT NOT NULL,
            referrer TEXT NOT NULL DEFAULT '',
            device TEXT NOT NULL DEFAULT 'ordinateur'
        );
        CREATE INDEX IF NOT EXISTS idx_visits_day ON visits(day);
        CREATE TABLE IF NOT EXISTS clicks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            day TEXT NOT NULL,
            clicked_at TEXT NOT NULL,
            kind TEXT NOT NULL,
            page TEXT NOT NULL DEFAULT '',
            visitor TEXT NOT NULL
        );
        CREATE INDEX IF NOT EXISTS idx_clicks_day ON clicks(day);
        CREATE TABLE IF NOT EXISTS reviews (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            author TEXT NOT NULL,
            source TEXT NOT NULL DEFAULT 'google',
            rating INTEGER NOT NULL DEFAULT 5,
            review_date TEXT NOT NULL DEFAULT '',
            event TEXT NOT NULL DEFAULT '',
            text TEXT NOT NULL DEFAULT '',
            position INTEGER NOT NULL DEFAULT 0,
            visible INTEGER NOT NULL DEFAULT 1
        );
    ");

    if (setting_raw($pdo, '_seeded') === null) {
        seed_content($pdo);
    }
    if ((int)setting_raw($pdo, '_schema_version') < SCHEMA_VERSION) {
        migrate($pdo);
    }
}

function setting_raw(PDO $pdo, string $key): ?string
{
    $s = $pdo->prepare('SELECT value FROM settings WHERE key = ?');
    $s->execute([$key]);
    $v = $s->fetchColumn();
    return $v === false ? null : (string)$v;
}

/** Contenus initiaux = contenu actuel du site HTML d'origine. */
function seed_content(PDO $pdo): void
{
    $pdo->beginTransaction();

    $set = $pdo->prepare('INSERT OR IGNORE INTO settings(key, value) VALUES(?, ?)');
    foreach (SETTINGS_SCHEMA as $section) {
        foreach ($section['fields'] as $key => $f) {
            $set->execute([$key, $f['default'] ?? '']);
        }
    }

    $ins = $pdo->prepare('INSERT INTO items(grp, icon, title, text, value, position) VALUES(?,?,?,?,?,?)');
    foreach (DEFAULT_ITEMS as $grp => $rows) {
        foreach ($rows as $i => $r) {
            $ins->execute([$grp, $r['icon'] ?? '', $r['title'] ?? '', $r['text'] ?? '', $r['value'] ?? '', $i + 1]);
        }
    }

    // Photos déjà présentes dans /photos (photo-01.jpg … photo-20.jpg)
    $files = glob(UPLOAD_DIR . '/photo-*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [];
    sort($files, SORT_NATURAL);
    $p = $pdo->prepare('INSERT INTO photos(filename, alt, width, height, position) VALUES(?,?,?,?,?)');
    foreach ($files as $i => $path) {
        [$w, $h] = @getimagesize($path) ?: [1200, 900];
        $p->execute([basename($path), 'La Salle RN6 à Appoigny près d’Auxerre — vue de la salle, photo ' . ($i + 1), $w, $h, $i + 1]);
        if (basename($path) === 'photo-02.jpg') {
            $pdo->prepare("INSERT OR REPLACE INTO settings(key, value) VALUES('hero_photo', ?)")->execute([basename($path)]);
        }
    }

    $pdo->exec("INSERT OR REPLACE INTO settings(key, value) VALUES('_seeded', '1')");
    $pdo->commit();
}

/** Mise à jour d'un site déjà installé : ajoute les nouveaux réglages sans toucher aux existants. */
function migrate(PDO $pdo): void
{
    $set = $pdo->prepare('INSERT OR IGNORE INTO settings(key, value) VALUES(?, ?)');
    foreach (SETTINGS_SCHEMA as $section) {
        foreach ($section['fields'] as $key => $f) {
            $set->execute([$key, $f['default'] ?? '']);
        }
    }
    $pdo->prepare("INSERT OR REPLACE INTO settings(key, value) VALUES('_schema_version', ?)")->execute([(string)SCHEMA_VERSION]);
}
