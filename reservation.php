<?php
/** Tunnel de réservation en ligne : formule, date, événement & options, coordonnées, paiement. */
require __DIR__ . '/includes/bootstrap.php';
track_visit('reservation');
$maintenance = s('resa_maintenance') === '1';
$providers = payment_providers();

if ($maintenance) {
    public_header(s('resa_meta_title'), s('resa_lead'), false);
    ?>
<section class="page-hero"><div class="wrap"><div class="eyebrow">Réservation en ligne</div><h1><?= e(s('resa_title')) ?></h1></div></section>
<section class="booking"><div class="wrap"><div class="card" role="status" style="max-width:820px;margin:0 auto;text-align:center;padding:50px 30px">
  <div style="font-size:2.2rem">🛠</div>
  <h2 style="font-size:1.9rem;margin:10px 0 14px"><?= e(s('resa_maint_title')) ?></h2>
  <p class="muted" style="max-width:620px;margin:0 auto;font-size:1.05rem"><?= rich(s('resa_maint_message')) ?></p>
<?php if (s('resa_maint_contact') !== '0'): ?>
  <p style="margin-top:26px"><a class="btn btn-gold" href="<?= e(mailto()) ?>" data-track="email">✉ Nous contacter</a> <a class="btn btn-light" href="./">← Retour au site</a></p>
<?php endif; ?>
</div></div></section>
<?php
    public_footer('reservation');
    exit;
}

// Données pour le JavaScript du tunnel
$minH = bk_num('bk_min_hours', 2);
$dayPricesTtc = [];
$hourFrom = PHP_FLOAT_MAX;
$ref = bk_now()->modify('monday next week');
for ($i = 1; $i <= 7; $i++) {
    $dayPricesTtc[$i] = ttc(bk_day_price($i));
    $hourFrom = min($hourFrom, ttc(bk_rental_price('hour', $ref->modify('+' . ($i - 1) . ' days'), $minH)));
}
[$dayIn, $dayOut] = bk_day_slot(bk_now());
$config = [
    'dayStart' => $dayOut->format('H\hi'),
    'dayArrive' => $dayIn->format('H\hi'),
    'maxDays' => bk_max_days(),
    'dayDiscounts' => [2 => bk_multiday_discount(2), 3 => bk_multiday_discount(3)],
    'dayPrices' => $dayPricesTtc,
    'minHours' => $minH,
    'step' => max(15, (int)s('bk_hour_step') ?: 60),
    'maxGuests' => (int)bk_num('bk_max_guests', 120),
    'maxMonths' => (int)bk_num('bk_max_months', 18),
    'today' => bk_now()->format('Y-m'),
    'options' => array_map(fn($o) => ['id' => (int)$o['id'], 'unit' => $o['unit'], 'ttc' => ttc((float)$o['price'])], bk_options()),
];
// Réservation à partir d'un devis : formulaire prérempli (s'il est encore valable et pas déjà réservé)
$devis = isset($_GET['devis']) ? devis_find((string)$_GET['devis'], (string)($_GET['t'] ?? '')) : null;
if ($devis && !devis_expired($devis) && !(($devis['booking_id'] && (bk_get((int)$devis['booking_id'])['status'] ?? '') === 'confirmed'))) {
    $config['prefill'] = devis_prefill($devis);
}
public_header(s('resa_meta_title'), s('resa_lead'), true);
?>
<section class="page-hero"><div class="wrap"><div class="eyebrow">Réservation en ligne</div><h1><?= e(s('resa_title')) ?></h1><p><?= rich(s('resa_lead')) ?></p></div></section>
<?php if (!empty($config['prefill'])): ?>
<div class="wrap"><p class="notice" style="margin:18px auto 0"><span>📄</span><span><strong>Devis <?= e($devis['number']) ?></strong> : tout est rempli d’après votre devis. Vérifiez, cochez les conditions et réglez pour bloquer la date.</span></p></div>
<?php elseif (isset($_GET['devis'])): ?>
<div class="wrap"><p class="notice" style="margin:18px auto 0"><span>📄</span><span>Ce devis n’est plus valable (expiré ou déjà réservé) : vous pouvez faire une nouvelle demande ci-dessous.</span></p></div>
<?php endif; ?>

<section class="booking"><div class="wrap">
<?php if (!$providers): ?>
  <div class="alert alert-err" style="max-width:820px;margin:0 auto 18px">Le paiement en ligne n’est pas encore configuré. Pour réserver, contactez-nous : <a href="<?= e(mailto()) ?>"><?= e(s('contact_email')) ?></a></div>
