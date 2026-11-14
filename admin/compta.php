<?php
/** Comptabilité : journal des factures, avoirs et retenues sur caution, totaux, export CSV et impression groupée. */
require __DIR__ . '/_init.php';
require_perm('stats');

// Période (mois en cours par défaut)
$tz = bk_now()->getTimezone();
$today = bk_now()->setTime(0, 0);
$presets = [
    'mois'      => ['Ce mois', $today->modify('first day of this month'), $today->modify('first day of next month')],
    'mois-1'    => ['Mois dernier', $today->modify('first day of last month'), $today->modify('first day of this month')],
    'trimestre' => ['Ce trimestre', $today->setDate((int)$today->format('Y'), intdiv((int)$today->format('n') - 1, 3) * 3 + 1, 1), $today->setDate((int)$today->format('Y'), intdiv((int)$today->format('n') - 1, 3) * 3 + 4, 1)],
    'annee'     => ['Cette année', $today->setDate((int)$today->format('Y'), 1, 1), $today->setDate((int)$today->format('Y') + 1, 1, 1)],
    'annee-1'   => ['Année dernière', $today->setDate((int)$today->format('Y') - 1, 1, 1), $today->setDate((int)$today->format('Y'), 1, 1)],
];
$p = (string)($_GET['p'] ?? 'mois');
$date = fn($k) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET[$k] ?? '')) ? DateTimeImmutable::createFromFormat('!Y-m-d', $_GET[$k], $tz) : false;
if ($p === 'perso' && ($f = $date('du')) && ($t = $date('au')) && $f <= $t) {
    [$from, $to] = [$f, $t->modify('+1 day')];
    $label = 'du ' . $f->format('d/m/Y') . ' au ' . $t->format('d/m/Y');
} else {
    $p = isset($presets[$p]) ? $p : 'mois';
    [$label, $from, $to] = $presets[$p];
    $label = mb_strtolower($label) . ' (' . $from->format('d/m/Y') . ' – ' . $to->modify('-1 day')->format('d/m/Y') . ')';
}
$entries = compta_entries($from, $to);
$slug = $from->format('Y-m-d') . '_' . $to->modify('-1 day')->format('Y-m-d');

if (isset($_GET['csv'])) {
    admin_log('Export comptable CSV ' . $slug);
    compta_csv($entries, 'comptabilite-' . $slug . '.csv');
    exit;
}

if (isset($_GET['print'])) {
    $docs = array_filter($entries, fn($en) => in_array($en['type'], ['facture', 'avoir'], true));
    ?><!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Factures et avoirs <?= e($slug) ?> — <?= e(s('bk_company_name')) ?></title>
<style><?= invoice_css() ?></style></head><body>
<div class="print"><a href="compta.php?<?= e(http_build_query(array_diff_key($_GET, ['print' => 1]))) ?>" style="margin-right:12px">← Retour</a><button onclick="window.print()">Imprimer / Enregistrer en PDF (<?= count($docs) ?> document<?= count($docs) > 1 ? 's' : '' ?>)</button></div>
<?php if (!$docs): ?><div class="doc"><p>Aucune facture ni aucun avoir sur cette période.</p></div><?php endif; ?>
<?php foreach ($docs as $en): ?>
<?= invoice_doc($en['b'], $en['type'] === 'avoir') ?>
<?php endforeach; ?>
</body></html>
<?php
    exit;
}

$tot = compta_totals($entries);
$qs = fn(array $extra) => '?' . http_build_query(array_merge(array_intersect_key($_GET, array_flip(['p', 'du', 'au'])), $extra));
admin_header('Comptabilité', 'compta');
?>
<h1>Comptabilité</h1>
<p class="intro">Factures, avoirs et retenues sur caution de la période, à transmettre à votre comptable.</p>

<nav class="tabs">
  <?php foreach ($presets as $k => [$l]): ?><a href="?p=<?= e($k) ?>" class="<?= $p === $k ? 'on' : '' ?>"><?= e($l) ?></a><?php endforeach; ?>
</nav>
<form method="get" class="card" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;padding:14px 18px">
  <input type="hidden" name="p" value="perso">
  <label class="field" style="margin:0;flex:1 1 150px"><span>Du</span><input type="date" name="du" value="<?= e($from->format('Y-m-d')) ?>"></label>
  <label class="field" style="margin:0;flex:1 1 150px"><span>Au (inclus)</span><input type="date" name="au" value="<?= e($to->modify('-1 day')->format('Y-m-d')) ?>"></label>
  <button class="btn light">Afficher</button>
</form>

<h2 style="margin:22px 0 12px">Période : <?= e($label) ?></h2>
<div class="kpis">
  <div class="kpi"><b><?= money2($tot['all']['net_ttc']) ?></b><span>Chiffre d’affaires TTC (avoirs déduits)</span></div>
  <div class="kpi"><b><?= money2($tot['all']['net_ht']) ?></b><span>Chiffre d’affaires HT</span></div>
  <div class="kpi"><b><?= money2($tot['all']['net_tva']) ?></b><span>TVA collectée</span></div>
  <div class="kpi"><b><?= (int)$tot['all']['factures'] ?></b><span>facture(s)<?= $tot['all']['avoirs_ttc'] ? ' · avoirs ' . money2($tot['all']['avoirs_ttc']) : '' ?><?= $tot['all']['cautions'] ? ' · retenues caution ' . money2($tot['all']['cautions']) : '' ?></span></div>
