<?php
/** Historique : relire un e-mail envoyé (contenu tel que reçu par le destinataire), et le renvoyer. */
require __DIR__ . '/_init.php';
$admin = require_admin();
if (!admin_can('emails') && !admin_can('reservations')) {
    require_perm('emails');
}

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$st = db()->prepare('SELECT * FROM mail_log WHERE id = ?');
$st->execute([$id]);
$m = $st->fetch();
if (!$m) {
    http_response_code(404);
    admin_header('E-mail introuvable');
    echo '<h1>E-mail introuvable</h1><p class="intro">Il a peut-être été effacé (l’historique est conservé 2 ans).</p>';
    admin_footer();
    exit;
}
// Sans le droit « E-mails clients », seuls les e-mails envoyés à un client ayant réservé sont visibles
if (!admin_can('emails')) {
    $own = db()->prepare('SELECT 1 FROM bookings WHERE email = ? COLLATE NOCASE LIMIT 1');
    $own->execute([$m['to_addr']]);
    if (!$own->fetchColumn()) {
        require_perm('emails');
    }
}
$html = $m['body'] !== null ? (string)@gzinflate((string)$m['body']) : '';

// Contenu brut, affiché dans un cadre isolé (aucun script, liens ouverts dans un nouvel onglet)
if (isset($_GET['raw'])) {
    header('X-Frame-Options: SAMEORIGIN');
    header("Content-Security-Policy: default-src 'none'; img-src 'self' data: https:; style-src 'unsafe-inline'; frame-ancestors 'self'; sandbox allow-popups allow-popups-to-escape-sandbox");
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><base target="_blank"><style>body{margin:0}</style>', $html;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'resend' && $html !== '') {
    check_csrf();
    // Changer d'adresse : droit « E-mails clients » uniquement (l'e-mail contient le lien privé du client)
    $to = admin_can('emails') && (string)($_POST['to'] ?? '') !== '' ? trim((string)$_POST['to']) : $m['to_addr'];
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        flash('Adresse e-mail invalide.', 'err');
        redirect('mail.php?id=' . $id);
    }
    $ok = send_mail($to, $m['subject'], $html, mail_reply_to());
    admin_log('E-mail renvoyé à ' . $to . ' : ' . $m['subject']);
    flash($ok ? 'E-mail renvoyé à ' . $to . '.' : 'Échec de l’envoi : ' . mail_last_error(), $ok ? 'ok' : 'err');
    redirect('mail.php?id=' . $id);
}

// Réservations de ce destinataire (lien retour)
$bk = db()->prepare('SELECT id, ref FROM bookings WHERE email = ? COLLATE NOCASE ORDER BY id DESC LIMIT 3');
$bk->execute([$m['to_addr']]);
$bookings = $bk->fetchAll();

header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https:; connect-src 'self'; frame-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; frame-ancestors 'none'; form-action 'self'; base-uri 'none'; object-src 'none'");
admin_header('E-mail envoyé', admin_can('emails') ? 'emails' : 'reservations');
?>
<p><a href="<?= admin_can('emails') ? 'emails.php?vue=journal' : 'reservations.php' ?>" class="muted">← <?= admin_can('emails') ? 'Tous les e-mails envoyés' : 'Réservations' ?></a></p>
<h1 style="word-break:break-word"><?= e($m['subject']) ?></h1>
<div class="card">
  <p style="margin:0 0 6px"><strong>À :</strong> <?= e($m['to_addr']) ?>
    <?php foreach ($bookings as $b): ?> · <a href="reservation.php?id=<?= (int)$b['id'] ?>">réservation <?= e($b['ref']) ?></a><?php endforeach; ?></p>
  <p style="margin:0 0 6px"><strong>Envoyé le :</strong> <?= e(date('d/m/Y à H\hi', strtotime($m['at']))) ?></p>
  <p style="margin:0"><strong>Résultat :</strong> <span style="color:<?= $m['ok'] ? '#1d7a46' : '#b42318' ?>;font-weight:600"><?= $m['ok'] ? '✓ Accepté par le serveur d’envoi' : '✕ Échec' ?></span>
    <small class="muted" style="word-break:break-word"><?= e($m['info']) ?></small></p>
  <?php if ($html !== ''): ?>
  <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
    <?php if (admin_can('emails')): ?><input type="email" name="to" value="<?= e($m['to_addr']) ?>" style="max-width:280px" aria-label="Adresse"><?php endif; ?>
    <button class="btn light sm" name="do" value="resend" onclick="return confirm('Renvoyer cet e-mail tel quel ?')">✉ Renvoyer</button>
  </form>
  <?php endif; ?>
</div>
<?php if ($html !== ''): ?>
<div class="card" style="padding:0;overflow:hidden">
  <iframe src="mail.php?id=<?= $id ?>&amp;raw=1" title="Contenu de l’e-mail" style="width:100%;height:75vh;border:0;display:block;background:#f4f1ea"></iframe>
</div>
<?php else: ?>
<p class="muted">Le contenu de cet e-mail n’a pas été conservé (envoyé avant la mise en place de l’historique).</p>
<?php endif; ?>
<?php admin_footer();
