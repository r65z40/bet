<?php
/** Abonnement de cet appareil aux notifications (JSON). */
require __DIR__ . '/_init.php';
$admin = require_admin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('{}');
}
$in = json_decode((string)file_get_contents('php://input'), true) ?: [];
$_POST['csrf'] = (string)($in['csrf'] ?? '');
check_csrf();
$do = (string)($in['do'] ?? '');
$sub = (array)($in['subscription'] ?? []);
$endpoint = (string)($sub['endpoint'] ?? '');

if ($do === 'subscribe') {
    $p256 = (string)($sub['keys']['p256dh'] ?? '');
    $auth = (string)($sub['keys']['auth'] ?? '');
    if (!push_endpoint_allowed($endpoint) || strlen($endpoint) > 1000 || !preg_match('/^[A-Za-z0-9_-]{80,100}$/', $p256) || !preg_match('/^[A-Za-z0-9_-]{16,30}$/', $auth)) {
        http_response_code(400);
        exit(json_encode(['error' => 'Abonnement refusé.']));
    }
    db()->prepare('INSERT INTO push_subs(admin_id, endpoint, p256dh, auth, ua, created_at) VALUES(?,?,?,?,?,?)
        ON CONFLICT(endpoint) DO UPDATE SET admin_id = excluded.admin_id, p256dh = excluded.p256dh, auth = excluded.auth')
        ->execute([$admin['id'], $endpoint, $p256, $auth, mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 200), date('Y-m-d H:i:s')]);
    admin_log('Notifications activées sur un appareil');
    exit(json_encode(['ok' => true]));
}
if ($do === 'unsubscribe') {
    db()->prepare('DELETE FROM push_subs WHERE endpoint = ? AND admin_id = ?')->execute([$endpoint, $admin['id']]);
    exit(json_encode(['ok' => true]));
}
if ($do === 'test') {
    $st = db()->prepare('SELECT * FROM push_subs WHERE admin_id = ?');
    $st->execute([$admin['id']]);
    $n = 0;
    foreach ($st->fetchAll() as $s) {
        $code = push_send($s, ['title' => 'Notifications activées ✅', 'body' => 'Vous serez prévenu à chaque nouvelle réservation.', 'url' => 'index.php']);
        $n += $code >= 200 && $code < 300 ? 1 : 0;
    }
    exit(json_encode(['sent' => $n]));
}
http_response_code(400);
echo '{}';
