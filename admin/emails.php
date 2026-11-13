<?php
/** Expéditions d'e-mails : écrire à un client ou à un groupe de clients, suivi des envois. */
require __DIR__ . '/_init.php';
require_perm('emails');

$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $do = (string)($_POST['do'] ?? '');

    // Lot suivant d'une expédition (appelé en boucle par la page de suivi)
    if ($do === 'process') {
        session_write_close(); // envoi d'un lot (jusqu'à 20 s) : ne bloque pas les autres pages de l'admin
        $counts = mailing_process((int)($_POST['id'] ?? 0));
        if (!empty($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode($counts);
            exit;
        }
        redirect('emails.php?id=' . (int)$_POST['id']);
    }
    if ($do === 'retry') {
        db()->prepare("UPDATE mail_queue SET status = 'pending', error = '' WHERE campaign_id = ? AND status = 'failed'")->execute([$id]);
        db()->prepare("UPDATE mail_campaigns SET status = 'sending' WHERE id = ?")->execute([$id]);
        redirect('emails.php?id=' . $id . '&run=1');
    }
    if ($do === 'stop') {
        db()->prepare("UPDATE mail_queue SET status = 'skipped', error = 'envoi arrêté' WHERE campaign_id = ? AND status = 'pending'")->execute([$id]);
        mailing_process($id);
        db()->prepare("UPDATE mail_campaigns SET status = 'done' WHERE id = ?")->execute([$id]);
        flash('Envoi arrêté.');
        redirect('emails.php?id=' . $id);
    }
    if ($do === 'reoptin') {
        db()->prepare('DELETE FROM mail_optout WHERE email = ?')->execute([strtolower(trim((string)($_POST['email'] ?? '')))]);
        flash('Adresse réinscrite.');
        redirect('emails.php?vue=desinscrits');
    }

    // Écriture : aperçu, test ou envoi
    $form = [
        'audience' => isset(MAILING_AUDIENCES[$_POST['audience'] ?? '']) ? $_POST['audience'] : 'un',
        'to' => mb_substr(trim((string)($_POST['to'] ?? '')), 0, 200),
        'subject' => mb_substr(trim(str_replace(["\r", "\n"], ' ', (string)($_POST['subject'] ?? ''))), 0, 200),
        'body' => mb_substr(trim(str_replace("\r\n", "\n", (string)($_POST['body'] ?? ''))), 0, 20000),
    ];
    $_SESSION['mail_draft'] = $form;
    $recipients = mailing_recipients($form['audience'], $form['to']);
    $errors = [];
    if ($form['subject'] === '') $errors[] = 'Indiquez un objet.';
    if ($form['body'] === '') $errors[] = 'Écrivez un message.';
    // Le test et l'aperçu fonctionnent même sans destinataire (exemple fictif) ; seul l'envoi réel en exige
    if (!$recipients && $do === 'send') $errors[] = $form['audience'] === 'un' ? 'Adresse e-mail du destinataire invalide.' : 'Aucun destinataire dans ce groupe.';
    if ($do === 'send' && $form['audience'] !== 'un' && (int)($_POST['confirm_count'] ?? -1) !== count($recipients)) {
        $errors[] = 'Le nombre de destinataires a changé : vérifiez puis confirmez à nouveau.';
    }
    if ($errors && $do !== 'preview') {
        foreach ($errors as $e) flash($e, 'err');
        redirect('emails.php');
    }
    if ($do === 'test') {
        $me = current_admin()['email'] ?: (notify_emails()[0] ?? s('contact_email'));
        $sample = reset($recipients) ?: mailing_sample_recipient();
        $ok = mailing_send_one(['audience' => $form['audience'], 'subject' => $form['subject'], 'body' => $form['body']], array_merge($sample, ['email' => $me]), true);
        flash($ok ? 'E-mail de test envoyé à ' . $me . ' (avec les informations ' . ($recipients ? 'de ' . ($sample['first_name'] ?: $sample['email']) : 'd’un client fictif') . ').' : 'Échec de l’envoi : ' . mail_last_error(), $ok ? 'ok' : 'err');
        redirect('emails.php');
    }
    if ($do === 'send') {
        $cid = mailing_create($form['audience'], $form['subject'], $form['body'], $recipients);
        admin_log('Envoi d’e-mail « ' . $form['subject'] . ' » à ' . count($recipients) . ' destinataire(s)');
        unset($_SESSION['mail_draft']);
        // Envoi immédiat du premier lot (un e-mail à un client part donc tout de suite) ;
        // les envois plus gros continuent sur la page de suivi, ou en arrière-plan.
        $c = mailing_process($cid);
        if ($c['remaining'] === 0) {
            flash($c['failed'] ? 'Échec de l’envoi : voir le détail ci-dessous.' : 'E-mail envoyé (' . $c['sent'] . ' destinataire(s)). Le détail ci-dessous montre la réponse du serveur d’envoi.', $c['failed'] ? 'err' : 'ok');
            redirect('emails.php?id=' . $cid);
        }
        redirect('emails.php?id=' . $cid . '&run=1');
    }
    redirect('emails.php?apercu=1');
}

