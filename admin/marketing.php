<?php
/** Marketing : abonnés (preuve du consentement), résultats des relances, offres « dernière minute ». */
require __DIR__ . '/_init.php';
require_perm('reservations');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $do = (string)($_POST['do'] ?? '');
    // Envoi groupé : droit « E-mails clients » ; remise sur les prix : droit « Tarifs » ; export des données : mot de passe redemandé
    if (($do === 'lm_send' && !admin_can('emails')) || (in_array($do, ['lm_send', 'lm_apply', 'lm_clear'], true) && !admin_can('tarifs'))) {
        flash('Votre compte n’a pas le droit de faire cette action (droits « Tarifs » et « E-mails clients »).', 'err');
        redirect('marketing.php');
    }
    if ($do === 'csv') {
        require_reauth('marketing.php');
    }
    if ($do === 'lm_send' || $do === 'lm_apply') {
        $n = marketing_lastminute_run($do === 'lm_send');
        $dates = (int)db()->query('SELECT COUNT(*) FROM special_prices WHERE auto = 1')->fetchColumn();
        admin_log('Dernière minute : ' . $dates . ' date(s) remisée(s)' . ($do === 'lm_send' ? ', e-mail à ' . $n . ' destinataire(s)' : ''));
        flash($dates ? $dates . ' date(s) libre(s) affichée(s) avec la remise.' . ($do === 'lm_send' ? ($n ? ' E-mail en cours d’envoi à ' . $n . ' destinataire(s) (suivi dans E-mails clients).' : ' Aucun destinataire à joindre (ou tous ont déjà reçu une offre il y a moins de 2 semaines).') : '')
            : 'Aucune date libre à remiser pour l’instant.');
    } elseif ($do === 'lm_clear') {
        db()->exec('DELETE FROM special_prices WHERE auto = 1');
        admin_log('Dernière minute : remises retirées');
        flash('Remises « dernière minute » retirées : les prix normaux s’appliquent.');
    } elseif ($do === 'del' && filter_var($em = strtolower(trim((string)($_POST['email'] ?? ''))), FILTER_VALIDATE_EMAIL)) {
        require_reauth('marketing.php');
        db()->prepare('DELETE FROM contacts WHERE email = ?')->execute([$em]);
        db()->prepare('INSERT OR IGNORE INTO mail_optout(email, at) VALUES(?, ?)')->execute([$em, bk_now()->format(BK_FMT)]);
        admin_log('Contact supprimé et désinscrit : ' . $em);
        flash($em . ' supprimé de la liste et désinscrit de tous les envois commerciaux.');
    } elseif ($do === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="abonnes-' . date('Y-m-d') . '.csv"');
        $f = fopen('php://output', 'w');
        fwrite($f, "\xEF\xBB\xBF");
        fputcsv($f, ['E-mail', 'Prénom', 'Consentement', 'Date du consentement', 'Formulaire', 'Texte accepté', 'Dernière activité'], ';', '"', '');
        $opt = array_flip(db()->query('SELECT email FROM mail_optout')->fetchAll(PDO::FETCH_COLUMN));
        foreach (db()->query('SELECT * FROM contacts WHERE consent = 1 ORDER BY consent_at DESC') as $c) {
            if (isset($opt[$c['email']])) continue;
            fputcsv($f, array_map(fn($v) => preg_match('/^[=+\-@]/', (string)$v) ? "'" . $v : $v, [$c['email'], $c['first_name'], 'oui', $c['consent_at'], $c['consent_source'], $c['consent_text'], $c['last_activity_at']]), ';', '"', '');
        }
        exit;
    }
    redirect('marketing.php');
}

