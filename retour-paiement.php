<?php
/** Retour du client après paiement (Stripe ou PayPal) : vérification auprès du prestataire puis confirmation. */
require __DIR__ . '/includes/bootstrap.php';

$b = bk_find((string)($_GET['ref'] ?? ''), (string)($_GET['t'] ?? ''));
if (!$b) {
    http_response_code(404);
    exit('Réservation introuvable.');
}
// Retour après le paiement du solde
if (!empty($_GET['solde'])) {
    $ok = bk_balance($b) <= 0;
    if (!$ok) {
        try {
            $ok = ($_GET['provider'] ?? '') === 'paypal'
                ? paypal_capture_balance($b, (string)($_GET['token'] ?? ''))
                : stripe_check_balance_session($b, (string)($_GET['session_id'] ?? ''));
        } catch (Throwable $e) {
            bk_log((int)$b['id'], 'Erreur au retour du paiement du solde : ' . $e->getMessage());
        }
    }
    header('Location: ' . bk_client_url($b) . '&solde=' . ($ok ? 'ok' : 'erreur') . '#paiement');
    exit;
}
$ok = $b['status'] === 'confirmed';
$error = '';
if (!$ok) {
    try {
        $ok = ($_GET['provider'] ?? '') === 'paypal'
            ? paypal_capture($b, (string)($_GET['token'] ?? ''))
            : stripe_check_session($b, (string)($_GET['session_id'] ?? ''));
    } catch (Throwable $e) {
        bk_log((int)$b['id'], 'Erreur au retour de paiement : ' . $e->getMessage());
        $error = '1';
    }
}
header('Location: ' . bk_client_url($b) . ($ok ? '&merci=1' : '&erreur=1'));
