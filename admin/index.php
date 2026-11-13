<?php
/** Tableau de bord. */
require __DIR__ . '/_init.php';
$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['maintenance'])) {
    check_csrf();
    $on = $_POST['maintenance'] === '1';
    if (!admin_can('reglages')) {
        redirect('index.php');
    }
    save_setting('resa_maintenance', $on ? '1' : '0');
    admin_log($on ? 'Réservation en ligne mise en maintenance' : 'Réservation en ligne réouverte');
    flash($on ? 'Réservation en ligne passée en maintenance.' : 'Réservation en ligne réouverte.');
    redirect('index.php');
}

ensure_all_variants(4.0); // crée les WebP des photos qui n'en ont pas encore
$today = date('Y-m-d');
$t = stats_summary($today, $today);
$w = stats_summary(date('Y-m-d', strtotime('-6 days')), $today);
$m = stats_summary(date('Y-m-d', strtotime('-29 days')), $today);
$nbPhotos = (int)db()->query('SELECT COUNT(*) FROM photos WHERE visible = 1')->fetchColumn();
bk_expire_pending();
$nextBookings = db()->prepare("SELECT * FROM bookings WHERE status = 'confirmed' AND end_at > ? ORDER BY start_at LIMIT 5");
$nextBookings->execute([bk_now()->format(BK_FMT)]);
$nextBookings = $nextBookings->fetchAll();

$checks = [];
if (stripos((string)($_SERVER['SERVER_SOFTWARE'] ?? ''), 'nginx') !== false) {
    $checks[] = 'Serveur Nginx détecté : les fichiers .htaccess ne sont pas appliqués. Ajoutez les règles de protection indiquées dans le README.';
}
if (is_file(SETUP_CODE_FILE)) {
    @unlink(SETUP_CODE_FILE);
}
if (!is_writable(UPLOAD_DIR)) {
    $checks[] = 'Le dossier photos/ n’est pas accessible en écriture : l’envoi de photos échouera.';
}
if (!is_https() && !in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'], true) && !str_starts_with($_SERVER['HTTP_HOST'] ?? '', 'localhost:')) {
    $checks[] = 'Le site n’est pas en HTTPS : activez le certificat SSL chez votre hébergeur pour protéger votre mot de passe.';
}

admin_header('Tableau de bord', 'index');
$can = fn($p) => admin_can($p);
?>
<h1>Bonjour <?= e($admin['username']) ?> 👋</h1>
<p class="intro">Voici l’activité de <?= e(s('site_name')) ?>.</p>
<?php if ($can('reglages') && time() - ($lastBackup = backup_last_time()) > 3 * 86400): ?>
<div class="flash flash-err">💾 <?= $lastBackup ? 'Aucune sauvegarde depuis le ' . e(date('d/m/Y', $lastBackup)) : 'Aucune sauvegarde n’a encore été faite' ?>. <a href="sauvegardes.php">Sauvegarder maintenant</a><?= s('_backup_error') !== '' ? ' — dernière erreur : ' . e(s('_backup_error')) : '' ?></div>
<?php endif; ?>
<?php if ($can('reglages') && s('_drive_error') !== ''): ?>
<div class="flash flash-err">☁ Copie des sauvegardes sur Google Drive en échec : <?= e(s('_drive_error')) ?> <a href="sauvegardes.php#drive">Voir</a></div>
<?php endif; ?>

<?php if ($admin['email'] === ''): ?>
  <div class="flash flash-err">Ajoutez votre adresse e-mail dans <a href="compte.php">Mon compte</a> : sans elle, impossible de récupérer votre mot de passe en cas d’oubli.</div>
<?php endif; ?>

