<?php
/** Réglages du système de réservation : horaires, tarifs, annulation, paiement, e-mails, société, agenda. */
require __DIR__ . '/_init.php';
$current = (string)($_GET['s'] ?? 'regles');
require_perm($current === 'tarifs' ? 'tarifs' : 'reglages');

$sections = BOOKING_SETTINGS;
const SENSITIVE_SECTIONS = ['paiement', 'emails', 'agenda', 'societe']; // détournement de paiements, d'e-mails ou de liens
$current = (string)($_GET['s'] ?? 'regles');
if (!isset($sections[$current])) {
    $current = 'regles';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $do = (string)($_POST['do'] ?? 'save');
    if ($do === 'save') {
        if (in_array($current, SENSITIVE_SECTIONS, true)) {
            require_reauth('reglages.php?s=' . urlencode($current));
        }
        $errors = save_settings_fields($sections[$current]['fields']);
        admin_log('Réglages modifiés : ' . $sections[$current]['title']);
        foreach ($errors as $err) {
            flash($err, 'err');
        }
        if (!$errors) {
            flash('Réglages enregistrés.');
        }
        if ($current === 'agenda') {
            if (s('bk_gcal_sa') !== '' && !gcal_service_account()) {
                flash('La clé collée n’est pas une clé de compte de service Google valide (fichier .json complet attendu).', 'err');
            }
            gcal_sync_if_needed(true);
            flash(s('_gcal_error') === '' ? 'Agenda Google relu : ' . (int)s('_gcal_count') . ' événement(s) à venir.' : 'Agenda Google : ' . s('_gcal_error'), s('_gcal_error') === '' ? 'ok' : 'err');
        }
    } elseif ($do === 'sync') {
        gcal_sync_if_needed(true);
        flash(s('_gcal_error') === '' ? 'Agenda Google relu : ' . (int)s('_gcal_count') . ' événement(s) à venir.' : 'Agenda Google : ' . s('_gcal_error'), s('_gcal_error') === '' ? 'ok' : 'err');
    } elseif ($do === 'gpush') {
        if (!gcal_write_enabled()) {
            flash('Renseignez d’abord l’ID de l’agenda et la clé du compte de service.', 'err');
        } else {
            [$ok, $fail] = gcal_full_sync();
            admin_log('Synchronisation complète vers Google Agenda');
            flash($fail ? 'Google Agenda : ' . s('_gcal_push_error') : 'Google Agenda est à jour (' . $ok . ' réservation(s) ou blocage(s) traité(s)).', $fail ? 'err' : 'ok');
        }
    } elseif ($do === 'cronnow') {
        $r = run_scheduled_tasks(true);
        admin_log('Tâches automatiques lancées manuellement');
        flash('Tâches exécutées : ' . (int)($r['rappels'] ?? 0) . ' rappel(s), ' . (int)($r['avis'] ?? 0) . ' demande(s) d’avis, ' . (int)($r['emails_groupes'] ?? 0) . ' e-mail(s) groupé(s), ' . (int)($r['cautions_demandees'] ?? 0) . ' demande(s) de caution, ' . (int)($r['cautions_liberees'] ?? 0) . ' caution(s) libérée(s).'
            . (!empty($r['erreurs']) ? ' Erreurs : ' . implode(' ; ', $r['erreurs']) : ''), empty($r['erreurs']) ? 'ok' : 'err');
    } elseif ($do === 'newtoken') {
        save_setting('bk_ics_token', bin2hex(random_bytes(16)));
        flash('Nouveau lien d’abonnement créé : l’ancien ne fonctionne plus.');
    } elseif ($do === 'testmail') {
        $to = notify_emails()[0] ?? s('contact_email');
        $ok = send_mail($to, 'Test d’envoi — ' . s('site_name'), mail_layout('Test réussi', mail_render_text("Bonjour,\n\nCet e-mail de test confirme que les messages de **{site}** partent correctement : confirmations de réservation, factures, rappels avant l’événement, demandes de caution et réponses à vos clients.\n\nSi vous le recevez dans votre boîte de réception, tout fonctionne. S’il arrive dans les indésirables, vérifiez dans l’espace OVH que les enregistrements SPF, DKIM et DMARC de votre domaine sont actifs, puis marquez ce message comme « non indésirable ».\n\nAdresse de la salle : {adresse}.\n\nBonne journée,\n{site}", [])));
        flash($ok ? 'E-mail de test envoyé à ' . $to . ' depuis ' . mail_from_address() . '. Vérifiez votre boîte (et les spams).' : 'Échec : ' . mail_last_error(), $ok ? 'ok' : 'err');
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

admin_header('Réglages des réservations', $current === 'tarifs' ? 'tarifs' : 'reglages');
?>
<h1><?= $current === 'tarifs' ? 'Tarifs & options' : 'Réglages des réservations' ?></h1>
<p class="intro"><?= $current === 'tarifs' ? 'Prix des journées saisis <strong>HT</strong> (TTC indiqué dessous) ; location à l’heure saisie <strong>TTC</strong>. Le site affiche et encaisse le TTC.' : 'Horaires, paiement, e-mails, agenda, factures et conditions de location.' ?></p>

<?php if ($current === 'tarifs'): ?>
<?= tarifs_tabs('prix') ?>
<div>
  <div>
<?php else: ?>
<div class="layout">
  <aside class="side">
    <?php foreach ($sections as $key => $sec): if ($key === 'tarifs') continue; ?>
      <a href="?s=<?= e($key) ?>" class="<?= $key === $current ? 'on' : '' ?>"><?= e($sec['title']) ?></a>
    <?php endforeach; ?>
      <a href="notifications.php">Notifications</a>
      <a href="sauvegardes.php">Sauvegardes</a>
  </aside>
  <div>
<?php endif; ?>
<?php if ($current === 'paiement'): ?>
    <div class="hint">
      <strong>Adresse du webhook Stripe</strong> (à copier dans Stripe › Développeurs › Webhooks) :<br><code><?= e(bk_url('stripe-webhook.php')) ?></code><br>
      Pour tester sans vrai paiement : clé <code>sk_test_…</code> chez Stripe, mode « Test (sandbox) » chez PayPal, puis carte de test Stripe <code>4242 4242 4242 4242</code>.
      <?php if (!payment_providers()): ?><br><strong style="color:#b42318">Aucun moyen de paiement actif : la réservation en ligne affiche un message invitant à vous contacter.</strong><?php endif; ?>
    </div>
<?php elseif ($current === 'caution'): ?>
    <div class="hint">
      <strong>Comment fonctionne l’empreinte bancaire ?</strong> Le client enregistre sa carte sur la page sécurisée de Stripe : le montant est <strong>bloqué</strong> sur sa carte, mais <strong>pas débité</strong>.
      Après l’état des lieux de sortie, depuis la fiche de la réservation, vous <strong>libérez</strong> l’empreinte ou vous <strong>encaissez</strong> tout ou partie du montant avec un motif ; sans action de votre part, elle est libérée automatiquement.
      Une empreinte ne dure que <strong>7 jours</strong> : elle est donc demandée peu avant l’arrivée (e-mail automatique), et le client peut aussi la faire à l’état des lieux d’entrée, depuis son téléphone.
      <?php if (s('bk_deposit_mode') !== 'cheque' && !isset(payment_providers()['stripe'])): ?><br><strong style="color:#b42318">Stripe n’est pas activé (Réglages › Paiement) : l’empreinte bancaire ne peut pas fonctionner (seul le chèque est proposé).</strong><?php endif; ?>
      <?php if ((int)bk_num('bk_deposit_days_before', 2) + 3 + (int)bk_num('bk_deposit_release_days', 2) > 7): ?><br><strong style="color:#b42318">Attention : pour une location de 72 h, jours avant + 3 jours + jours après dépassent 7 jours : l’empreinte expirerait avant la libération prévue.</strong><?php endif; ?>
    </div>
<?php elseif ($current === 'emails'): ?>
    <div class="hint">Le texte et l’apparence des e-mails envoyés (confirmation, annulation, alertes…) se modifient dans <a href="modeles.php">Modèles d’e-mails</a>.</div>
    <?php if (s('_mail_last_error') !== ''): ?><div class="flash flash-err">Dernière erreur d’envoi : <?= e(s('_mail_last_error')) ?></div><?php endif; ?>
<?php elseif ($current === 'automatique'): $rep = json_decode(s('_cron_report'), true) ?: []; ?>
    <div class="card">
      <h2>Tâche planifiée</h2>
      <p class="muted" style="margin-top:-6px">Les rappels et demandes d’avis partent automatiquement toutes les heures. Dernier passage :
        <strong><?= s('_cron_last') ? e(date('d/m/Y à H:i', (int)s('_cron_last'))) : 'jamais' ?></strong>
        <?php if ($rep && empty($rep['skipped'])): ?>— <?= (int)($rep['rappels'] ?? 0) ?> rappel(s), <?= (int)($rep['avis'] ?? 0) ?> demande(s) d’avis<?= !empty($rep['cautions_demandees']) || !empty($rep['cautions_liberees']) ? ', ' . (int)($rep['cautions_demandees'] ?? 0) . ' demande(s) de caution, ' . (int)($rep['cautions_liberees'] ?? 0) . ' caution(s) libérée(s)' : '' ?><?= !empty($rep['erreurs']) ? ' — <span style="color:#b42318">' . e(implode(' ; ', $rep['erreurs'])) . '</span>' : '' ?><?php endif; ?></p>
      <form method="post"><?= csrf_field() ?><button class="btn light sm" name="do" value="cronnow">▶ Lancer maintenant</button></form>
      <details style="margin-top:14px" id="diagnostic"><summary><strong>Diagnostic des lenteurs</strong></summary>
        <?php $jm = (string)db()->query('PRAGMA journal_mode')->fetchColumn(); $files = array_map('basename', glob(DATA_DIR . '/*.sqlite*') ?: []); ?>
        <ul style="line-height:1.7;margin:10px 0">
          <li>Moteur PHP : <strong><?= e(PHP_SAPI) ?></strong> <?= e(PHP_VERSION) ?> — <?= can_finish_early() ? '<span style="color:#1d7a46">✓ PHP-FPM : e-mails et agenda traités après l’affichage des pages</span>' : '<span style="color:#b42318">✗ sans PHP-FPM : choisissez le moteur « php » dans l’espace OVH (Hébergement › Informations générales › Version PHP › Modifier la configuration)</span>' ?></li>
          <li>Stockage : <strong><?= db_on_network_storage() ? 'réseau (NFS)' : 'local' ?></strong> — base en mode <strong><?= e($jm) ?></strong> <?= db_on_network_storage() && strtolower($jm) === 'wal' ? '<span style="color:#b42318">(changement en attente : la base est encore utilisée par un ancien processus)</span>' : '' ?></li>
          <li>Fichiers de la base : <?= e(implode(', ', $files)) ?></li>
          <li>OPcache : <?php $opc = extension_loaded('Zend OPcache') && filter_var(ini_get('opcache.enable'), FILTER_VALIDATE_BOOLEAN);
            $opcStatus = $opc && function_exists('opcache_get_status') ? @opcache_get_status(false) : false; ?>
            <?= $opc ? 'activé ✓' . (is_array($opcStatus) ? ' — ' . (int)($opcStatus['opcache_statistics']['num_cached_scripts'] ?? 0) . ' fichiers en cache' : '') : 'désactivé (mode « production » dans l’espace OVH : Hébergement › Informations générales › Modifier la configuration)' ?></li>
        </ul>
        <?php inflight_sweep(); $tail = perf_log_tail(60); $now_inflight = count(glob(DATA_DIR . '/inflight/*') ?: []); ?>
        <p class="muted" style="margin:0 0 6px">Requêtes en cours en ce moment : <strong><?= $now_inflight ?></strong> (dont celle-ci).</p>
        <p class="muted" style="margin:0 0 6px">Journal des lenteurs (pages de plus de 2 s, tâches de plus de 1,5 s) — aussi téléchargeable par FTP : <code>data/perf.log</code></p>
        <pre style="max-height:320px;overflow:auto;background:#0b1626;color:#dfe7f1;padding:12px;border-radius:8px;font-size:12px;white-space:pre-wrap"><?= $tail ? e(implode("\n", array_reverse($tail))) : 'Rien de lent enregistré.' ?></pre>
      </details>
      <details style="margin-top:14px"><summary><strong>Activer la tâche planifiée chez OVH (recommandé, 2 minutes)</strong></summary>
        <ol style="line-height:1.7">
          <li>Espace client OVH › <strong>Web Cloud › Hébergements</strong> › votre hébergement › onglet <strong>Plus › Tâches planifiées - Cron</strong> › <strong>Ajouter une planification</strong>.</li>
          <li>Commande à exécuter : <code>www/cron.php</code> (adaptez si le site est dans un sous-dossier) — Langage : la version de PHP du site — Fréquence : <strong>toutes les heures</strong>.</li>
          <li>Valider. Sans cette tâche, les e-mails partent quand même, au plus une fois par heure, dès qu’une page du site est consultée.</li>
        </ol>
        <p class="muted">Autre solution (service externe type cron-job.org) — adresse secrète à appeler toutes les heures :<br><code style="word-break:break-all"><?= e(bk_url('cron.php?k=' . s('bk_cron_token'))) ?></code></p>
      </details>
    </div>
<?php elseif ($current === 'agenda'): ?>
    <?php $sa = gcal_service_account(); ?>
    <div class="card">
      <h2>Un seul agenda Google pour tout</h2>
      <p class="muted" style="margin-top:-6px">Le site <strong>écrit</strong> chaque réservation payée (et chaque blocage fait ici) dans votre agenda Google, et <strong>lit</strong> ce même agenda : tout ce que vous y ajoutez depuis l’iPhone bloque les réservations, temps mort compris. Sur l’iPhone, il suffit d’afficher cet agenda Google.</p>
      <p><strong>Lecture</strong> (vos blocages → site) :
        <?php if (s('bk_gcal_url') === ''): ?><span class="pill">non configurée</span>
        <?php elseif (s('_gcal_error') !== ''): ?><span class="pill" style="background:#fdecea;color:#b42318">erreur</span> <?= e(s('_gcal_error')) ?>
        <?php else: ?><span class="pill gold">active</span> <?= (int)s('_gcal_count') ?> événement(s) à venir — lu le <?= s('_gcal_last_ok') ? e(date('d/m/Y à H:i', (int)s('_gcal_last_ok'))) : '—' ?><?php endif; ?></p>
      <p><strong>Écriture</strong> (réservations → agenda) :
        <?php if (!gcal_write_enabled()): ?><span class="pill">non configurée</span>
        <?php elseif (s('_gcal_push_error') !== ''): ?><span class="pill" style="background:#fdecea;color:#b42318">erreur</span> <?= e(s('_gcal_push_error')) ?>
        <?php else: ?><span class="pill gold">active</span> compte de service <code><?= e($sa['client_email']) ?></code><?php endif; ?></p>
      <form method="post" class="actions"><?= csrf_field() ?>
        <button class="btn light sm" name="do" value="sync">↻ Relire l’agenda</button>
        <button class="btn light sm" name="do" value="gpush">⇪ Écrire toutes les réservations à venir dans l’agenda</button>
      </form>
    </div>
    <details class="card">
      <summary><strong>Guide : autoriser le site à écrire dans votre agenda (une fois, sur ordinateur, ~10 min)</strong></summary>
      <ol style="line-height:1.7;margin-top:12px">
        <li>Ouvrez <strong>console.cloud.google.com</strong> avec votre compte Google, créez un projet (ex. « Salle RN6 »).</li>
        <li>Menu <strong>API et services › Bibliothèque</strong> : cherchez « Google Calendar API » et cliquez sur <strong>Activer</strong>.</li>
        <li><strong>API et services › Identifiants › Créer des identifiants › Compte de service</strong> : nom « site-rn6 », puis <strong>OK</strong> (aucun rôle nécessaire).</li>
        <li>Ouvrez ce compte de service › onglet <strong>Clés › Ajouter une clé › Créer une clé › JSON</strong> : un fichier se télécharge. Ouvrez-le avec le Bloc-notes et collez <strong>tout</strong> son contenu dans le champ « clé du compte de service » ci-dessous.</li>
        <li>Dans <strong>Google Agenda</strong> › Paramètres de l’agenda de la salle › <strong>Partager avec des personnes</strong> : ajoutez l’adresse du compte de service (elle finit par <code>.iam.gserviceaccount.com</code>) avec le droit <strong>« Apporter des modifications aux événements »</strong>.</li>
        <li>Sur la même page, section <strong>Intégrer l’agenda</strong> : copiez l’<strong>ID de l’agenda</strong> et l’<strong>adresse secrète au format iCal</strong> dans les champs ci-dessous, puis Enregistrer.</li>
        <li>Cliquez sur <strong>« Écrire toutes les réservations à venir dans l’agenda »</strong> : elles apparaissent dans Google Agenda et sur votre iPhone.</li>
      </ol>
      <p class="muted">Conseil : utilisez un agenda dédié à la salle (Google Agenda › Autres agendas › + › Créer un agenda) plutôt que votre agenda personnel, sinon vos rendez-vous personnels bloqueraient aussi la salle.</p>
    </details>
    <details class="card">
      <summary><strong>Autre possibilité : abonnement direct depuis l’iPhone (lecture seule)</strong></summary>
      <p class="muted" style="margin-top:12px">Lien d’abonnement privé (ne le partagez pas) :</p>
      <p><code style="word-break:break-all"><?= e(bk_url('calendrier.php?k=' . s('bk_ics_token'))) ?></code></p>
      <p class="muted">iPhone : Réglages › Calendrier › Comptes › Ajouter un compte › Autre › « Ajouter un calendrier avec abonnement ». Inutile si l’écriture dans Google Agenda est activée.</p>
      <form method="post"><?= csrf_field() ?><button class="btn danger sm" name="do" value="newtoken" onclick="return confirm('Créer un nouveau lien ? L’ancien abonnement cessera de fonctionner.')">Changer le lien</button></form>
    </details>
<?php endif; ?>

    <form method="post" class="card">
      <?= csrf_field() ?>
      <h2><?= e($sections[$current]['title']) ?></h2>
      <?php if (!empty($sections[$current]['intro'])): ?><p class="muted" style="margin-top:-6px"><?= e($sections[$current]['intro']) ?> <a href="notifications.php">Ouvrir</a></p><?php endif; ?>
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
      <?= in_array($current, SENSITIVE_SECTIONS, true) ? reauth_field() : '' ?>
      <div class="sticky-save actions">
        <button class="btn gold" name="do" value="save">💾 Enregistrer</button>
        <?php if ($current === 'emails'): ?><button class="btn light" name="do" value="testmail">✉ Envoyer un e-mail de test</button><?php endif; ?>
        <?php if ($current === 'paiement'): ?><button class="btn light" name="do" value="testpay">⚡ Tester la connexion</button><?php endif; ?>
      </div>
    </form>

<?php if ($current === 'emails'): $logs = db()->query('SELECT * FROM mail_log ORDER BY id DESC LIMIT 30')->fetchAll(); ?>
    <div class="card">
      <h2>Derniers e-mails envoyés</h2>
      <p class="muted" style="margin-top:-6px">« Accepté » = le serveur d’envoi a pris l’e-mail en charge. S’il n’arrive pas, il est bloqué ou classé en indésirables chez le destinataire.</p>
      <?php if (!$logs): ?><p class="muted">Aucun pour l’instant.</p><?php else: ?>
      <div style="overflow-x:auto"><table>
        <tr><th>Date</th><th>Destinataire</th><th>Objet</th><th>Résultat</th></tr>
        <?php foreach ($logs as $l): ?>
          <tr><td style="white-space:nowrap"><?= e(date('d/m H:i', strtotime($l['at']))) ?></td><td><?= e($l['to_addr']) ?></td><td><?= e($l['subject']) ?></td>
            <td style="color:<?= $l['ok'] ? '#1d7a46' : '#b42318' ?>;font-weight:600"><?= $l['ok'] ? '✓ Accepté' : '✕ Échec' ?><br><small class="muted" style="font-weight:400;word-break:break-word"><?= e($l['info']) ?></small></td></tr>
        <?php endforeach; ?>
      </table></div>
      <?php endif; ?>
    </div>
    <script>
    // Préremplissage selon le fournisseur choisi
    (function () {
      const presets = { gmail: ['smtp.gmail.com', '587', 'tls'], ovh: ['ssl0.ovh.net', '465', 'ssl'] };
      const sel = document.getElementById('f_bk_smtp_preset');
      if (!sel) return;
      sel.addEventListener('change', () => {
        const p = presets[sel.value];
        if (!p) return;
        document.getElementById('f_bk_smtp_host').value = p[0];
        document.getElementById('f_bk_smtp_port').value = p[1];
        document.getElementById('f_bk_smtp_secure').value = p[2];
      });
      const method = document.getElementById('f_bk_mail_method');
      const toggle = () => document.querySelectorAll('[id^="f_bk_smtp_"]').forEach(el => { el.closest('label').style.display = method.value === 'php' ? 'none' : ''; });
      method.addEventListener('change', toggle); toggle();
    })();
    </script>
<?php endif; ?>
<?php if ($current === 'tarifs'): ?>
    <div class="card">
      <h2>Simulation des prix (TTC, ce que voit le client)</h2>
      <p class="muted">Avec les réglages enregistrés. Location à l’heure : la colonne « barème » est le prix calculé ; les jours appliquent le plafond (prix de la journée).</p>
      <div style="overflow-x:auto"><table>
        <tr><th>Durée</th><th class="num">Barème</th><?php foreach (['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'] as $d): ?><th class="num"><?= $d ?></th><?php endforeach; ?></tr>
        <?php $ref = bk_now()->modify('monday next week');
        $maxH = (strtotime('2000-01-01 ' . s('bk_hour_close')) - strtotime('2000-01-01 ' . s('bk_hour_open'))) / 3600;
        for ($h = bk_num('bk_min_hours', 2); $h <= $maxH; $h++): ?>
          <tr><td><?= e(fr_hours($h)) ?> <small class="muted">(<?= money2(bk_hour_price_ttc($h) / $h) ?>/h)</small></td><td class="num"><?= money(bk_hour_price_ttc($h)) ?></td>
          <?php for ($i = 1; $i <= 7; $i++): $p = ttc(bk_rental_price('hour', $ref->modify('+' . ($i - 1) . ' days'), $h)); $capped = $p < bk_hour_price_ttc($h) - 0.01; ?>
            <td class="num" <?= $capped ? 'style="color:#617083" title="Plafonné au prix de la journée"' : '' ?>><?= money2($p) ?><?= $capped ? '*' : '' ?></td>
          <?php endfor; ?></tr>
        <?php endfor; ?>
        <?php for ($n = 1; $n <= bk_max_days(); $n++): ?>
          <tr><td><strong><?= 24 * $n ?> h</strong> <small class="muted">dès ce jour</small></td><td></td><?php for ($i = 1; $i <= 7; $i++): ?><td class="num"><strong><?= money2(ttc(bk_rental_price('day', $ref->modify('+' . ($i - 1) . ' days'), 24 * $n, $n))) ?></strong></td><?php endfor; ?></tr>
        <?php endfor; ?>
      </table></div>
      <p class="muted" style="font-size:.85rem">* plafonné au prix de la journée de ce jour : au-delà, réserver la journée revient au même prix.</p>
    </div>
<?php endif; ?>
  </div>
</div>
<?php admin_footer();
