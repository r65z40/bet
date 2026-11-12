<?php
/** Réception des clics (navigator.sendBeacon) pour les statistiques. */
require __DIR__ . '/includes/bootstrap.php';

$kind = (string)($_POST['k'] ?? '');
$page = (string)($_POST['p'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset(CLICK_KINDS[$kind])
    && in_array($page, ['accueil', 'plan', 'reservation', 'conditions', 'ma-reservation'], true)
    && !is_bot($_SERVER['HTTP_USER_AGENT'] ?? '')
    && empty($_SESSION['admin_id'])
    && same_origin()
    && !stats_quota_reached('clicks', 40, 5000)) {
    db()->prepare('INSERT INTO clicks(day, clicked_at, kind, page, visitor) VALUES(?,?,?,?,?)')
        ->execute([date('Y-m-d'), date('Y-m-d H:i:s'), $kind, $page, visitor_id()]);
}
http_response_code(204);

/** Refuse les envois provenant d'autres sites (en-tête Origin/Referer d'un autre domaine). */
function same_origin(): bool
{
    $host = (string)parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
    $from = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
    return $from === '' || (string)parse_url($from, PHP_URL_HOST) === $host;
}