$opt = array_flip(db()->query('SELECT email FROM mail_optout')->fetchAll(PDO::FETCH_COLUMN));
$subs = (int)db()->query('SELECT COUNT(*) FROM contacts WHERE consent = 1')->fetchColumn();
$reach = count(marketing_audience(false));
$since = bk_now()->modify('-90 days')->format(BK_FMT);
$st = db()->prepare("SELECT source, COUNT(*) n, SUM(total_ttc) ca FROM bookings WHERE status = 'confirmed' AND source <> '' AND created_at >= ? GROUP BY source ORDER BY n DESC");
$st->execute([$since]);
$sources = $st->fetchAll();
$srcLabels = ['relance-devis' => 'Relance de devis', 'relance-paiement' => 'Relance « réservation non finalisée »', 'relance-11-mois' => 'Relance « 11 mois après »',
    'derniere-minute' => 'E-mail dernière minute', 'bandeau-derniere-minute' => 'Bandeau dernière minute (accueil)', 'parrainage' => 'Avoir de parrainage', 'apres-evenement' => 'E-mail de remerciement'];
$ref = db()->query("SELECT COUNT(*) FROM bookings b JOIN promo_codes p ON p.code = b.promo_code AND p.referral = 1 WHERE b.status = 'confirmed'")->fetchColumn();
$lmDates = db()->query("SELECT * FROM special_prices WHERE auto = 1 ORDER BY date_from")->fetchAll();
$preview = marketing_lastminute_dates();
$contacts = db()->query('SELECT * FROM contacts ORDER BY COALESCE(consent_at, last_activity_at) DESC LIMIT 300')->fetchAll();
$auto = [
    ['Récapitulatif par e-mail (visiteur qui hésite)', 'mk_recap'], ['Relance des devis', 'mk_devis_relance'], ['Relance des paiements abandonnés', 'mk_abandon_relance'],
    ['Remerciement + parrainage après l’événement', 'mk_post_event'], ['Relance 11 mois après', 'mk_anniv'], ['Offres dernière minute (chaque lundi)', 'mk_lastminute'],
];
admin_header('Marketing', 'marketing');
?>
<h1>Marketing & relances</h1>
<p class="intro">Les e-mails partent tout seuls avec la tâche planifiée. Chacun contient un lien de désinscription ; une adresse désinscrite ne reçoit plus rien de commercial. Les e-mails d’offres ne sont envoyés qu’aux abonnés ayant coché la case et aux anciens clients.</p>

<div class="kpis">
  <div class="kpi"><b><?= $subs ?></b><span>abonné(s) aux actualités</span></div>
  <div class="kpi"><b><?= $reach ?></b><span>destinataire(s) joignables pour une offre (abonnés + clients)</span></div>
  <div class="kpi"><b><?= array_sum(array_column($sources, 'n')) ?></b><span>réservation(s) venues des relances et offres (90 j) · <?= money2((float)array_sum(array_column($sources, 'ca'))) ?></span></div>
  <div class="kpi"><b><?= (int)$ref ?></b><span>réservation(s) par parrainage</span></div>
</div>

