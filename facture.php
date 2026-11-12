<?php
/** Facture (ou avoir) imprimable / PDF. Accès : lien secret du client, ou administrateur connecté. */
if (!empty($_GET['id'])) {
    define('ADMIN_AREA', true);
}
require __DIR__ . '/includes/bootstrap.php';

$b = !empty($_GET['id']) && current_admin() ? bk_get((int)$_GET['id']) : bk_find((string)($_GET['ref'] ?? ''), (string)($_GET['t'] ?? ''));
$avoir = isset($_GET['avoir']);
if (!$b || $b['invoice_number'] === '' || ($avoir && $b['credit_number'] === '')) {
    http_response_code(404);
    exit('Document indisponible.');
}
header('Referrer-Policy: no-referrer');
$rate = (float)bk_num('bk_vat_rate', 20);
$number = $avoir ? $b['credit_number'] : $b['invoice_number'];
$date = bk_dt($avoir ? ($b['cancelled_at'] ?? $b['paid_at']) : $b['paid_at']);
$refundTtc = (float)$b['refunded_amount'];
$refundHt = round($refundTtc / (1 + $rate / 100), 2);
?><!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title><?= $avoir ? 'Avoir' : 'Facture' ?> <?= e($number) ?> — <?= e(s('bk_company_name')) ?></title>
<style>
body{font-family:Arial,Helvetica,sans-serif;color:#152131;background:#eef1f4;margin:0;padding:24px;font-size:14px}
.doc{max-width:800px;margin:auto;background:#fff;padding:44px 48px;box-shadow:0 8px 30px rgba(0,0,0,.08)}
.head{display:flex;justify-content:space-between;gap:30px;margin-bottom:34px}
h1{font-family:Georgia,serif;color:#071c35;margin:0 0 6px;font-size:28px}
.muted{color:#617083}
table{width:100%;border-collapse:collapse;margin:24px 0}
th{text-align:left;font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:#617083;border-bottom:2px solid #071c35;padding:8px 6px}
td{padding:10px 6px;border-bottom:1px solid #e3e7ec;vertical-align:top}
td.n,th.n{text-align:right;white-space:nowrap}
.tot{margin-left:auto;width:300px}.tot td{border:0;padding:5px 6px}.tot tr.big td{font-size:17px;font-weight:bold;border-top:2px solid #071c35;padding-top:10px}
.paid{display:inline-block;margin-top:10px;padding:6px 12px;border-radius:999px;background:#e7f6ec;color:#1d7a46;font-weight:bold}
.foot{margin-top:36px;font-size:11px;color:#617083;border-top:1px solid #e3e7ec;padding-top:12px}
.print{max-width:800px;margin:0 auto 14px;text-align:right}.print button{padding:10px 16px;border-radius:8px;border:0;background:#071c35;color:#fff;font-weight:bold;cursor:pointer}
@media print{body{background:#fff;padding:0}.doc{box-shadow:none;padding:0}.print{display:none}}
</style></head><body>
<div class="print"><button onclick="window.print()">Imprimer / Enregistrer en PDF</button></div>
<div class="doc">
  <div class="head">
    <div>
      <h1><?= e(s('bk_company_name')) ?></h1>
      <div class="muted"><?= nl2br(e(s('bk_company_address'))) ?><br>
      <?= s('bk_company_form') ? e(s('bk_company_form')) . '<br>' : '' ?>
      <?= s('bk_company_siren') ? 'SIREN : ' . e(s('bk_company_siren')) . '<br>' : '' ?>
      <?= s('bk_company_rcs') ? 'RCS ' . e(s('bk_company_rcs')) . '<br>' : '' ?>
      <?= s('bk_company_vat') ? 'TVA intracom. : ' . e(s('bk_company_vat')) . '<br>' : '' ?>
      <?= e(s('contact_email')) ?></div>
    </div>
    <div style="text-align:right">
      <h1><?= $avoir ? 'AVOIR' : 'FACTURE' ?></h1>
      <strong>N° <?= e($number) ?></strong><br>
      Date : <?= e($date->format('d/m/Y')) ?><br>
      <?php if ($avoir): ?>Sur facture n° <?= e($b['invoice_number']) ?><br><?php endif; ?>
      Réservation : <?= e($b['ref']) ?>
    </div>
  </div>
  <div style="background:#f4f6f8;padding:14px 16px;border-radius:8px;max-width:360px">
    <strong>Client</strong><br>
    <?= $b['company'] !== '' ? e($b['company']) . '<br>' : '' ?>
    <?= $b['is_asso'] && $b['asso_name'] !== '' ? e($b['asso_name']) . '<br>' : '' ?>
    <?= e($b['first_name'] . ' ' . $b['last_name']) ?><br>
    <?= $b['address'] !== '' ? e($b['address']) . '<br>' : '' ?>
    <?= e(trim($b['zip'] . ' ' . $b['city'])) ?><?= trim($b['zip'] . $b['city']) !== '' ? '<br>' : '' ?>
    <?= e($b['email']) ?>
  </div>
<?php if (!$avoir): ?>
  <table>
    <tr><th>Désignation</th><th class="n">Qté</th><th class="n">P.U. HT</th><th class="n">Total HT</th></tr>
<?php foreach (json_decode($b['lines_json'], true) ?: [] as $l): ?>
    <tr><td><?= e($l['label']) ?><?php if ($l['kind'] === 'rental'): ?><br><span class="muted"><?= e($b['event_type']) ?>, <?= (int)$b['guests'] ?> pers.</span><?php endif; ?></td>
      <td class="n"><?= (int)$l['qty'] ?></td><td class="n"><?= money2((float)$l['unit_ht']) ?></td><td class="n"><?= money2((float)$l['total_ht']) ?></td></tr>
<?php endforeach; ?>
<?php if ((float)$b['discount_ht'] > 0): ?>
    <tr><td>Remise association (<?= e(rating_label((string)bk_num('bk_asso_discount'))) ?> %)</td><td class="n">1</td><td class="n">− <?= money2((float)$b['discount_ht']) ?></td><td class="n">− <?= money2((float)$b['discount_ht']) ?></td></tr>
<?php endif; ?>
  </table>
  <table class="tot">
    <tr><td>Total HT</td><td class="n"><?= money2((float)$b['total_ht']) ?></td></tr>
    <tr><td>TVA <?= e(rating_label((string)$rate)) ?> %</td><td class="n"><?= money2((float)$b['vat']) ?></td></tr>
    <tr class="big"><td>Total TTC</td><td class="n"><?= money2((float)$b['total_ttc']) ?></td></tr>
  </table>
  <span class="paid">✓ Payée le <?= e(bk_dt($b['paid_at'])->format('d/m/Y')) ?> par <?= $b['provider'] === 'paypal' ? 'PayPal' : 'carte bancaire' ?></span>
<?php else: ?>
  <table>
    <tr><th>Désignation</th><th class="n">Total HT</th></tr>
    <tr><td>Remboursement suite à l’annulation de la réservation <?= e($b['ref']) ?><br><span class="muted"><?= e(bk_slot_label($b)) ?></span></td><td class="n">− <?= money2($refundHt) ?></td></tr>
  </table>
  <table class="tot">
    <tr><td>Total HT</td><td class="n">− <?= money2($refundHt) ?></td></tr>
    <tr><td>TVA <?= e(rating_label((string)$rate)) ?> %</td><td class="n">− <?= money2(round($refundTtc - $refundHt, 2)) ?></td></tr>
    <tr class="big"><td>Total TTC remboursé</td><td class="n">− <?= money2($refundTtc) ?></td></tr>
  </table>
<?php endif; ?>
  <div class="foot"><?= nl2br(e(s('bk_invoice_footer'))) ?></div>
</div>
</body></html>
