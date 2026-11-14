<?php
/** Facture et avoir : rendu HTML d'un document (page facture.php et impression groupée de la comptabilité). */

function invoice_css(): string
{
    return <<<'CSS'
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
@media(max-width:640px){body{padding:8px;font-size:13px}.doc{padding:22px 16px}.head{flex-direction:column;gap:14px}.head>div:last-child{text-align:left!important}h1{font-size:22px}.tot{width:100%}td,th{padding:8px 4px}}
@media print{body{background:#fff;padding:0}.doc{box-shadow:none;padding:0}.print{display:none}}
.doc+.doc{margin-top:24px}
@media print{.doc{page-break-after:always;break-after:page}.doc:last-of-type{page-break-after:auto;break-after:auto}.doc+.doc{margin-top:0}}
CSS;
}

/** Document complet (div.doc) d'une facture ou d'un avoir. */
function invoice_doc(array $b, bool $avoir): string
{
    $rate = (float)bk_num('bk_vat_rate', 20);
    $number = $avoir ? $b['credit_number'] : $b['invoice_number'];
    $date = bk_dt($avoir ? ($b['cancelled_at'] ?? $b['paid_at']) : $b['paid_at']);
    $giftBack = (int)$b['gift_restored'] ? (float)$b['gift_amount'] : 0.0;
    $refundTtc = round((float)$b['refunded_amount'] + $giftBack, 2);
    $refundHt = round($refundTtc / (1 + $rate / 100), 2);
    ob_start();
    ?>
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
<?php if ((float)$b['promo_ht'] > 0): ?>
    <tr><td>Code promo <?= e($b['promo_code']) ?></td><td class="n">1</td><td class="n">− <?= money2((float)$b['promo_ht']) ?></td><td class="n">− <?= money2((float)$b['promo_ht']) ?></td></tr>
<?php endif; ?>
<?php if ((float)$b['discount_ht'] > 0): ?>
    <tr><td>Remise association (<?= e(rating_label((string)bk_num('bk_asso_discount'))) ?> %)</td><td class="n">1</td><td class="n">− <?= money2((float)$b['discount_ht']) ?></td><td class="n">− <?= money2((float)$b['discount_ht']) ?></td></tr>
<?php endif; ?>
  </table>
  <table class="tot">
    <tr><td>Total HT</td><td class="n"><?= money2((float)$b['total_ht']) ?></td></tr>
    <tr><td>TVA <?= e(rating_label((string)$rate)) ?> %</td><td class="n"><?= money2((float)$b['vat']) ?></td></tr>
    <tr class="big"><td>Total TTC</td><td class="n"><?= money2((float)$b['total_ttc']) ?></td></tr>
  </table>
<?php if (bk_is_acompte($b)): $full = !empty($b['balance_paid_at']); ?>
  <table class="tot" style="margin-top:0">
<?php if ((float)$b['gift_amount'] > 0): ?>
    <tr><td>Réglé par bon cadeau <?= e($b['gift_code']) ?></td><td class="n">− <?= money2((float)$b['gift_amount']) ?></td></tr>
<?php endif; ?>
    <tr><td>Acompte réglé le <?= e(bk_dt($b['paid_at'])->format('d/m/Y')) ?> (<?= e(provider_label($b['provider'])) ?>)</td><td class="n">− <?= money2((float)$b['acompte_amount']) ?></td></tr>
<?php if ($full): ?>
    <tr><td>Solde réglé le <?= e(bk_dt($b['balance_paid_at'])->format('d/m/Y')) ?> (<?= e(balance_method_label($b['balance_method'])) ?>)</td><td class="n">− <?= money2(bk_balance_amount($b)) ?></td></tr>
<?php endif; ?>
    <tr class="big"><td>Reste à payer</td><td class="n"><?= money2(bk_balance($b)) ?></td></tr>
  </table>
<?php if ($full): ?>
  <span class="paid">✓ Payée : acompte le <?= e(bk_dt($b['paid_at'])->format('d/m/Y')) ?>, solde le <?= e(bk_dt($b['balance_paid_at'])->format('d/m/Y')) ?></span>
<?php else: ?>
  <span class="paid" style="background:#fff3dc;color:#8a5a00">Acompte reçu — solde de <?= money2(bk_balance($b)) ?> à régler avant le <?= e(bk_dt($b['balance_due_at'])->format('d/m/Y')) ?></span>
<?php endif; ?>
<?php elseif ((float)$b['gift_amount'] > 0): ?>
  <table class="tot" style="margin-top:0">
    <tr><td>Réglé par bon cadeau <?= e($b['gift_code']) ?></td><td class="n">− <?= money2((float)$b['gift_amount']) ?></td></tr>
<?php if (bk_due($b) > 0): ?>
    <tr><td><strong>Réglé par <?= e(provider_label($b['provider'])) ?></strong></td><td class="n"><strong><?= money2(bk_due($b)) ?></strong></td></tr>
<?php endif; ?>
  </table>
<?php endif; ?>
<?php if (!bk_is_acompte($b)): ?>
  <span class="paid">✓ Payée le <?= e(bk_dt($b['paid_at'])->format('d/m/Y')) ?> par <?= e((float)$b['gift_amount'] > 0 && $b['provider'] !== 'giftcard' ? 'bon cadeau et ' . provider_label($b['provider']) : provider_label($b['provider'])) ?></span>
<?php endif; ?>
<?php if (!empty($b['moved_at'])): ?>
  <p class="muted" style="margin-top:14px">Date de la location modifiée d’un commun accord le <?= e(bk_dt($b['moved_at'])->format('d/m/Y')) ?> : <strong><?= e(bk_slot_label($b)) ?></strong> (au lieu de : <?= e($b['moved_from']) ?>). Montant inchangé.</p>
<?php endif; ?>
<?php else: ?>
  <table>
    <tr><th>Désignation</th><th class="n">Total HT</th></tr>
    <tr><td>Annulation de la réservation <?= e($b['ref']) ?><br><span class="muted"><?= e(bk_slot_label($b)) ?>
      <?= (float)$b['refunded_amount'] > 0 ? '<br>Remboursé par ' . e(provider_label($b['provider'])) . ' : ' . money2((float)$b['refunded_amount']) . ' TTC' : '' ?>
      <?= $giftBack > 0 ? '<br>Recrédité sur le bon cadeau ' . e($b['gift_code']) . ' : ' . money2($giftBack) . ' TTC' : '' ?></span></td><td class="n">− <?= money2($refundHt) ?></td></tr>
  </table>
  <table class="tot">
    <tr><td>Total HT</td><td class="n">− <?= money2($refundHt) ?></td></tr>
    <tr><td>TVA <?= e(rating_label((string)$rate)) ?> %</td><td class="n">− <?= money2(round($refundTtc - $refundHt, 2)) ?></td></tr>
    <tr class="big"><td>Total TTC remboursé</td><td class="n">− <?= money2($refundTtc) ?></td></tr>
  </table>
<?php endif; ?>
  <div class="foot"><?= nl2br(e($avoir || bk_balance($b) <= 0 ? s('bk_invoice_footer') : trim(str_replace(['Facture acquittée.', 'Facture acquittée'], 'Facture partiellement réglée (acompte reçu, solde à régler).', s('bk_invoice_footer'))))) ?></div>
</div>
<?php
    return (string)ob_get_clean();
}
