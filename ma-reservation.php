<?php
/** Espace client d'une réservation (lien secret envoyé par e-mail) : statut, récapitulatif, facture, agenda. */
require __DIR__ . '/includes/bootstrap.php';

$b = bk_find((string)($_GET['ref'] ?? ''), (string)($_GET['t'] ?? ''));
if (!$b) {
    http_response_code(404);
    public_header('Réservation introuvable', '', false);
    echo '<section class="page-hero"><div class="wrap"><h1>Réservation introuvable</h1><p>Vérifiez le lien reçu par e-mail ou contactez-nous.</p></div></section>';
    public_footer();
    exit;
}
if (isset($_GET['ics'])) {
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="reservation-' . $b['ref'] . '.ics"');
    echo bk_client_ics($b);
    exit;
}
header('Referrer-Policy: no-referrer'); // le lien contient un jeton secret
$pending = in_array($b['status'], ['pending', 'expired'], true);
[$statusLabel, $statusColor] = BOOKING_STATUSES[$b['status']] ?? ['—', 'gray'];
$providers = payment_providers();
public_header('Ma réservation ' . $b['ref'] . ' | ' . s('site_name'), '', false);
?>
<section class="page-hero"><div class="wrap">
  <div class="eyebrow">Réservation <?= e($b['ref']) ?></div>
  <h1><?= $b['status'] === 'confirmed' ? (isset($_GET['merci']) ? 'Merci, c’est réservé !' : 'Votre réservation') : ($b['status'] === 'cancelled' ? 'Réservation annulée' : 'Paiement non finalisé') ?></h1>
  <p><span class="status <?= e($statusColor) ?>"><?= e($statusLabel) ?></span></p>
</div></section>
<section><div class="wrap" style="max-width:820px">
<?php if (isset($_GET['merci']) && $b['status'] === 'confirmed'): ?>
  <div class="alert alert-ok">Votre paiement a bien été reçu. Un e-mail de confirmation vient de vous être envoyé à <?= e($b['email']) ?>.</div>
<?php elseif (isset($_GET['erreur'])): ?>
  <div class="alert alert-err">Le paiement n’a pas pu être confirmé. Si votre compte a été débité, ne réessayez pas : contactez-nous à <?= e(s('contact_email')) ?>.</div>
<?php elseif (isset($_GET['indispo'])): ?>
  <div class="alert alert-err">Désolé, ce créneau a été réservé entre-temps. <a href="reservation.php">Choisir une autre date</a>.</div>
<?php elseif (isset($_GET['annule']) && $pending): ?>
  <div class="alert alert-err">Paiement annulé. Votre créneau reste réservé quelques minutes : vous pouvez réessayer ci-dessous.</div>
<?php endif; ?>

  <div class="card">
    <h2><?= e(bk_slot_label($b)) ?></h2>
    <p class="muted" style="margin:4px 0 14px"><?= e($b['event_type']) ?> · <?= (int)$b['guests'] ?> personne(s) · <?= e($b['first_name'] . ' ' . $b['last_name']) ?></p>
    <ul class="sum-lines">
<?php foreach (json_decode($b['lines_json'], true) ?: [] as $l): ?>
      <li><span><?= e($l['label']) ?></span><span><?= money2(ttc((float)$l['total_ht'])) ?></span></li>
<?php endforeach; ?>
<?php if ((float)$b['discount_ht'] > 0): ?>
      <li><span>Remise association</span><span>− <?= money2(ttc((float)$b['discount_ht'])) ?></span></li>
<?php endif; ?>
<?php if ((float)$b['promo_ht'] > 0): ?>
      <li><span>Code promo <?= e($b['promo_code']) ?></span><span>− <?= money2(ttc((float)$b['promo_ht'])) ?></span></li>
<?php endif; ?>
    </ul>
    <div class="sum-total"><span>Total TTC</span><strong><?= money2((float)$b['total_ttc']) ?></strong></div>
    <div class="sum-vat">dont TVA <?= money2((float)$b['vat']) ?> — total HT <?= money2((float)$b['total_ht']) ?></div>
<?php if ((float)$b['gift_amount'] > 0): ?>
    <div class="sum-total" style="margin-top:8px;font-size:.95rem"><span>Réglé par bon cadeau <?= e($b['gift_code']) ?></span><span>− <?= money2((float)$b['gift_amount']) ?></span></div>
    <div class="sum-total"><span><?= $pending ? 'Reste à payer' : 'Payé en ligne' ?></span><strong style="font-size:1.3rem"><?= money2(bk_due($b)) ?></strong></div>
<?php endif; ?>
<?php if ((float)$b['refunded_amount'] > 0): ?>
    <p class="alert alert-ok" style="margin-top:14px">Remboursé : <?= money2((float)$b['refunded_amount']) ?></p>
<?php endif; ?>

