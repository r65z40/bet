<?php
/** Réception des clics (navigator.sendBeacon) pour les statistiques. */
require __DIR__ . '/includes/bootstrap.php';

$kind = (string)($_POST['k'] ?? '');
$page = (string)($_POST['p'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset(CLICK_KINDS[$kind])
    && in_array($page, ['accueil', 'plan', 'reservation'], true)
    && !is_bot($_SERVER['HTTP_USER_AGENT'] ?? '')
    && empty($_SESSION['admin_id'])) {
    db()->prepare('INSERT INTO clicks(day, clicked_at, kind, page, visitor) VALUES(?,?,?,?,?)')
        ->execute([date('Y-m-d'), date('Y-m-d H:i:s'), $kind, $page, visitor_id()]);
}
http_response_code(204);
