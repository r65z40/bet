<?php
/**
 * Webhook Stripe : confirme la réservation même si le client ferme la page avant le retour sur le site.
 * À déclarer dans Stripe › Développeurs › Webhooks (événement checkout.session.completed).
 */
require __DIR__ . '/includes/bootstrap.php';

$payload = (string)file_get_contents('php://input');
if (!stripe_verify_signature($payload, (string)($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? ''), s('bk_stripe_webhook'))) {
    http_response_code(400);
    exit('Signature invalide');
}
$event = json_decode($payload, true);
$type = $event['type'] ?? '';
if (in_array($type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
    $sess = $event['data']['object'] ?? [];
    $b = bk_get((int)($sess['metadata']['booking_id'] ?? $sess['client_reference_id'] ?? 0));
    if ($b && ($sess['payment_status'] ?? '') === 'paid') {
        try {
            // On relit la session chez Stripe plutôt que de se fier au contenu reçu
            stripe_check_session($b, (string)$sess['id']);
        } catch (Throwable $e) {
            bk_log((int)$b['id'], 'Webhook Stripe : ' . $e->getMessage());
            http_response_code(500); // Stripe réessaiera
            exit;
        }
    }
}
http_response_code(200);
echo 'ok';