</div>

<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px">
  <a class="btn" href="<?= e($qs(['csv' => 1])) ?>">⬇ Exporter le journal (Excel / CSV)</a>
  <a class="btn light" href="<?= e($qs(['print' => 1])) ?>" target="_blank">🖨 Toutes les factures et avoirs (PDF)</a>
</div>

<div class="grid g2" style="align-items:start">
  <div class="card">
    <h2>Par mois</h2>
    <?php if (!$tot['months']): ?><p class="muted">Aucune écriture.</p><?php else: ?>
    <div style="overflow-x:auto"><table>
      <tr><th>Mois</th><th class="num">Factures</th><th class="num">Avoirs TTC</th><th class="num">CA HT</th><th class="num">TVA</th><th class="num">CA TTC</th></tr>
      <?php foreach ($tot['months'] as $m => $t): $d = DateTimeImmutable::createFromFormat('!Y-m', $m); ?>
      <tr><td><?= e(ucfirst(preg_replace('/^1er /', '', fr_date($d, false)))) ?></td><td class="num"><?= (int)$t['factures'] ?></td><td class="num"><?= $t['avoirs_ttc'] ? money2($t['avoirs_ttc']) : '—' ?></td>
        <td class="num"><?= money2($t['net_ht']) ?></td><td class="num"><?= money2($t['net_tva']) ?></td><td class="num"><strong><?= money2($t['net_ttc']) ?></strong></td></tr>
      <?php endforeach; ?>
    </table></div>
    <?php endif; ?>
  </div>
  <div class="card">
    <h2>Encaissements par moyen de paiement</h2>
    <?php if (!$tot['by']): ?><p class="muted">Aucun encaissement.</p><?php else: ?>
    <table>
      <?php foreach ($tot['by'] as $k => $v): ?><tr><td><?= e(ucfirst($k)) ?></td><td class="num"><?= money2($v) ?></td></tr><?php endforeach; ?>
      <tr><td><strong>Total</strong></td><td class="num"><strong><?= money2(array_sum($tot['by'])) ?></strong></td></tr>
    </table>
    <p class="muted" style="font-size:.85rem">Remboursements déduits. Les frais Stripe et PayPal ne sont pas inclus : ils figurent sur les relevés de ces services. « Bon cadeau » = part des locations réglée par bon (déjà encaissée lors de la vente du bon).</p>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <h2>Journal (<?= count($entries) ?> écriture<?= count($entries) > 1 ? 's' : '' ?>)</h2>
  <?php if (!$entries): ?><p class="muted">Aucune facture, aucun avoir ni aucune retenue sur caution sur cette période.</p><?php else: ?>
  <div style="overflow-x:auto"><table>
    <tr><th>Date</th><th>Document</th><th>Client</th><th class="num">HT</th><th class="num">TVA</th><th class="num">TTC</th><th>Paiement</th></tr>
    <?php foreach ($entries as $en): $b = $en['b']; ?>
    <tr>
      <td><?= e(bk_dt($en['date'])->format('d/m/Y')) ?></td>
      <td><?php if ($en['type'] === 'caution'): ?>Retenue sur caution<br><span class="muted" style="font-size:.85rem"><?= e(mb_strimwidth((string)($en['note'] ?? ''), 0, 60, '…')) ?></span>
        <?php else: ?><a href="../facture.php?id=<?= (int)$b['id'] ?><?= $en['type'] === 'avoir' ? '&amp;avoir=1' : '' ?>" target="_blank"><?= e(COMPTA_TYPES[$en['type']] . ' ' . $en['number']) ?></a><?php endif; ?></td>
      <td><a href="reservation.php?id=<?= (int)$b['id'] ?>"><?= e(compta_client($b)) ?></a><br><span class="muted" style="font-size:.85rem"><?= e($b['ref']) ?> · le <?= e(bk_dt($b['start_at'])->format('d/m/Y')) ?></span></td>
<?php if ($en['type'] === 'solde'): ?>
      <td colspan="3" class="num muted" style="font-size:.88rem">encaissé <strong style="color:var(--navy)"><?= money2($en['paid']) ?></strong> (facture déjà comptée)</td>
<?php else: ?>
      <td class="num"><?= money2($en['ht']) ?></td><td class="num"><?= money2($en['tva']) ?></td><td class="num"><strong><?= money2($en['ttc']) ?></strong></td>
<?php endif; ?>
      <td><?= $en['provider'] !== '' ? e(ucfirst(balance_method_label($en['provider']))) : '' ?><?= $en['type'] === 'facture' && bk_is_acompte($b) ? '<br><span class="muted" style="font-size:.82rem">acompte ' . money2($en['paid']) . '</span>' : '' ?><?= abs($en['gift']) > 0 ? ($en['provider'] !== '' ? ' + ' : '') . 'bon cadeau ' . money2($en['gift']) : '' ?></td>
    </tr>
    <?php endforeach; ?>
  </table></div>
  <?php endif; ?>
  <p class="muted" style="font-size:.85rem;margin-top:12px">Les retenues sur caution sont des indemnités pour dégradation : elles n’ont pas de facture et sont en principe hors champ de la TVA (à confirmer avec votre comptable).</p>
</div>
<?php admin_footer();