// Brouillon / préremplissage depuis une réservation
$draft = $_SESSION['mail_draft'] ?? ['audience' => 'un', 'to' => '', 'subject' => '', 'body' => ''];
if (!empty($_GET['b']) && ($bk = bk_get((int)$_GET['b']))) {
    $draft = ['audience' => 'un', 'to' => $bk['email'], 'subject' => 'Votre réservation du ' . fr_date(bk_dt($bk['start_at']), false) . ' — ' . s('site_name'),
        'body' => "Bonjour {prenom},\n\n\n\nBien cordialement,\n" . s('site_name')];
}
$vue = (string)($_GET['vue'] ?? '');
admin_header('E-mails clients', 'emails');
?>
<h1>E-mails clients</h1>

<nav class="tabs">
  <a href="emails.php" class="<?= !$id && $vue === '' ? 'on' : '' ?>">✉ Écrire</a>
  <a href="emails.php?vue=historique" class="<?= $id || $vue === 'historique' ? 'on' : '' ?>">Envois</a>
  <?php if (admin_can('reglages')): ?><a href="modeles.php">Modèles</a><?php endif; ?>
  <a href="emails.php?vue=desinscrits" class="<?= $vue === 'desinscrits' ? 'on' : '' ?>">Désinscrits</a>
</nav>

<?php if ($id && ($c = mailing_get($id))): $counts = mailing_counts($id); $done = $counts['sent'] + $counts['failed'] + $counts['skipped']; ?>
<div class="card">
  <h2><?= e($c['subject']) ?></h2>
  <p class="muted" style="margin-top:-6px"><?= e(MAILING_AUDIENCES[$c['audience']] ?? $c['audience']) ?> · créé le <?= e(bk_dt($c['created_at'])->format('d/m/Y à H\hi')) ?></p>
  <div class="progress" style="background:#eef1f4;border-radius:999px;height:14px;overflow:hidden;margin:14px 0 8px"><div id="bar" style="height:100%;background:var(--ok);width:<?= $c['total'] ? round(100 * $done / $c['total']) : 100 ?>%;transition:width .3s"></div></div>
  <p id="status"><strong><?= $counts['sent'] ?></strong> envoyé(s) · <strong><?= $counts['failed'] ?></strong> échec(s) · <?= $counts['skipped'] ?> ignoré(s) · <strong><?= $counts['remaining'] ?></strong> restant(s) sur <?= (int)$c['total'] ?></p>
  <div class="actions">
    <?php if ($counts['remaining'] > 0): ?>
      <form method="post" id="runForm"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><button class="btn gold" name="do" value="process" id="runBtn">▶ Continuer l’envoi</button></form>
      <form method="post" action="emails.php?id=<?= $id ?>"><?= csrf_field() ?><button class="btn danger sm" name="do" value="stop" onclick="return confirm('Arrêter l’envoi ? Les e-mails restants ne partiront pas.')">Arrêter</button></form>
    <?php endif; ?>
    <?php if ($counts['failed'] > 0 && $counts['remaining'] === 0): ?>
      <form method="post" action="emails.php?id=<?= $id ?>"><?= csrf_field() ?><button class="btn light" name="do" value="retry">↻ Renvoyer les échecs</button></form>
    <?php endif; ?>
  </div>
  <p class="muted" id="keep" <?= $counts['remaining'] > 0 && isset($_GET['run']) ? '' : 'hidden' ?>>Envoi en cours par lots de <?= MAILING_BATCH ?> : gardez cette page ouverte.</p>
