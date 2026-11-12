<?php
/** Réglages du système de réservation : horaires, tarifs, annulation, paiement, e-mails, société, agenda. */
require __DIR__ . '/_init.php';
require_admin();

$sections = BOOKING_SETTINGS;
$current = (string)($_GET['s'] ?? 'regles');
if (!isset($sections[$current])) {
    $current = 'regles';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $do = (string)($_POST['do'] ?? 'save');
    if ($do === 'save') {
        $errors = save_settings_fields($sections[$current]['fields']);
        foreach ($errors as $err) {
            flash($err, 'err');
        }
        if (!$errors) {
            flash('Réglages enregistrés.');
        }
        if ($current === 'agenda') {
            gcal_sync_if_needed(true);
            flash(s('_gcal_error') === '' ? 'Agenda Google relu : ' . (int)s('_gcal_count') . ' événement(s) à venir.' : 'Agenda Google : ' . s('_gcal_error'), s('_gcal_error') === '' ? 'ok' : 'err');
        }
    } elseif ($do === 'sync') {
        gcal_sync_if_needed(true);
        flash(s('_gcal_error') === '' ? 'Agenda Google relu : ' . (int)s('_gcal_count') . ' événement(s) à venir.' : 'Agenda Google : ' . s('_gcal_error'), s('_gcal_error') === '' ? 'ok' : 'err');
    } elseif ($do === 'newtoken') {
        save_setting('bk_ics_token', bin2hex(random_bytes(16)));
        flash('Nouveau lien d’abonnement créé : l’ancien ne fonctionne plus.');
    } elseif ($do === 'testmail') {
        $to = s('bk_notify_email') ?: s('contact_email');
        $ok = send_mail($to, 'Test d’envoi — ' . s('site_name'), mail_layout('Test réussi', '<p>Les e-mails de réservation partiront correctement.</p>'));
        flash($ok ? 'E-mail de test envoyé à ' . $to . '. Vérifiez votre boîte (et les spams).' : 'Échec : ' . s('_mail_last_error'), $ok ? 'ok' : 'err');
    } elseif ($do === 'testpay') {
        try {
            if (s('bk_stripe_secret') !== '') {
                stripe_request('GET', '/v1/balance');
                flash('Stripe : connexion réussie' . (str_starts_with(s('bk_stripe_secret'), 'sk_test_') ? ' (mode TEST).' : ' (mode PRODUCTION).'));
            }
        } catch (Throwable $e) {
            flash($e->getMessage(), 'err');
        }
        try {
            if (s('bk_paypal_client') !== '' && s('bk_paypal_secret') !== '') {
                paypal_token();
                flash('PayPal : connexion réussie (' . (s('bk_paypal_mode') === 'live' ? 'PRODUCTION' : 'TEST / sandbox') . ').');
            }
        } catch (Throwable $e) {
            flash($e->getMessage(), 'err');
        }
    }
    redirect('reglages.php?s=' . urlencode($current));
}

admin_header('Réglages des réservations', $current === 'tarifs' ? 'tarifs' : 'resa');
?>
<h1>Réglages des réservations</h1>
<p class="intro">Tout ce qui concerne la réservation en ligne. Les prix se saisissent <strong>HT</strong> ; le site affiche et encaisse le <strong>TTC</strong>.</p>

<div class="layout">
  <aside class="side">
    <?php foreach ($sections as $key => $sec): ?>
      <a href="?s=<?= e($key) ?>" class="<?= $key === $current ? 'on' : '' ?>"><?= e($sec['title']) ?></a>
    <?php endforeach; ?>
    <a href="options.php">Options payantes</a>
    <h3>Liens</h3>
    <a href="reservations.php">← Réservations</a>
    <a href="../reservation.php" target="_blank">Page de réservation ↗</a>
  </aside>
  <div>
<?php if ($current === 'paiement'): ?>
    <div class="hint">
      <strong>Adresse du webhook Stripe</strong> (à copier dans Stripe › Développeurs › Webhooks) :<br><code><?= e(bk_url('stripe-webhook.php')) ?></code><br>
      Pour tester sans vrai paiement : clé <code>sk_test_…</code> chez Stripe, mode « Test (sandbox) » chez PayPal, puis carte de test Stripe <code>4242 4242 4242 4242</code>.
      <?php if (!payment_providers()): ?><br><strong style="color:#b42318">Aucun moyen de paiement actif : la réservation en ligne affiche un message invitant à vous contacter.</strong><?php endif; ?>
    </div>
<?php elseif ($current === 'emails'): ?>
    <?php if (s('_mail_last_error') !== ''): ?><div class="flash flash-err">Dernière erreur d’envoi : <?= e(s('_mail_last_error')) ?></div><?php endif; ?>
