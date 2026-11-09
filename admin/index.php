<?php
/** Tableau de bord. */
require __DIR__ . '/_init.php';
$admin = require_admin();

$today = date('Y-m-d');
$t = stats_summary($today, $today);
$w = stats_summary(date('Y-m-d', strtotime('-6 days')), $today);
$m = stats_summary(date('Y-m-d', strtotime('-29 days')), $today);
$nbPhotos = (int)db()->query('SELECT COUNT(*) FROM photos WHERE visible = 1')->fetchColumn();
$nbTarifs = (int)db()->query("SELECT COUNT(*) FROM items WHERE grp LIKE 'tarif_%' AND visible = 1")->fetchColumn();

$checks = [];
if (!is_writable(UPLOAD_DIR)) {
    $checks[] = 'Le dossier photos/ n’est pas accessible en écriture : l’envoi de photos échouera.';
}
if (empty($_SERVER['HTTPS']) && !in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'], true) && !str_starts_with($_SERVER['HTTP_HOST'] ?? '', 'localhost:')) {
    $checks[] = 'Le site n’est pas en HTTPS : activez le certificat SSL chez votre hébergeur pour protéger votre mot de passe.';
}

admin_header('Tableau de bord', 'index');
?>
<h1>Bonjour <?= e($admin['username']) ?> 👋</h1>
<p class="intro">Voici l’activité de <?= e(s('site_name')) ?>.</p>

<?php foreach ($checks as $c): ?><div class="flash flash-err">⚠ <?= e($c) ?></div><?php endforeach; ?>

<div class="kpis">
  <div class="kpi"><b><?= $t['visitors'] ?></b><span>Visiteurs aujourd’hui</span></div>
  <div class="kpi"><b><?= $w['visitors'] ?></b><span>Visiteurs sur 7 jours</span></div>
  <div class="kpi"><b><?= $m['visitors'] ?></b><span>Visiteurs sur 30 jours</span></div>
  <div class="kpi"><b><?= $m['resa'] + $m['contact'] ?></b><span>Demandes (clics réserver + e-mail) sur 30 j</span></div>
</div>

<div class="card">
  <h2>14 derniers jours</h2>
  <?= chart_html(stats_series(date('Y-m-d', strtotime('-13 days')), $today)) ?>
  <p style="margin:14px 0 0"><a href="stats.php">Toutes les statistiques →</a></p>
</div>

<div class="grid g4">
  <a class="card" href="textes.php" style="text-decoration:none"><h2>✏️ Textes</h2><p class="muted">Titres, descriptions, coordonnées, référencement.</p></a>
  <a class="card" href="listes.php?g=tarif_jours" style="text-decoration:none"><h2>€ Tarifs</h2><p class="muted"><?= $nbTarifs ?> lignes de tarifs affichées.</p></a>
  <a class="card" href="photos.php" style="text-decoration:none"><h2>📷 Photos</h2><p class="muted"><?= $nbPhotos ?> photos dans la galerie.</p></a>
  <a class="card" href="listes.php" style="text-decoration:none"><h2>☰ Listes</h2><p class="muted">Points forts, équipements, mobilier, accès…</p></a>
</div>
<?php admin_footer();
