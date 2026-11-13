<?php
/** Relance du paiement d'une réservation non payée (depuis « Ma réservation »). */
require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}
$b = bk_find((string)($_POST['ref'] ?? ''), (string)($_POST['t'] ?? ''));
$provider = (string)($_POST['provider'] ?? '');
if (!$b || !isset(payment_providers()[$provider])) {
    http_response_code(404);
    exit('Réservation introuvable.');
}
if (!in_array($b['status'], ['pending', 'expired'], true)) {
    header('Location: ' . bk_client_url($b));
    exit;
}
// Le créneau n'est plus réservé pour ce client : on vérifie qu'il est toujours libre et on le bloque à nouveau
$expired = $b['status'] === 'expired' || $b['hold_until'] === null || $b['hold_until'] < bk_now()->format(BK_FMT);
if ($expired) {
    gcal_sync_if_needed(true); // un événement ajouté à l'instant dans l'agenda Google compte
}
$pdo = db();
$pdo->exec('BEGIN IMMEDIATE');
try {
    $err = $expired ? bk_slot_error($b['mode'], bk_dt($b['start_at']), bk_dt($b['end_at']), (int)$b['id']) : null;
    if (!$err) {
        $pdo->prepare("UPDATE bookings SET status = 'pending', hold_until = ? WHERE id = ?")
            ->execute([bk_now()->modify('+35 minutes')->format(BK_FMT), $b['id']]);
    }
    $pdo->exec('COMMIT');
} catch (Throwable $e) {
    try { $pdo->exec('ROLLBACK'); } catch (Throwable) {}
    $err = 'indisponible';
}
if ($err) {
    header('Location: ' . bk_client_url($b) . '&indispo=1');
    exit;
}
$b = bk_get((int)$b['id']);
try {
    $url = $provider === 'paypal' ? paypal_checkout($b) : stripe_checkout($b);
    header('Location: ' . $url);
} catch (Throwable $e) {
    bk_log((int)$b['id'], 'Échec de relance du paiement : ' . $e->getMessage());
    header('Location: ' . bk_client_url($b) . '&erreur=1');
}