<?php endif; ?>
<form id="bk" class="bk-grid" novalidate>
  <div>
    <div class="notice" style="margin-bottom:18px"><span>ℹ️</span><div><strong><?= e(s('resa_notice_title')) ?></strong><br><?= rich(s('resa_notice')) ?></div></div>

    <section class="card step" id="step1">
      <div class="step-head"><span class="step-num">1</span><h2>Votre formule</h2></div>
      <div class="formulas">
        <button type="button" class="formula" data-mode="day" aria-pressed="false">
          <strong>Location à la journée</strong>
          <span><?= bk_max_days() >= 3 ? '24 h, 48 h ou 72 h' : (bk_max_days() === 2 ? '24 h ou 48 h' : '24 h') ?>, de <?= e($config['dayArrive']) ?> au lendemain <?= e($config['dayStart']) ?></span>
          <em>à partir de <?= money(min($dayPricesTtc)) ?> TTC les 24 h</em>
        </button>
        <button type="button" class="formula" data-mode="hour" aria-pressed="false">
          <strong>Location à l’heure</strong>
          <span>Entre <?= e(str_replace(':', 'h', s('bk_hour_open'))) ?> et <?= e(str_replace(':', 'h', s('bk_hour_close'))) ?>, <?= e(fr_hours($minH)) ?> minimum</span>
          <em>à partir de <?= money($hourFrom) ?> TTC les <?= e(fr_hours($minH)) ?></em>
        </button>
      </div>
    </section>

    <section class="card step locked" id="step2">
      <div class="step-head"><span class="step-num">2</span><h2>Votre date</h2></div>
      <div class="chips" id="daysPick" role="group" aria-label="Durée de la location" hidden>
        <?php for ($n = 1; $n <= bk_max_days(); $n++): ?>
          <button type="button" class="chip" data-days="<?= $n ?>" aria-pressed="<?= $n === 1 ? 'true' : 'false' ?>"><?= 24 * $n ?> h<?= $n > 1 ? ' (' . $n . ' jours)' : '' ?><?= bk_multiday_discount($n) > 0 ? ' · −' . e(rating_label((string)bk_multiday_discount($n))) . ' %' : '' ?></button>
        <?php endfor; ?>
      </div>
      <div class="cal-head">
        <button type="button" class="cal-nav" id="calPrev" aria-label="Mois précédent">‹</button>
        <strong id="calTitle" aria-live="polite"></strong>
        <button type="button" class="cal-nav" id="calNext" aria-label="Mois suivant">›</button>
      </div>
      <div class="cal" id="cal" role="grid" aria-label="Calendrier des disponibilités"></div>
      <div class="cal-legend"><span><i style="background:#eaf6ee;border:1px solid #c8e8d2"></i>Disponible</span><span id="legendPartial"><i style="background:#fff7e6;border:1px solid #f0dca8"></i>Partiellement disponible</span><span><i style="background:#f1f3f6"></i>Indisponible</span></div>
      <div class="slots" id="slots" hidden>
        <strong>Heure d’arrivée</strong>
        <div class="chips" id="startChips" role="group" aria-label="Heure d’arrivée"></div>
        <label class="f" style="max-width:260px"><span>Durée</span><select id="duration"></select></label>
      </div>
    </section>

    <section class="card step locked" id="step3">
      <div class="step-head"><span class="step-num">3</span><h2>Votre événement</h2></div>
      <div class="row2">
        <label class="f"><span>Type d’événement</span>
          <select name="event_type" id="event_type" required>
            <option value="">Choisir…</option>
            <?php foreach (bk_event_types() as $t): ?><option><?= e($t) ?></option><?php endforeach; ?>
          </select></label>
        <label class="f"><span>Nombre de personnes <i>(max. <?= (int)$config['maxGuests'] ?>)</i></span>
          <input type="number" name="guests" id="guests" min="1" max="<?= (int)$config['maxGuests'] ?>" inputmode="numeric" required></label>
      </div>
<?php $opts = bk_options(); if ($opts): ?>
      <strong style="display:block;margin:6px 0 10px;color:var(--navy)">Options</strong>
      <div class="opts">
        <?php foreach ($opts as $o): ?>
        <label class="opt">
          <input type="checkbox" name="options" value="<?= (int)$o['id'] ?>">
          <div><strong><?= e($o['label']) ?></strong><?php if ($o['description'] !== ''): ?><small><?= e($o['description']) ?></small><?php endif; ?></div>
          <b><?= money2(ttc((float)$o['price'])) ?><?= $o['unit'] === 'personne' ? ' / pers.' : '' ?></b>
        </label>
        <?php endforeach; ?>
      </div>
<?php endif; ?>
<?php if (bk_num('bk_asso_discount') > 0): ?>
      <label class="check" style="margin-top:16px"><input type="checkbox" name="asso" id="asso"> <span><strong>Je réserve pour une association à but non lucratif</strong> — remise de <?= e(rating_label((string)bk_num('bk_asso_discount'))) ?> %</span></label>
      <label class="f" id="assoWrap" hidden><span>Nom de l’association</span><input type="text" name="asso_name" maxlength="120"></label>