</div>
<?php if ($counts['remaining'] > 0 && isset($_GET['run'])): ?>
<script>
(function () {
  const form = document.getElementById('runForm');
  const csrf = form.querySelector('[name=csrf]').value;
  const total = <?= (int)$c['total'] ?>;
  async function step() {
    const fd = new FormData(); fd.append('csrf', csrf); fd.append('do', 'process'); fd.append('id', '<?= $id ?>'); fd.append('ajax', '1');
    try {
      const r = await fetch('emails.php', { method: 'POST', body: fd, credentials: 'same-origin' });
      const c = await r.json();
      const done = c.sent + c.failed + c.skipped;
      document.getElementById('bar').style.width = (total ? Math.round(100 * done / total) : 100) + '%';
      document.getElementById('status').innerHTML = `<strong>${c.sent}</strong> envoyé(s) · <strong>${c.failed}</strong> échec(s) · ${c.skipped} ignoré(s) · <strong>${c.remaining}</strong> restant(s) sur ${total}`;
      if (c.remaining > 0) setTimeout(step, 1000); else location.href = 'emails.php?id=<?= $id ?>';
    } catch (e) { document.getElementById('keep').textContent = 'Connexion interrompue : cliquez sur « Continuer l’envoi ».'; }
  }
  step();
})();
</script>
<?php endif; ?>
<div class="card">
  <h2>Destinataires</h2>
  <div style="overflow-x:auto"><table>
    <tr><th>Adresse</th><th>Nom</th><th>Statut</th></tr>
    <?php $q = db()->prepare('SELECT * FROM mail_queue WHERE campaign_id = ? ORDER BY id LIMIT 2000'); $q->execute([$id]);
    foreach ($q as $r): $col = ['sent' => '#1d7a46', 'failed' => '#b42318', 'skipped' => '#617083'][$r['status']] ?? '#7a5a12'; ?>
      <tr><td><?= e($r['email']) ?></td><td><?= e(trim($r['first_name'] . ' ' . $r['last_name'])) ?></td>
        <td style="color:<?= $col ?>;font-weight:600"><?= e(['sent' => 'Envoyé', 'failed' => 'Échec', 'skipped' => 'Ignoré', 'pending' => 'En attente'][$r['status']] ?? $r['status']) ?><?= $r['error'] !== '' ? '<br><small class="muted" style="font-weight:400;word-break:break-word">' . e($r['error']) . '</small>' : '' ?></td></tr>
    <?php endforeach; ?>
  </table></div>
  <details style="margin-top:14px"><summary>Message envoyé</summary><div style="background:#f4f6f8;border-radius:10px;padding:14px;margin-top:10px"><?= mailing_html($c['body'], []) ?></div></details>
</div>

<?php elseif ($vue === 'historique'): ?>
<div class="card">
  <?php $rows = db()->query('SELECT * FROM mail_campaigns ORDER BY id DESC LIMIT 200')->fetchAll(); ?>
  <?php if (!$rows): ?><p class="muted">Aucun envoi pour l’instant.</p><?php else: ?>
  <div style="overflow-x:auto"><table>
    <tr><th>Date</th><th>Objet</th><th>Destinataires</th><th class="num">Envoyés</th><th></th></tr>
    <?php foreach ($rows as $r): ?>
      <tr><td><?= e(bk_dt($r['created_at'])->format('d/m/Y H\hi')) ?></td><td><?= e($r['subject']) ?></td><td><?= e(MAILING_AUDIENCES[$r['audience']] ?? '') ?></td>
        <td class="num"><?= (int)$r['sent'] ?> / <?= (int)$r['total'] ?><?= $r['failed'] ? ' <span style="color:#b42318">(' . (int)$r['failed'] . ' échec)</span>' : '' ?><?= $r['status'] === 'sending' ? ' <span class="pill gold">en cours</span>' : '' ?></td>
        <td><a class="btn light sm" href="emails.php?id=<?= (int)$r['id'] ?>">Détails</a></td></tr>
    <?php endforeach; ?>
  </table></div>
  <?php endif; ?>
</div>

<?php elseif ($vue === 'desinscrits'): ?>
<div class="card">
  <p class="muted" style="margin-top:0">Adresses qui ont cliqué sur « Ne plus recevoir ces e-mails » : elles sont exclues des envois groupés (mais reçoivent toujours les e-mails de leurs réservations).</p>
  <?php $rows = db()->query('SELECT * FROM mail_optout ORDER BY at DESC')->fetchAll(); ?>
  <?php if (!$rows): ?><p class="muted">Aucune.</p><?php endif; ?>
  <?php foreach ($rows as $r): ?>
    <form method="post" class="item-row" style="grid-template-columns:1fr auto;align-items:center"><?= csrf_field() ?><input type="hidden" name="email" value="<?= e($r['email']) ?>">
      <div><strong><?= e($r['email']) ?></strong> <small class="muted">depuis le <?= e(bk_dt($r['at'])->format('d/m/Y')) ?></small></div>
      <button class="btn light sm" name="do" value="reoptin" onclick="return confirm('Réinscrire cette adresse ? Faites-le uniquement à la demande de la personne.')">Réinscrire</button>
    </form>
  <?php endforeach; ?>
</div>

