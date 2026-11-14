<?php
/** Devis établi en ligne par le client : affichage, impression (PDF) et bouton « Réserver ce devis ». */
require __DIR__ . '/includes/bootstrap.php';

header('X-Robots-Tag: noindex');
$d = devis_find((string)($_GET['n'] ?? ''), (string)($_GET['t'] ?? ''));
if (!$d) {
    http_response_code(404);
    exit('Devis introuvable.');
}
$booked = !empty($d['booking_id']) && (bk_get((int)$d['booking_id'])['status'] ?? '') === 'confirmed';
$expired = devis_expired($d);
?><!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Devis <?= e($d['number']) ?> — <?= e(s('site_name')) ?></title>
<style><?= invoice_css() ?>
.bar{max-width:800px;margin:0 auto 14px;display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end;align-items:center}
.bar a,.bar button{padding:11px 16px;border-radius:8px;border:0;font-weight:bold;cursor:pointer;text-decoration:none;font-size:14px;font-family:inherit}
.bar .go{background:linear-gradient(135deg,#f0cf72,#d8a83f);color:#152131}.bar .pr{background:#071c35;color:#fff}.bar .muted{margin-right:auto}
@media print{.bar{display:none}}
</style></head>
<body>
<div class="bar">
  <span class="muted"><?= $booked ? '✓ Ce devis a été réservé.' : ($expired ? 'Ce devis a expiré le ' . e(bk_dt($d['valid_until'])->format('d/m/Y')) . ' : refaites une demande pour connaître le tarif actuel.' : 'La date n’est pas bloquée : réservez pour la garantir.') ?></span>
  <button class="pr" onclick="window.print()">🖨 Imprimer / PDF</button>
<?php if (!$booked && !$expired): ?>
  <a class="go" href="<?= e(u('reservation.php') . '?devis=' . rawurlencode($d['number']) . '&t=' . $d['token']) ?>">Réserver ce devis →</a>
<?php elseif ($expired && !$booked): ?>
  <a class="go" href="<?= e(u('reservation.php')) ?>">Faire un nouveau devis →</a>
<?php endif; ?>
</div>
<?= devis_doc($d) ?>
</body></html>
