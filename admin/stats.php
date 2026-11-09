<?php
/** Statistiques de fréquentation (sans cookie, sans service externe). */
require __DIR__ . '/_init.php';
require_admin();
stats_purge();

$periods = ['7' => '7 jours', '30' => '30 jours', '90' => '3 mois', '365' => '12 mois'];
$p = (string)($_GET['p'] ?? '30');
if (!isset($periods[$p])) {
    $p = '30';
}
$days = (int)$p;
$to = date('Y-m-d');
$from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
$prevTo = date('Y-m-d', strtotime($from . ' -1 day'));
$prevFrom = date('Y-m-d', strtotime($prevTo . ' -' . ($days - 1) . ' days'));

$now = stats_summary($from, $to);
$before = stats_summary($prevFrom, $prevTo);
$byMonth = $days > 90;
$series = stats_series($byMonth ? date('Y-m-01', strtotime($from)) : $from, $to, $byMonth);

$pageLabels = ['accueil' => 'Accueil', 'plan' => 'Plan de la salle', 'reservation' => 'Réserver en ligne'];

admin_header('Statistiques', 'stats');
?>
<h1>Statistiques</h1>
<p class="intro">Fréquentation du site et actions des visiteurs. Mesure anonyme, sans cookie (robots et administrateurs exclus).</p>

<nav class="tabs">
  <?php foreach ($periods as $k => $label): ?>
    <a href="?p=<?= $k ?>" class="<?= (string)$k === $p ? 'on' : '' ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</nav>

<div class="kpis">
  <div class="kpi"><b><?= $now['visitors'] ?></b><span>Visiteurs <?= trend($now['visitors'], $before['visitors']) ?></span></div>
  <div class="kpi"><b><?= $now['views'] ?></b><span>Pages vues <?= trend($now['views'], $before['views']) ?></span></div>
  <div class="kpi"><b><?= $now['resa'] ?></b><span>Clics « Réserver » <?= trend($now['resa'], $before['resa']) ?></span></div>
  <div class="kpi"><b><?= $now['contact'] ?></b><span>Contacts e-mail <?= trend($now['contact'], $before['contact']) ?></span></div>
</div>

<div class="card">
  <h2>Évolution <?= $byMonth ? 'par mois' : 'par jour' ?></h2>
  <?= chart_html($series, $byMonth) ?>
  <p class="muted" style="margin:12px 0 0">Taux de conversion : <strong><?= str_replace('.', ',', (string)$now['conversion']) ?> %</strong> des visiteurs ont cliqué sur « Réserver » ou contacté par e-mail
    (période précédente : <?= str_replace('.', ',', (string)$before['conversion']) ?> %).</p>
</div>

<div class="grid g2">
  <div class="card"><h2>Actions des visiteurs</h2><?= hbars(stats_breakdown('clicks', 'kind', $from, $to), CLICK_KINDS, 'Aucun clic enregistré sur la période.', true) ?></div>
  <div class="card"><h2>Pages consultées</h2><?= hbars(stats_breakdown('visits', 'page', $from, $to), $pageLabels) ?></div>
  <div class="card"><h2>Provenance</h2><p class="muted" style="margin-top:-8px">Site d’où viennent les visiteurs (Google, Facebook, 1001Salles…)</p><?= hbars(stats_breakdown('visits', 'referrer', $from, $to, 12)) ?></div>
  <div class="card"><h2>Appareils</h2><?= hbars(stats_breakdown('visits', 'device', $from, $to), ['ordinateur' => '💻 Ordinateur', 'mobile' => '📱 Mobile', 'tablette' => '📲 Tablette']) ?></div>
  <div class="card"><h2>Jours de la semaine</h2><?= hbars(stats_weekdays($from, $to), [], 'Aucune donnée', true) ?></div>
  <div class="card"><h2>Heures de visite</h2>
    <?php $hours = stats_hours($from, $to); $groups = ['Nuit (0h–7h)' => 0, 'Matin (7h–12h)' => 0, 'Midi (12h–14h)' => 0, 'Après-midi (14h–18h)' => 0, 'Soirée (18h–24h)' => 0];
    foreach ($hours as $h => $n) { $groups[match (true) { $h < 7 => 'Nuit (0h–7h)', $h < 12 => 'Matin (7h–12h)', $h < 14 => 'Midi (12h–14h)', $h < 18 => 'Après-midi (14h–18h)', default => 'Soirée (18h–24h)' }] += $n; }
    echo hbars($groups); ?>
  </div>
</div>
<p class="muted">Un « visiteur » est compté une fois par jour. Les données de plus de 25 mois sont effacées automatiquement.</p>
<?php admin_footer();