<?php if ($b['status'] === 'confirmed'): ?>
    <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:22px">
      <a class="btn btn-gold" href="<?= e(bk_client_url($b, 'facture.php')) ?>" target="_blank">🧾 Ma facture</a>
      <a class="btn btn-light" href="<?= e(gcal_template_url($b)) ?>" target="_blank" rel="noopener">📅 Google Agenda</a>
      <a class="btn btn-light" href="<?= e(bk_client_url($b)) ?>&amp;ics=1">📅 Apple / Outlook (.ics)</a>
    </div>
<?php if (deposit_by_card() && bk_dt($b['end_at']) > bk_now()): ?>
    <div class="deposit" id="caution">
      <h3>🔑 Caution — <?= money2($b['deposit_status'] === 'empreinte' ? (float)$b['deposit_held'] : bk_deposit_amount($b)) ?></h3>
<?php if (isset($_GET['caution']) && $_GET['caution'] === 'ok'): ?>
      <div class="alert alert-ok">Merci, votre empreinte bancaire est enregistrée. Rien n’a été débité.</div>
<?php elseif (isset($_GET['caution'])): ?>
      <div class="alert alert-err">L’empreinte n’a pas pu être enregistrée. Réessayez ou contactez-nous.</div>
<?php endif; ?>
<?php if ($b['deposit_status'] === 'empreinte'): ?>
      <p>✅ Empreinte bancaire en place depuis le <?= e(bk_dt($b['deposit_at'])->format('d/m/Y')) ?>. Rien n’est débité ; elle sera libérée après l’état des lieux de sortie, sauf dégradation constatée.</p>
<?php elseif (in_array($b['deposit_status'], ['restituee', 'retenue', 'recue'], true)): ?>
      <p><?= e(DEPOSIT_STATUSES[$b['deposit_status']][0]) ?>.</p>
<?php elseif (deposit_can_hold($b)): ?>
      <p>La caution prend la forme d’une <strong>empreinte bancaire</strong> : la somme est bloquée sur votre carte, <strong>rien n’est débité</strong>. Elle est libérée après l’état des lieux de sortie, sauf dégradation constatée.</p>
      <form method="post" action="caution.php"><input type="hidden" name="ref" value="<?= e($b['ref']) ?>"><input type="hidden" name="t" value="<?= e($b['token']) ?>">
        <button class="btn btn-blue">🔒 Déposer la caution par carte</button></form>
<?php else: ?>
      <p><?= e(s('bk_deposit_text')) ?></p>
      <p class="muted">Vous pourrez déposer l’empreinte bancaire à partir du <strong><?= e(fr_date(deposit_opens_at($b))) ?></strong> (un e-mail vous le rappellera), ou sur place à votre arrivée. Une empreinte n’est valable que quelques jours, c’est pourquoi elle n’est pas demandée plus tôt.</p>
<?php endif; ?>
    </div>
<?php else: ?>
    <p class="notice" style="margin-top:20px"><span>🔑</span><span><?= e(s('bk_deposit_text')) ?></span></p>
<?php endif; ?>
    <p class="muted" style="font-size:.92rem">Adresse : <?= e(s('address')) ?><?php if (s('maps_url')): ?> — <a href="<?= e(s('maps_url')) ?>" target="_blank" rel="noopener">itinéraire</a><?php endif; ?>.
      Pour modifier ou annuler, écrivez-nous à <a href="<?= e(mailto()) ?>"><?= e(s('contact_email')) ?></a> (voir les <a href="conditions.php">conditions d’annulation</a>).</p>
<?php elseif ($b['status'] === 'cancelled' && $b['credit_number'] !== ''): ?>
    <p style="margin-top:18px"><a class="btn btn-light" href="<?= e(bk_client_url($b, 'facture.php')) ?>" target="_blank">🧾 Facture</a> <a class="btn btn-light" href="<?= e(bk_client_url($b, 'facture.php')) ?>&amp;avoir=1" target="_blank">🧾 Avoir (remboursement)</a></p>
<?php elseif ($pending && $providers): ?>
    <form method="post" action="paiement.php" class="pay" style="margin-top:22px">
      <input type="hidden" name="ref" value="<?= e($b['ref']) ?>"><input type="hidden" name="t" value="<?= e($b['token']) ?>">
      <?php foreach ($providers as $k => $label): ?>
      <button class="btn <?= $k === 'paypal' ? 'btn-paypal' : 'btn-blue' ?>" name="provider" value="<?= e($k) ?>"><?= $k === 'paypal' ? 'Payer avec PayPal' : '🔒 Payer par carte bancaire' ?> — <?= money2(bk_due($b)) ?></button>
      <?php endforeach; ?>
    </form>
    <p class="muted" style="font-size:.88rem;margin-top:12px">Sans paiement, la réservation n’est pas prise en compte et le créneau est libéré automatiquement.</p>
<?php endif; ?>
  </div>
</div></section>
<?php public_footer('ma-reservation');