<div class="grid g2" style="align-items:start;margin-top:16px">
  <div class="card">
    <h2>Automatismes</h2>
    <table>
      <?php foreach ($auto as [$l, $k]): $on = $k === 'mk_lastminute' ? s($k) === '1' : s($k) !== '0'; ?>
      <tr><td><?= e($l) ?></td><td class="num"><span class="pill" style="<?= $on ? 'background:#e7f6ec;color:#1d7a46' : '' ?>"><?= $on ? 'Activé' : 'Désactivé' ?></span></td></tr>
      <?php endforeach; ?>
    </table>
    <p style="margin-top:12px"><a class="btn light sm" href="reglages.php?s=marketing">Régler (remises, délais, parrainage…)</a> <a class="btn light sm" href="modeles.php">Textes des e-mails</a></p>
    <?php if ($sources): ?>
    <h3 style="margin-top:18px">D’où viennent les réservations récupérées (90 jours)</h3>
    <table><?php foreach ($sources as $s): ?><tr><td><?= e($srcLabels[$s['source']] ?? $s['source']) ?></td><td class="num"><?= (int)$s['n'] ?></td><td class="num"><?= money2((float)$s['ca']) ?></td></tr><?php endforeach; ?></table>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>⚡ Dernière minute</h2>
    <p class="muted" style="margin-top:-6px">Remise de <?= e(rating_label((string)bk_num('lm_percent', 15))) ?> % sur les dates libres des <?= (int)bk_num('lm_days', 30) ?> prochains jours (<?= e(strtolower(BOOKING_SETTINGS['marketing']['fields']['lm_weekdays']['options'][s('lm_weekdays')] ?? '')) ?>).
      <?= s('mk_lastminute') === '1' ? 'Automatique chaque lundi.' : 'L’envoi automatique du lundi est désactivé : vous pouvez le lancer ici.' ?></p>
    <?php if ($lmDates): ?>
      <p><strong>Remises en cours :</strong> <?= e(implode(', ', array_map(fn($d) => fr_date(bk_dt($d['date_from'])), $lmDates))) ?></p>
    <?php endif; ?>
    <p><strong>Dates libres concernées aujourd’hui :</strong> <?= $preview ? e(implode(', ', array_map(fn($d) => fr_date($d), $preview))) : 'aucune' ?></p>
    <form method="post" style="display:flex;gap:8px;flex-wrap:wrap">
      <?= csrf_field() ?>
      <button class="btn gold sm" name="do" value="lm_send" onclick="return confirm('Appliquer la remise sur ces dates et envoyer l’offre par e-mail aux abonnés et clients ?')"<?= $preview ? '' : ' disabled' ?>>Appliquer + envoyer l’e-mail</button>
      <button class="btn light sm" name="do" value="lm_apply"<?= $preview ? '' : ' disabled' ?>>Appliquer sans e-mail</button>
      <?php if ($lmDates): ?><button class="btn light sm" name="do" value="lm_clear">Retirer les remises</button><?php endif; ?>
    </form>
  </div>
</div>

<div class="card">
  <h2 style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap">Contacts <form method="post" style="margin:0;display:flex;gap:6px"><?= csrf_field() ?><?= reauth_needed() ? '<input type="password" name="reauth" placeholder="Votre mot de passe" style="width:150px" autocomplete="current-password">' : '' ?><button class="btn light sm" name="do" value="csv">⬇ Exporter les abonnés (CSV)</button></form></h2>
  <p class="muted" style="margin-top:-6px">Abonnés (case cochée), demandes de devis, messages et clients. Preuve du consentement conservée (date, formulaire, texte accepté). Les prospects sans activité depuis 3 ans sont effacés automatiquement.</p>
  <?php if (!$contacts): ?><p class="muted">Aucun contact pour l’instant.</p><?php else: ?>
  <div style="overflow-x:auto"><table>
    <tr><th>E-mail</th><th>Origine</th><th>Actualités</th><th>Dernière activité</th><th></th></tr>
    <?php foreach ($contacts as $c): $out = isset($opt[$c['email']]); ?>
    <tr>
      <td><?= e($c['email']) ?><?= $c['first_name'] !== '' ? '<br><span class="muted" style="font-size:.85rem">' . e($c['first_name']) . '</span>' : '' ?></td>
      <td><?= e(ucfirst($c['source'])) ?></td>
      <td><?php if ($out): ?><span class="pill">Désinscrit</span><?php elseif ((int)$c['consent']): ?><span class="pill" style="background:#e7f6ec;color:#1d7a46" title="<?= e($c['consent_text']) ?>">Oui · <?= e(bk_dt($c['consent_at'])->format('d/m/Y')) ?> (<?= e($c['consent_source']) ?>)</span><?php else: ?><span class="muted">Non</span><?php endif; ?></td>
      <td><?= e(bk_dt($c['last_activity_at'])->format('d/m/Y')) ?></td>
      <td><form method="post" style="margin:0"><?= csrf_field() ?><input type="hidden" name="email" value="<?= e($c['email']) ?>"><?= reauth_needed() ? '<input type="password" name="reauth" placeholder="Mot de passe" style="width:120px">' : '' ?><button class="btn danger sm" name="do" value="del" onclick="return confirm('Supprimer ce contact et le désinscrire de tous les envois commerciaux ?')">Supprimer</button></form></td>
    </tr>
    <?php endforeach; ?>
  </table></div>
  <?php endif; ?>
</div>
<?php admin_footer();