<?php endif; ?>
    </section>

    <section class="card step locked" id="step4">
      <div class="step-head"><span class="step-num">4</span><h2>Vos coordonnées</h2></div>
      <div class="row2">
        <label class="f"><span>Prénom</span><input type="text" name="first_name" autocomplete="given-name" maxlength="60" required></label>
        <label class="f"><span>Nom</span><input type="text" name="last_name" autocomplete="family-name" maxlength="60" required></label>
        <label class="f"><span>E-mail</span><input type="email" name="email" autocomplete="email" maxlength="120" required></label>
        <label class="f"><span>Téléphone</span><input type="tel" name="phone" autocomplete="tel" maxlength="30" required></label>
      </div>
      <label class="f"><span>Société <i>(facultatif, apparaîtra sur la facture)</i></span><input type="text" name="company" autocomplete="organization" maxlength="120"></label>
      <label class="f"><span>Adresse <i>(pour la facture)</i></span><input type="text" name="address" autocomplete="street-address" maxlength="200"></label>
      <div class="row3">
        <label class="f"><span>Code postal</span><input type="text" name="zip" autocomplete="postal-code" maxlength="12"></label>
        <label class="f"><span>Ville</span><input type="text" name="city" autocomplete="address-level2" maxlength="80"></label>
      </div>
      <label class="f"><span>Message <i>(facultatif)</i></span><textarea name="message" rows="3" maxlength="2000" placeholder="Horaires d’arrivée des prestataires, questions…"></textarea></label>
      <div class="hp" aria-hidden="true"><label>Site web <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
    </section>
  </div>

  <aside class="card summary step" id="summary" aria-live="polite">
    <div class="step-head"><span class="step-num">5</span><h2>Récapitulatif</h2></div>
    <div class="sum-slot" id="sumSlot">Choisissez une formule et une date.</div>
    <ul class="sum-lines" id="sumLines"></ul>
    <div class="promo-row">
      <label for="promoInput" class="muted" style="font-size:.86rem;font-weight:700">Code promo ou bon cadeau</label>
      <div style="display:flex;gap:8px;margin-top:4px"><input type="text" id="promoInput" maxlength="40" autocomplete="off" autocapitalize="characters" placeholder="Ex. RN6-XXXX-XXXX" style="text-transform:uppercase"><button type="button" class="btn btn-light" id="promoApply" style="padding:10px 14px">Appliquer</button></div>
      <div id="promoMsg" style="font-size:.86rem;margin-top:4px"></div>
    </div>
    <div class="sum-total"><span>Total TTC</span><strong id="sumTotal">—</strong></div>
    <div class="sum-vat" id="sumVat"></div>
    <div class="sum-total" id="dueRow" hidden style="margin-top:6px"><span>Reste à payer</span><strong id="sumDue" style="font-size:1.35rem">—</strong></div>
    <div class="pay-plan" id="payPlan" hidden></div>
    <div class="sum-total" id="payNow" hidden style="margin-top:6px"><span>À payer aujourd’hui</span><strong style="font-size:1.35rem">—</strong></div>
    <div id="errors"></div>
    <p class="notice" style="margin:14px 0 0;font-size:.86rem"><span>🔑</span><span><?= e(s('bk_deposit_text')) ?></span></p>
    <label class="check"><input type="checkbox" name="cgv" id="cgv"> <span>J’accepte les <a href="<?= e(u('conditions.php')) ?>" target="_blank">conditions de location</a><?= documents() ? ' et le <a href="' . e(u('conditions.php')) . '#documents" target="_blank">règlement intérieur</a>' : '' ?> et j’ai lu la <a href="<?= e(u('confidentialite.php')) ?>" target="_blank">politique de confidentialité</a>.</span></label>
    <div class="pay">
<?php foreach ($providers as $key => $label): ?>
      <button type="submit" class="btn <?= $key === 'paypal' ? 'btn-paypal' : 'btn-blue' ?>" data-provider="<?= e($key) ?>" disabled>
        <?= $key === 'paypal' ? 'Payer avec <strong style="font-style:italic">PayPal</strong>' : '🔒 Payer par carte bancaire' ?>
      </button>
<?php endforeach; ?>
    </div>
    <button type="button" class="btn btn-light" id="devisBtn" disabled style="width:100%;margin-top:10px">📄 Recevoir un devis (PDF)</button>
    <p class="muted" id="devisHelp" style="font-size:.82rem;margin:6px 0 0;text-align:center">Le devis est envoyé à votre adresse e-mail ; il ne bloque pas la date.</p>
    <p class="secure">Paiement sécurisé par <?= e(implode(' et ', array_map(fn($k) => $k === 'paypal' ? 'PayPal' : 'Stripe', array_keys($providers)))) ?>. <span id="secureNote">Le montant total est réglé à la réservation.</span></p>
  </aside>
</form>
</div></section>
<div class="mobile-bar" id="mobileBar" hidden><div><small class="muted" id="barLabel">Total TTC</small><br><strong id="barTotal">—</strong></div><a class="btn btn-gold" href="#summary">Récapitulatif ↓</a></div>
<script>window.BK = <?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="assets/booking.js?v=<?= @filemtime(ROOT . '/assets/booking.js') ?>" defer></script>
<?php public_footer('reservation');
