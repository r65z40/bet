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
    $pdo = new PDO('sqlite:' . db_file());
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA journal_mode = WAL');
    install_schema($pdo);
    return $pdo;
}

/**
 * Chemin de la base. Son nom contient une partie aléatoire, mémorisée dans
 * data/config.php (un fichier PHP n'est jamais téléchargeable) : même si le
 * serveur ignorait les .htaccess, la base ne pourrait pas être devinée.
 * Une ancienne base data/site.sqlite est renommée automatiquement.
 */
function db_file(): string
{
    $cfgFile = DATA_DIR . '/config.php';
    $cfg = is_file($cfgFile) ? (include $cfgFile) : null;
    if (!is_array($cfg) || empty($cfg['db'])) {
        $lock = fopen(DATA_DIR . '/.lock', 'c');
        flock($lock, LOCK_EX);
        clearstatcache();
        $cfg = is_file($cfgFile) ? (include $cfgFile) : null;
        if (!is_array($cfg) || empty($cfg['db'])) {
            $cfg = ['db' => 'site-' . bin2hex(random_bytes(12)) . '.sqlite'];
            $old = DATA_DIR . '/site.sqlite';
            foreach (['', '-wal', '-shm'] as $suffix) {
                if (is_file($old . $suffix)) {
                    rename($old . $suffix, DATA_DIR . '/' . $cfg['db'] . $suffix);
                }
            }
            $tmp = $cfgFile . '.' . bin2hex(random_bytes(4));
            file_put_contents($tmp, "<?php\n// Généré automatiquement : nom secret de la base de données.\nreturn " . var_export($cfg, true) . ";\n");
            rename($tmp, $cfgFile);
        }
        flock($lock, LOCK_UN);
        fclose($lock);
    }
    return DATA_DIR . '/' . basename($cfg['db']);
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
        CREATE TABLE IF NOT EXISTS bookings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ref TEXT NOT NULL UNIQUE,
            token TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'pending',
            mode TEXT NOT NULL DEFAULT 'day',
            start_at TEXT NOT NULL,
            end_at TEXT NOT NULL,
            hours REAL NOT NULL DEFAULT 24,
            guests INTEGER NOT NULL DEFAULT 0,
            event_type TEXT NOT NULL DEFAULT '',
            first_name TEXT NOT NULL DEFAULT '',
            last_name TEXT NOT NULL DEFAULT '',
            email TEXT NOT NULL DEFAULT '',
            phone TEXT NOT NULL DEFAULT '',
            company TEXT NOT NULL DEFAULT '',
            is_asso INTEGER NOT NULL DEFAULT 0,
            asso_name TEXT NOT NULL DEFAULT '',
            address TEXT NOT NULL DEFAULT '',
            zip TEXT NOT NULL DEFAULT '',
            city TEXT NOT NULL DEFAULT '',
            message TEXT NOT NULL DEFAULT '',
            lines_json TEXT NOT NULL DEFAULT '[]',
            subtotal_ht REAL NOT NULL DEFAULT 0,
            discount_ht REAL NOT NULL DEFAULT 0,
            total_ht REAL NOT NULL DEFAULT 0,
            vat REAL NOT NULL DEFAULT 0,
            total_ttc REAL NOT NULL DEFAULT 0,
            provider TEXT NOT NULL DEFAULT '',
            provider_order TEXT NOT NULL DEFAULT '',
            payment_ref TEXT NOT NULL DEFAULT '',
            paid_at TEXT,
            hold_until TEXT,
            invoice_number TEXT NOT NULL DEFAULT '',
            refunded_amount REAL NOT NULL DEFAULT 0,
            credit_number TEXT NOT NULL DEFAULT '',
            cancelled_at TEXT,
            deposit_status TEXT NOT NULL DEFAULT '',
            admin_notes TEXT NOT NULL DEFAULT '',
            visitor TEXT NOT NULL DEFAULT '',
            created_at TEXT NOT NULL,
            cgv_at TEXT
        );
        CREATE INDEX IF NOT EXISTS idx_bookings_dates ON bookings(start_at, end_at);
        CREATE INDEX IF NOT EXISTS idx_bookings_status ON bookings(status);
        CREATE TABLE IF NOT EXISTS booking_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            booking_id INTEGER NOT NULL,
            at TEXT NOT NULL,
            message TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS booking_options (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            label TEXT NOT NULL,
            description TEXT NOT NULL DEFAULT '',
            price REAL NOT NULL DEFAULT 0,
            unit TEXT NOT NULL DEFAULT 'forfait',
            position INTEGER NOT NULL DEFAULT 0,
            visible INTEGER NOT NULL DEFAULT 1
        );
        CREATE TABLE IF NOT EXISTS booking_blocks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            start_at TEXT NOT NULL,
            end_at TEXT NOT NULL,
            label TEXT NOT NULL DEFAULT ''
        );
        CREATE TABLE IF NOT EXISTS ext_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            uid TEXT NOT NULL DEFAULT '',
            start_at TEXT NOT NULL,
            end_at TEXT NOT NULL,
            summary TEXT NOT NULL DEFAULT ''
        );
        CREATE INDEX IF NOT EXISTS idx_ext_dates ON ext_events(start_at, end_at);
        CREATE TABLE IF NOT EXISTS special_prices (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            label TEXT NOT NULL,
            date_from TEXT NOT NULL,
            date_to TEXT NOT NULL,
            kind TEXT NOT NULL DEFAULT 'percent',
            value REAL NOT NULL DEFAULT 0,
            active INTEGER NOT NULL DEFAULT 1
        );
        CREATE TABLE IF NOT EXISTS promo_codes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT NOT NULL UNIQUE,
            kind TEXT NOT NULL DEFAULT 'percent',
            value REAL NOT NULL DEFAULT 0,
            balance REAL NOT NULL DEFAULT 0,
            max_uses INTEGER NOT NULL DEFAULT 0,
            valid_from TEXT NOT NULL DEFAULT '',
            valid_to TEXT NOT NULL DEFAULT '',
            min_total REAL NOT NULL DEFAULT 0,
            label TEXT NOT NULL DEFAULT '',
            active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS push_subs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            admin_id INTEGER NOT NULL,
            endpoint TEXT NOT NULL UNIQUE,
            p256dh TEXT NOT NULL,
            auth TEXT NOT NULL,
            ua TEXT NOT NULL DEFAULT '',
            created_at TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS password_resets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            admin_id INTEGER NOT NULL,
            token_hash TEXT NOT NULL UNIQUE,
            purpose TEXT NOT NULL DEFAULT 'reset',
            expires_at INTEGER NOT NULL,
            used_at TEXT,
            created_at TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS admin_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            admin_id INTEGER,
            username TEXT NOT NULL DEFAULT '',
            at TEXT NOT NULL,
            ip TEXT NOT NULL DEFAULT '',
            action TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS mail_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            at TEXT NOT NULL,
            to_addr TEXT NOT NULL,
            subject TEXT NOT NULL DEFAULT '',
            ok INTEGER NOT NULL DEFAULT 0,
            info TEXT NOT NULL DEFAULT ''
        );
        CREATE TABLE IF NOT EXISTS mail_campaigns (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            subject TEXT NOT NULL,
            body TEXT NOT NULL,
            audience TEXT NOT NULL,
            created_at TEXT NOT NULL,
            total INTEGER NOT NULL DEFAULT 0,
            sent INTEGER NOT NULL DEFAULT 0,
            failed INTEGER NOT NULL DEFAULT 0,
            status TEXT NOT NULL DEFAULT 'sending'
        );
        CREATE TABLE IF NOT EXISTS mail_queue (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            campaign_id INTEGER NOT NULL,
            email TEXT NOT NULL,
            first_name TEXT NOT NULL DEFAULT '',
            last_name TEXT NOT NULL DEFAULT '',
            booking_id INTEGER,
            status TEXT NOT NULL DEFAULT 'pending',
            error TEXT NOT NULL DEFAULT '',
            sent_at TEXT
        );
        CREATE INDEX IF NOT EXISTS idx_queue ON mail_queue(campaign_id, status);
        CREATE TABLE IF NOT EXISTS mail_optout (
            email TEXT PRIMARY KEY,
            at TEXT NOT NULL
        );
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

    // Colonnes ajoutées aux réservations (codes promo, bons cadeaux, e-mails automatiques)
    $bcols = array_column($pdo->query('PRAGMA table_info(bookings)')->fetchAll(), 'name');
    foreach ([
        'promo_code' => "TEXT NOT NULL DEFAULT ''", 'promo_ht' => 'REAL NOT NULL DEFAULT 0',
        'gift_code' => "TEXT NOT NULL DEFAULT ''", 'gift_amount' => 'REAL NOT NULL DEFAULT 0', 'gift_restored' => 'INTEGER NOT NULL DEFAULT 0',
        'amount_due' => 'REAL', 'reminder_sent_at' => 'TEXT', 'review_sent_at' => 'TEXT',
        'moved_at' => 'TEXT', 'moved_from' => "TEXT NOT NULL DEFAULT ''",
        'deposit_amount' => 'REAL', 'deposit_held' => 'REAL NOT NULL DEFAULT 0', 'deposit_captured' => 'REAL NOT NULL DEFAULT 0',
        'deposit_pi' => "TEXT NOT NULL DEFAULT ''", 'deposit_at' => 'TEXT', 'deposit_requested_at' => 'TEXT', 'deposit_reason' => "TEXT NOT NULL DEFAULT ''", 'deposit_captured_at' => 'TEXT',
    ] as $col => $def) {
        if (!in_array($col, $bcols, true)) {
            $pdo->exec("ALTER TABLE bookings ADD COLUMN $col $def");
        }
    }
    // Colonnes ajoutées aux comptes administrateurs (droits, e-mail, désactivation)
    $cols = array_column($pdo->query('PRAGMA table_info(admins)')->fetchAll(), 'name');
    foreach ([
        'email' => "TEXT NOT NULL DEFAULT ''",
        'perms' => "TEXT NOT NULL DEFAULT 'all'",      // comptes existants : tous les droits
        'active' => 'INTEGER NOT NULL DEFAULT 1',
        'pw_changed_at' => 'INTEGER NOT NULL DEFAULT 0',
        'last_login' => 'TEXT',
    ] as $col => $def) {
        if (!in_array($col, $cols, true)) {
            $pdo->exec("ALTER TABLE admins ADD COLUMN $col $def");
        }
    }

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
    // v7 : compte unique existant sans e-mail → adresse de contact du site (pour « mot de passe oublié »)
    if ((int)setting_raw($pdo, '_schema_version') < 7 && (int)$pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn() === 1) {
        $contact = (string)(setting_raw($pdo, 'contact_email') ?? '');
        if (filter_var($contact, FILTER_VALIDATE_EMAIL)) {
            $pdo->prepare("UPDATE admins SET email = ? WHERE email = ''")->execute([$contact]);
        }
    }
    // v6 : un site qui envoyait par mail() (pas de mot de passe SMTP) garde ce fonctionnement
    if (setting_raw($pdo, 'bk_mail_method') === null && setting_raw($pdo, '_seeded') !== null && setting_raw($pdo, 'bk_smtp_user') !== null) {
        $pdo->prepare("INSERT INTO settings(key, value) VALUES('bk_mail_method', ?)")
            ->execute([(string)setting_raw($pdo, 'bk_smtp_pass') === '' ? 'php' : 'smtp']);
    }
    foreach (BOOKING_SETTINGS as $section) {
        foreach ($section['fields'] as $key => $f) {
            $set->execute([$key, $f['default'] ?? '']);
        }
    }
    // v4 : les tarifs de l'accueil viennent du système de réservation (affichage TTC)
    if ((int)setting_raw($pdo, '_schema_version') < 4 && setting_raw($pdo, '_seeded') !== null) {
        $pdo->prepare("UPDATE items SET visible = 0 WHERE grp = 'tarif_options' AND text IN (?, ?, ?)")
            ->execute(['Location Ecran + sono', 'Location de vaisselle', 'Préparation & mise en place des tables et chaises selon vos plans']);
        $pdo->prepare("UPDATE settings SET value = ? WHERE key = 'tarifs_subtitle' AND value = ?")
            ->execute(['Prix TTC (TVA 20 % incluse), pour une location de 24 h selon le jour d’arrivée.', 'Tarifs hors taxes. La TVA au taux de 20 % s’ajoute au montant total.']);
        $pdo->prepare("UPDATE settings SET value = ? WHERE key = 'tarifs_badge' AND value = ?")
            ->execute(['Prix TTC · paiement en ligne sécurisé', 'Tarifs HT · TVA 20 % non incluse']);
        $pdo->prepare("UPDATE settings SET value = ? WHERE key = 'resa_lead' AND value = ?")
            ->execute(['Consultez les disponibilités, choisissez votre créneau et vos options, et réglez en ligne en toute sécurité.', 'Consultez les disponibilités de la salle et choisissez directement votre créneau de location.']);
        $pdo->prepare("UPDATE settings SET value = ? WHERE key = 'resa_notice' AND value = ?")
            ->execute(['Choisissez votre formule et une date disponible, ajoutez vos options puis réglez en ligne : la salle vous est réservée immédiatement.', 'Choisissez une date disponible dans l’agenda ci-dessous puis suivez les étapes de réservation.']);
    }
    // Lien secret du flux agenda (abonnement depuis l'iPhone / Google Agenda) et de la tâche planifiée
    $set->execute(['bk_ics_token', bin2hex(random_bytes(16))]);
    $set->execute(['bk_cron_token', bin2hex(random_bytes(16))]);
    // v11 : remboursement intégral au moins 30 jours avant (au lieu de 80 %), si la règle n'a pas été personnalisée
    if ((int)setting_raw($pdo, '_schema_version') < 11 && setting_raw($pdo, '_seeded') !== null) {
        $pdo->prepare("UPDATE settings SET value = ? WHERE key = 'bk_cancel_rules' AND replace(value, char(13), '') = ?")->execute(["30 = 100\n7 = 50", "30 = 80\n7 = 50"]);
        $pdo->prepare("UPDATE settings SET value = replace(value, ?, ?) WHERE key = 'bk_cgv'")
            ->execute(['**Au moins 30 jours avant** la date : remboursement de 80 % du montant payé.', '**Au moins 30 jours avant** la date : remboursement intégral du montant payé.']);
    }
    // v10 : les réservations déjà passées ne reçoivent pas de rappel ni de demande d'avis à la mise à jour
    if ((int)setting_raw($pdo, '_schema_version') < 10 && setting_raw($pdo, '_seeded') !== null) {
        $now = date('Y-m-d H:i:s');
        $pdo->prepare('UPDATE bookings SET review_sent_at = ? WHERE review_sent_at IS NULL AND end_at < ?')->execute([$now, $now]);
    }
    // Options réservables initiales (une seule fois)
    if (setting_raw($pdo, '_options_seeded') === null) {
        $ins = $pdo->prepare('INSERT INTO booking_options(label, description, price, unit, position) VALUES(?,?,?,?,?)');
        foreach (BOOKING_DEFAULT_OPTIONS as $i => $o) {
            $ins->execute([$o['label'], $o['description'], $o['price'], $o['unit'], $i + 1]);
        }
        $pdo->exec("INSERT OR REPLACE INTO settings(key, value) VALUES('_options_seeded', '1')");
    }
    $pdo->prepare("INSERT OR REPLACE INTO settings(key, value) VALUES('_schema_version', ?)")->execute([(string)SCHEMA_VERSION]);
}