<?php elseif ($current === 'agenda'): ?>
    <div class="card">
      <h2>1. Votre agenda Google bloque les réservations</h2>
      <p class="muted">Ajoutez un événement dans Google Agenda (depuis l’iPhone, l’ordinateur…) : le créneau devient indisponible sur le site, temps mort compris. Lecture automatique toutes les 10 minutes environ, et juste avant chaque paiement.</p>
      <p>État : <?php if (s('bk_gcal_url') === ''): ?><span class="pill">non configuré</span>
        <?php elseif (s('_gcal_error') !== ''): ?><span class="pill" style="background:#fdecea;color:#b42318">erreur</span> <?= e(s('_gcal_error')) ?>
        <?php else: ?><span class="pill gold">actif</span> <?= (int)s('_gcal_count') ?> événement(s) à venir — dernière lecture <?= s('_gcal_last_ok') ? e(date('d/m/Y H:i', (int)s('_gcal_last_ok'))) : '—' ?><?php endif; ?></p>
      <form method="post"><?= csrf_field() ?><button class="btn light sm" name="do" value="sync">↻ Relire l’agenda maintenant</button></form>
    </div>
    <div class="card">
      <h2>2. Les réservations du site dans votre iPhone</h2>
      <p class="muted">Lien d’abonnement privé (ne le partagez pas) :</p>
      <p><code style="word-break:break-all"><?= e(bk_url('calendrier.php?k=' . s('bk_ics_token'))) ?></code></p>
      <p class="muted"><strong>iPhone</strong> : Réglages › Calendrier › Comptes › Ajouter un compte › Autre › « Ajouter un calendrier avec abonnement » › collez le lien (mise à jour toutes les 15 min).<br>
        <strong>Google Agenda</strong> (ordinateur) : Autres agendas › + › « À partir de l’URL » (Google ne le met à jour que quelques fois par jour).<br>
        Ne l’ajoutez pas dans le <em>même</em> agenda que celui saisi ci-dessous : il s’agit d’un agenda séparé.</p>
      <form method="post"><?= csrf_field() ?><button class="btn danger sm" name="do" value="newtoken" onclick="return confirm('Créer un nouveau lien ? L’ancien abonnement cessera de fonctionner.')">Changer le lien</button></form>
    </div>
<?php endif; ?>

    <form method="post" class="card">
      <?= csrf_field() ?>
      <h2><?= e($sections[$current]['title']) ?></h2>
      <?php if ($current === 'tarifs'): ?>
        <div class="grid g4" style="margin-bottom:8px">
          <?php for ($i = 1; $i <= 7; $i++): $f = $sections['tarifs']['fields']['bk_price_' . $i]; ?>
            <?= field('bk_price_' . $i, explode(' — ', $f['label'])[0] . ' (HT)', s('bk_price_' . $i), 'number', 'TTC : ' . money2(ttc(bk_day_price($i)))) ?>
          <?php endfor; ?>
        </div>
        <?php foreach ($sections['tarifs']['fields'] as $key => $f): if (str_starts_with($key, 'bk_price_')) continue; ?>
          <?= field($key, $f['label'], s($key), $f['type'] ?? 'text', $f['help'] ?? '', $f['options'] ?? []) ?>
        <?php endforeach; ?>
      <?php else: ?>
        <?php foreach ($sections[$current]['fields'] as $key => $f): ?>
          <?= field($key, $f['label'], s($key), $f['type'] ?? 'text', $f['help'] ?? '', $f['options'] ?? []) ?>
        <?php endforeach; ?>
      <?php endif; ?>
      <div class="sticky-save actions">
        <button class="btn gold" name="do" value="save">💾 Enregistrer</button>
        <?php if ($current === 'emails'): ?><button class="btn light" name="do" value="testmail">✉ Envoyer un e-mail de test</button><?php endif; ?>
        <?php if ($current === 'paiement'): ?><button class="btn light" name="do" value="testpay">⚡ Tester la connexion</button><?php endif; ?>
      </div>
    </form>

<?php if ($current === 'tarifs'): ?>
    <div class="card">
      <h2>Simulation des prix à l’heure (TTC)</h2>
      <p class="muted">Avec les réglages enregistrés. Vérifiez qu’une durée plus longue n’est jamais moins chère qu’une durée plus courte.</p>
      <div style="overflow-x:auto"><table>
        <tr><th>Durée</th><?php foreach (['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'] as $d): ?><th class="num"><?= $d ?></th><?php endforeach; ?></tr>
        <?php $ref = bk_now()->modify('monday next week');
        $maxH = (strtotime('2000-01-01 ' . s('bk_hour_close')) - strtotime('2000-01-01 ' . s('bk_hour_open'))) / 3600;
        $prev = [];
        for ($h = bk_num('bk_min_hours', 2); $h <= $maxH; $h++): ?>
          <tr><td><?= e(fr_hours($h)) ?> <small class="muted">×<?= e(rating_label((string)bk_coef($h))) ?></small></td>
          <?php for ($i = 1; $i <= 7; $i++): $p = ttc(bk_rental_price('hour', $ref->modify('+' . ($i - 1) . ' days'), $h)); $drop = isset($prev[$i]) && $p < $prev[$i]; $prev[$i] = $p; ?>
            <td class="num" <?= $drop ? 'style="color:#b42318;font-weight:700" title="Moins cher que la durée précédente"' : '' ?>><?= money2($p) ?></td>
          <?php endfor; ?></tr>
        <?php endfor; ?>
        <tr><td><strong>24 h</strong></td><?php for ($i = 1; $i <= 7; $i++): ?><td class="num"><strong><?= money2(ttc(bk_day_price($i))) ?></strong></td><?php endfor; ?></tr>
      </table></div>
    </div>
<?php endif; ?>
  </div>
</div>
<?php admin_footer();
