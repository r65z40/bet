<?php
/**
 * Point d'entrée commun : configuration, session, base de données, helpers.
 * Stack : PHP 8 + SQLite (PDO). Aucune dépendance externe.
 */
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
define('DATA_DIR', ROOT . '/data');
define('UPLOAD_DIR', ROOT . '/photos');   // photos du site (existantes + envoyées)
define('DB_FILE', DATA_DIR . '/site.sqlite');
define('MAX_UPLOAD_BYTES', 8 * 1024 * 1024); // 8 Mo

date_default_timezone_set('Europe/Paris');

// Pas de cookie pour les visiteurs : la session ne démarre que dans l'admin
// (ou si un administrateur connecté consulte le site, pour l'exclure des stats).
if (session_status() === PHP_SESSION_NONE
    && (defined('ADMIN_AREA') || isset($_COOKIE[session_name()]))) {
    session_set_cookie_params([
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

require_once __DIR__ . '/content.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/stats.php';
