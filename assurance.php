<?php
/** Dépôt de l'attestation d'assurance depuis l'espace client (lien secret de la réservation). */
require __DIR__ . '/includes/bootstrap.php';

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ./', true, 302);
    exit;
}
// Refuse les envois préparés sur un autre site (« null » = page d'origine sans référent : l'espace client masque son adresse,
// qui contient le jeton ; l'envoi reste protégé par ce jeton secret)
$from = (string)($_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? ''));
$from = $from === 'null' ? '' : $from;
if ($from !== '' && parse_url($from, PHP_URL_HOST) !== parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)) {
    http_response_code(403);
    exit;
}
$b = bk_find((string)($_POST['ref'] ?? ''), (string)($_POST['t'] ?? ''));
if (!$b || $b['status'] !== 'confirmed' || !insurance_online() || bk_dt($b['end_at']) < bk_now()) {
    http_response_code(404);
    exit('Réservation introuvable.');
}
$err = insurance_store($b, $_FILES['file'] ?? []);
header('Location: ' . bk_client_url($b) . '&assurance=' . ($err === null ? 'ok' : rawurlencode($err)) . '#assurance', true, 303);
