<?php
/**
 * Caution par empreinte bancaire : envoi vers la page sécurisée Stripe (POST depuis la page client),
 * puis retour du client et vérification chez Stripe.
 */
require __DIR__ . '/includes/bootstrap.php';

$src = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$b = bk_find((string)($src['ref'] ?? ''), (string)($src['t'] ?? ''));
if (!$b) {
    http_response_code(404);
    exit('Réservation introuvable.');
}
header('Referrer-Policy: no-referrer');
$back = bk_client_url($b);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        header('Location: ' . deposit_checkout($b), true, 303);
    } catch (Throwable $e) {
        bk_log((int)$b['id'], 'Caution : impossible d’ouvrir le paiement (' . $e->getMessage() . ')');
        header('Location: ' . $back . '&caution=erreur#caution', true, 303);
    }
    exit;
}

// Retour depuis Stripe
$ok = false;
try {
    $ok = deposit_check_session($b, (string)($_GET['session_id'] ?? ''));
} catch (Throwable $e) {
    bk_log((int)$b['id'], 'Caution : erreur au retour (' . $e->getMessage() . ')');
}
header('Location: ' . $back . ($ok ? '&caution=ok' : '&caution=erreur') . '#caution', true, 303);