<?php else:
    $counts = [];
    foreach (array_keys(MAILING_AUDIENCES) as $a) {
        $counts[$a] = $a === 'un' ? 1 : count(mailing_recipients($a));
    }
    $clients = db()->query("SELECT email, first_name, last_name FROM bookings WHERE paid_at IS NOT NULL GROUP BY lower(email) ORDER BY MAX(start_at) DESC LIMIT 500")->fetchAll();
    $preview = isset($_GET['apercu']) ? (mailing_recipients($draft['audience'], $draft['to']) ?: ['exemple' => mailing_sample_recipient()]) : [];
?>
<form method="post" class="card">
  <?= csrf_field() ?>
  <h2>Destinataires</h2>
  <div class="grid g2" style="gap:8px;margin-bottom:12px">
    <?php foreach (MAILING_AUDIENCES as $a => $label): ?>
      <label class="opt-row" style="display:flex;gap:10px;align-items:center;border:1px solid var(--line);border-radius:10px;padding:10px 12px;cursor:pointer">
        <input type="radio" name="audience" value="<?= e($a) ?>" <?= $draft['audience'] === $a ? 'checked' : '' ?>>
        <span><?= e($label) ?><?php if ($a !== 'un'): ?> <span class="pill"><?= $counts[$a] ?></span><?php endif; ?></span>
      </label>
    <?php endforeach; ?>
  </div>
  <label class="field" id="toWrap"><span>Adresse e-mail</span>
    <input type="email" name="to" list="clients" value="<?= e($draft['to']) ?>" placeholder="client@exemple.fr" autocomplete="off">
    <datalist id="clients"><?php foreach ($clients as $cl): ?><option value="<?= e($cl['email']) ?>"><?= e($cl['first_name'] . ' ' . $cl['last_name']) ?></option><?php endforeach; ?></datalist>
    <small>Tapez les premières lettres pour retrouver un client.</small>
  </label>

  <h2 style="margin-top:20px">Message</h2>
  <?= field('subject', 'Objet', $draft['subject']) ?>
  <label class="field"><span>Texte</span><textarea name="body" rows="12"><?= e($draft['body']) ?></textarea>
    <small>Variables remplacées pour chaque destinataire : <code>{prenom}</code> <code>{nom}</code> <code>{reservation}</code> (date et horaires) <code>{reference}</code> <code>{lien}</code> (son espace réservation). <code>**gras**</code> autorisé, les adresses web deviennent des liens.</small></label>
  <div class="hint">Envois groupés : un lien « Ne plus recevoir ces e-mails » est ajouté automatiquement. Réservez-les à des informations utiles à vos clients (nouveautés de la salle, offres similaires) : la loi l’autorise pour vos clients, à condition qu’ils puissent se désinscrire.
    Gmail limite à environ 500 e-mails par jour.</div>
  <div class="actions">
    <button class="btn light" name="do" value="preview">👁 Aperçu</button>
    <button class="btn light" name="do" value="test">✉ M’envoyer un test</button>
    <button class="btn gold" name="do" value="send" id="sendBtn">Envoyer</button>
    <input type="hidden" name="confirm_count" id="confirmCount" value="">
  </div>
</form>
<?php if (isset($_GET['apercu']) && $preview): $first = reset($preview); $vars = mailing_vars($first); ?>
<div class="card">
  <h2>Aperçu <small class="muted" style="font-family:Inter,sans-serif;font-size:.9rem"><?= isset($preview['exemple']) ? 'avec un client fictif' : 'pour ' . e($first['email']) ?><?= count($preview) > 1 ? ' (+ ' . (count($preview) - 1) . ' autres)' : '' ?></small></h2>
  <p><strong>Objet :</strong> <?= e(strtr($draft['subject'], $vars)) ?></p>
  <div style="border:1px solid var(--line);border-radius:10px;padding:16px"><?= mailing_html($draft['body'], $vars) ?></div>
</div>
<?php endif; ?>
<script>
(function () {
  const counts = <?= json_encode($counts) ?>;
  const labels = <?= json_encode(MAILING_AUDIENCES, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
  const radios = document.querySelectorAll('input[name=audience]');
  const toWrap = document.getElementById('toWrap');
  const btn = document.getElementById('sendBtn');
  function sync() {
    const a = document.querySelector('input[name=audience]:checked').value;
    toWrap.hidden = a !== 'un';
    btn.textContent = a === 'un' ? 'Envoyer' : `Envoyer à ${counts[a]} client(s)`;
    document.getElementById('confirmCount').value = counts[a];
  }
  radios.forEach(r => r.addEventListener('change', sync)); sync();
  btn.addEventListener('click', e => {
    const a = document.querySelector('input[name=audience]:checked').value;
    if (a !== 'un' && !confirm(`Envoyer ce message à ${counts[a]} client(s) (« ${labels[a]} ») ?`)) e.preventDefault();
  });
})();
</script>
<?php endif; ?>
<?php admin_footer();
