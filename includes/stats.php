<?php
/** Requêtes de statistiques (utilisées par le tableau de bord et la page Statistiques). */

/** Résumé d'une période [from, to] (dates Y-m-d incluses). */
function stats_summary(string $from, string $to): array
{
    $q = function (string $sql) use ($from, $to) {
        $st = db()->prepare($sql);
        $st->execute([$from, $to]);
        return (int)$st->fetchColumn();
    };
    $views    = $q('SELECT COUNT(*) FROM visits WHERE day BETWEEN ? AND ?');
    $visitors = $q('SELECT COUNT(*) FROM (SELECT DISTINCT day, visitor FROM visits WHERE day BETWEEN ? AND ?)');
    $resa     = $q("SELECT COUNT(*) FROM clicks WHERE kind = 'reserver' AND day BETWEEN ? AND ?");
    $contact  = $q("SELECT COUNT(*) FROM clicks WHERE kind IN ('email','copy') AND day BETWEEN ? AND ?");
    $converted = $q("SELECT COUNT(*) FROM (SELECT DISTINCT day, visitor FROM clicks WHERE kind IN ('reserver','email','copy') AND day BETWEEN ? AND ?)");
    return [
        'views' => $views,
        'visitors' => $visitors,
        'resa' => $resa,
        'contact' => $contact,
        'conversion' => $visitors ? round(100 * $converted / $visitors, 1) : 0.0,
    ];
}

/** Série par jour (ou par mois si $byMonth) : [label => [views, visitors]]. */
function stats_series(string $from, string $to, bool $byMonth = false): array
{
    $series = [];
    $d = new DateTime($from);
    $end = new DateTime($to);
    while ($d <= $end) {
        $series[$d->format($byMonth ? 'Y-m' : 'Y-m-d')] = ['views' => 0, 'visitors' => 0];
        $d->modify($byMonth ? 'first day of next month' : '+1 day');
    }
    $key = $byMonth ? 'substr(day, 1, 7)' : 'day';
    $st = db()->prepare("SELECT $key AS k, COUNT(*) AS v, COUNT(DISTINCT day || visitor) AS u FROM visits WHERE day BETWEEN ? AND ? GROUP BY k");
    $st->execute([$from, $to]);
    foreach ($st as $r) {
        if (isset($series[$r['k']])) {
            $series[$r['k']] = ['views' => (int)$r['v'], 'visitors' => (int)$r['u']];
        }
    }
    return $series;
}

/** Répartition [libellé => nombre] pour une colonne de visits ou clicks. */
function stats_breakdown(string $table, string $col, string $from, string $to, int $limit = 10): array
{
    $allowed = ['visits' => ['page', 'referrer', 'device'], 'clicks' => ['kind']];
    if (!in_array($col, $allowed[$table] ?? [], true)) {
        return [];
    }
    $st = db()->prepare("SELECT $col AS k, COUNT(*) AS n FROM $table WHERE day BETWEEN ? AND ? GROUP BY $col ORDER BY n DESC LIMIT $limit");
    $st->execute([$from, $to]);
    return array_column($st->fetchAll(), 'n', 'k');
}

function stats_weekdays(string $from, string $to): array
{
    $names = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];
    $out = array_fill_keys(['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'], 0);
    $st = db()->prepare("SELECT strftime('%w', day) AS w, COUNT(*) AS n FROM visits WHERE day BETWEEN ? AND ? GROUP BY w");
    $st->execute([$from, $to]);
    foreach ($st as $r) {
        $out[$names[(int)$r['w']]] = (int)$r['n'];
    }
    return $out;
}

function stats_hours(string $from, string $to): array
{
    $out = array_fill(0, 24, 0);
    $st = db()->prepare("SELECT CAST(substr(visited_at, 12, 2) AS INTEGER) AS h, COUNT(*) AS n FROM visits WHERE day BETWEEN ? AND ? GROUP BY h");
    $st->execute([$from, $to]);
    foreach ($st as $r) {
        $out[(int)$r['h']] = (int)$r['n'];
    }
    return $out;
}

/** Supprime les données de plus de 25 mois (minimisation RGPD). */
function stats_purge(): void
{
    $limit = date('Y-m-d', strtotime('-25 months'));
    db()->prepare('DELETE FROM visits WHERE day < ?')->execute([$limit]);
    db()->prepare('DELETE FROM clicks WHERE day < ?')->execute([$limit]);
}

/** Barre de comparaison avec la période précédente. */
function trend(float $now, float $before): string
{
    if ($before == 0) {
        return $now > 0 ? '<em class="up">nouveau</em>' : '';
    }
    $pct = round(100 * ($now - $before) / $before);
    return '<em class="' . ($pct >= 0 ? 'up' : 'down') . '">' . ($pct >= 0 ? '▲ +' : '▼ ') . $pct . ' %</em>';
}

/** Histogramme vertical en HTML/CSS pur. */
function chart_html(array $series, bool $byMonth = false): string
{
    $max = max(1, ...array_values(array_map(fn($r) => $r["views"], $series)));
    $months = ['', 'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
    $n = count($series);
    $every = $byMonth ? 1 : max(1, (int)ceil($n / 15));
    $cols = $xs = '';
    $i = 0;
    foreach ($series as $k => $r) {
        $label = $byMonth ? $months[(int)substr($k, 5, 2)] . ' ' . substr($k, 2, 2) : (int)substr($k, 8, 2) . '/' . (int)substr($k, 5, 2);
        $title = ($byMonth ? $label : date('d/m/Y', strtotime($k))) . ' : ' . $r['views'] . ' pages vues, ' . $r['visitors'] . ' visiteurs';
        $hv = round(100 * $r['views'] / $max, 1);
        $hu = round(100 * $r['visitors'] / $max, 1);
        $cols .= '<div class="col" title="' . e($title) . '"><div class="bar" style="height:' . max(0, $hv - $hu) . '%"></div><div class="bar u" style="height:' . $hu . '%"></div></div>';
        $xs .= '<span>' . ($i % $every === 0 ? e($label) : '') . '</span>';
        $i++;
    }
    return '<div class="legend"><span><i style="background:var(--gold)"></i>Visiteurs</span><span><i style="background:var(--navy2)"></i>Pages vues (en plus)</span><span>max : ' . $max . '</span></div>'
        . '<div class="chart" role="img" aria-label="Évolution des visites">' . $cols . '</div><div class="chart-x">' . $xs . '</div>';
}

/** Barres horizontales [libellé => n]. */
function hbars(array $data, array $labels = [], string $empty = 'Aucune donnée', bool $gold = false): string
{
    if (!$data || !array_sum($data)) {
        return '<p class="muted">' . e($empty) . '</p>';
    }
    $max = max($data);
    $total = array_sum($data);
    $out = '';
    foreach ($data as $k => $n) {
        $label = $labels[$k] ?? ($k === '' ? 'Accès direct / favori' : $k);
        $out .= '<div class="hbar"><span>' . e((string)$label) . '</span><div class="track"><div class="fill' . ($gold ? ' g' : '') . '" style="width:' . round(100 * $n / $max, 1) . '%"></div></div><b title="' . round(100 * $n / $total) . ' %">' . $n . '</b></div>';
    }
    return $out;
}