<?php if ($can('reglages')): $maint = s('resa_maintenance') === '1'; ?>
<form method="post" class="card" style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;<?= $maint ? 'border-color:#f0c36d;background:#fffaf0' : '' ?>">
  <?= csrf_field() ?>
  <div>
    <h2 style="margin:0 0 4px"><?= $maint ? '🛠 Réservation en ligne : en maintenance' : '✅ Réservation en ligne : ouverte' ?></h2>
    <p class="muted" style="margin:0"><?= $maint
        ? 'Les visiteurs voient votre message à la place du calendrier de réservation.'
        : 'Les visiteurs peuvent réserver et payer en ligne.' ?>
      <?php if ($can('contenu')): ?><a href="textes.php?s=resa">Modifier la page et le message</a> · <?php endif; ?><a href="../reservation.php" target="_blank">Voir la page ↗</a></p>
  </div>
  <button class="btn <?= $maint ? 'gold' : 'light' ?>" name="maintenance" value="<?= $maint ? '0' : '1' ?>"
    <?= $maint ? '' : 'onclick="return confirm(\'Passer la réservation en ligne en maintenance ?\')"' ?>><?= $maint ? 'Rouvrir la réservation' : 'Passer en maintenance' ?></button>
</form>
<?php endif; ?>

<?php if ($can('reglages')) foreach ($checks as $c): ?><div class="flash flash-err">⚠ <?= e($c) ?></div><?php endforeach; ?>

<?php if ($can('reservations')): ?>
<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap"><h2 style="margin:0">📅 Prochaines réservations</h2><a href="reservations.php">Toutes les réservations →</a></div>
  <?php if (!$nextBookings): ?><p class="muted">Aucune réservation à venir.</p><?php else: ?>
  <div style="overflow-x:auto"><table style="margin-top:8px">
    <?php foreach ($nextBookings as $nb): ?>
      <tr><td><strong><?= e(fr_date(bk_dt($nb['start_at']))) ?></strong> <span class="muted"><?= $nb['mode'] === 'day' ? (int)$nb['hours'] . ' h' : e(substr($nb['start_at'], 11, 5) . '–' . substr($nb['end_at'], 11, 5)) ?></span></td>
        <td><?= e($nb['first_name'] . ' ' . $nb['last_name']) ?> — <?= e($nb['event_type']) ?>, <?= (int)$nb['guests'] ?> pers.</td>
        <?php if ($can('stats')): ?><td class="num"><?= money2((float)$nb['total_ttc']) ?></td><?php endif; ?><td><a href="reservation.php?id=<?= (int)$nb['id'] ?>">Ouvrir</a></td></tr>
    <?php endforeach; ?>
  </table></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($can('stats')): ?>
<div class="kpis">
  <div class="kpi"><b><?= $t['visitors'] ?></b><span>Visiteurs aujourd’hui</span></div>
  <div class="kpi"><b><?= $w['visitors'] ?></b><span>Visiteurs sur 7 jours</span></div>
  <div class="kpi"><b><?= $m['visitors'] ?></b><span>Visiteurs sur 30 jours</span></div>
  <div class="kpi"><b><?= $m['resa'] + $m['contact'] ?></b><span>Clics « Réserver » et e-mail sur 30 j</span></div>
</div>
<div class="card">
  <h2>14 derniers jours</h2>
  <?= chart_html(stats_series(date('Y-m-d', strtotime('-13 days')), $today)) ?>
  <p style="margin:14px 0 0"><a href="stats.php">Toutes les statistiques →</a></p>
</div>
<?php endif; ?>

<?php
$cards = [
    ['contenu', 'textes.php', '✏️ Textes', 'Titres, descriptions, coordonnées, référencement.'],
    ['contenu', 'photos.php', '📷 Photos', $nbPhotos . ' photos dans la galerie.'],
    ['contenu', 'avis.php', '★ Avis', 'Avis Google et 1001Salles affichés sur le site.'],
    ['tarifs', 'reglages.php?s=tarifs', '€ Tarifs & options', 'Prix à la journée et à l’heure, options payantes.'],
    ['emails', 'emails.php', '✉ E-mails clients', 'Écrire à un client ou à un groupe.'],
    ['utilisateurs', 'utilisateurs.php', '👥 Équipe', 'Comptes et droits d’accès.'],
    ['reservations', 'compte.php#app', '📱 Application', 'Installer l’admin sur le téléphone et recevoir une notification à chaque réservation.'],
];
$cards = array_filter($cards, fn($c) => $can($c[0]));
?>
<?php if ($cards): ?>
<div class="grid g3">
  <?php foreach ($cards as [, $href, $title, $text]): ?>
    <a class="card" href="<?= e($href) ?>" style="text-decoration:none"><h2><?= e($title) ?></h2><p class="muted"><?= e($text) ?></p></a>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php admin_footer();
