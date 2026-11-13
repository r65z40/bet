<?php
/**
 * Point d'entrée commun : configuration, session, base de données, helpers.
 * Stack : PHP 8 + SQLite (PDO). Aucune dépendance externe.
 */
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
define('REQUEST_T0', microtime(true));

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443
        || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}
define('DATA_DIR', ROOT . '/data');
define('UPLOAD_DIR', ROOT . '/photos');   // photos du site (existantes + envoyées)
define('MAX_UPLOAD_BYTES', 8 * 1024 * 1024); // 8 Mo

date_default_timezone_set('Europe/Paris');

// Jamais de message d'erreur technique affiché aux visiteurs (chemins, requêtes…)
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// En-têtes de sécurité (valables aussi sur les serveurs qui ignorent .htaccess)
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    if (!defined('ADMIN_AREA')) {
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; "
            . "frame-src https://www.google.com https://maps.google.com; connect-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'self'");
    }
    header_remove('X-Powered-By');
    if (is_https()) {
        header('Strict-Transport-Security: max-age=15552000');
    }
}

// Pas de cookie pour les visiteurs : la session ne démarre que dans l'admin
// (ou si un administrateur connecté consulte le site, pour l'exclure des stats).
if (session_status() === PHP_SESSION_NONE
    && (defined('ADMIN_AREA') || isset($_COOKIE[session_name()]))) {
    ini_set('session.use_strict_mode', '1');      // refuse les identifiants de session inventés
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => is_https(),
    ]);
    // Pages publiques : simple lecture (savoir si c'est un administrateur, pour les statistiques) sans garder
    // le verrou de session, qui ferait attendre la page tant qu'un onglet de l'admin travaille.
    defined('ADMIN_AREA') ? session_start() : session_start(['read_and_close' => true]);
}

require_once __DIR__ . '/content.php';
require_once __DIR__ . '/legal.php';
require_once __DIR__ . '/booking_config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/admins.php';
require_once __DIR__ . '/stats.php';
require_once __DIR__ . '/booking.php';
require_once __DIR__ . '/promo.php';
require_once __DIR__ . '/cron.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/mail_templates.php';
require_once __DIR__ . '/mailing.php';
require_once __DIR__ . '/ical.php';
require_once __DIR__ . '/gcal_write.php';
require_once __DIR__ . '/deposit.php';
require_once __DIR__ . '/notify.php';
require_once __DIR__ . '/backup.php';
require_once __DIR__ . '/drive.php';
require_once __DIR__ . '/invoice.php';
require_once __DIR__ . '/compta.php';
require_once __DIR__ . '/webpush.php';
require_once __DIR__ . '/payments.php';
require_once __DIR__ . '/layout.php';

// Diagnostic : toute page qui met plus de 2 s à se construire est notée dans data/perf.log (lisible par FTP)
if (PHP_SAPI !== 'cli') {
    inflight_start();
    register_shutdown_function(function () {
        inflight_end();
        $d = microtime(true) - REQUEST_T0;
        $err = error_get_last();
        $fatal = $err && in_array($err['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true);
        if ($d > 2 || $fatal) {
            perf_log(sprintf('page lente : %.1f s — %s — %s%s', $d, request_label(), PHP_SAPI, $fatal ? ' — ERREUR : ' . mb_substr($err['message'], 0, 200) : ''));
        }
    });
}

// Tâches automatiques de secours (si la tâche planifiée OVH n'est pas configurée)
if (PHP_SAPI !== 'cli' && !defined('NO_CRON')) {
    try {
        cron_on_shutdown();
    } catch (Throwable) {
    }
}
