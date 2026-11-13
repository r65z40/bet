<?php
/** Facture (ou avoir) imprimable / PDF. Accès : lien secret du client, ou administrateur connecté. */
if (!empty($_GET['id'])) {
    define('ADMIN_AREA', true);
}
require __DIR__ . '/includes/bootstrap.php';

$b = !empty($_GET['id']) && admin_can('reservations') ? bk_get((int)$_GET['id']) : bk_find((string)($_GET['ref'] ?? ''), (string)($_GET['t'] ?? ''));
$avoir = isset($_GET['avoir']);
if (!$b || $b['invoice_number'] === '' || ($avoir && $b['credit_number'] === '')) {
    http_response_code(404);
    exit('Document indisponible.');
}
header('Referrer-Policy: no-referrer');
$number = $avoir ? $b['credit_number'] : $b['invoice_number'];
?><!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title><?= $avoir ? 'Avoir' : 'Facture' ?> <?= e($number) ?> — <?= e(s('bk_company_name')) ?></title>
<style>
<?= invoice_css() ?>
</style></head><body>
<div class="print"><button onclick="window.print()">Imprimer / Enregistrer en PDF</button></div>
<?= invoice_doc($b, $avoir) ?>
</body></html>
