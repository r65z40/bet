<?php
/** Dates spéciales : réveillon, fêtes, haute saison… (majoration en % ou prix de journée fixe). */
require __DIR__ . '/_init.php';
require_perm('tarifs');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $do = (string)($_POST['do'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    if ($do === 'add') {
        $label = mb_substr(trim((string)($_POST['label'] ?? '')), 0, 80);
        $from = (string)($_POST['date_from'] ?? '');
        $to = (string)($_POST['date_to'] ?? '') ?: $from;
        $kind = ($_POST['kind'] ?? '') === 'fixed' ? 'fixed' : 'percent';
        $value = round((float)str_replace(',', '.', (string)($_POST['value'] ?? '0')), 2);
        $ok = $label !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) && $to >= $from;
        if (!$ok) {
            flash('Indiquez un nom et des dates valides (la fin doit être après le début).', 'err');
        } elseif ($kind === 'fixed' && $value <= 0 || $kind === 'percent' && ($value < -90 || $value > 500)) {
            flash($kind === 'fixed' ? 'Indiquez le prix de la journée HT.' : 'Le pourcentage doit être compris entre −90 et +500.', 'err');
        } else {
            // Prix fixe saisi TTC dans le formulaire (ce que voit le client), stocké HT
            $stored = $kind === 'fixed' ? round($value / bk_vat_factor(), 2) : $value;
            db()->prepare('INSERT INTO special_prices(label, date_from, date_to, kind, value) VALUES(?,?,?,?,?)')->execute([$label, $from, $to, $kind, $stored]);
            admin_log('Date spéciale ajoutée : ' . $label);
            flash('Date spéciale ajoutée.');
        }
    } elseif ($do === 'toggle') {
        db()->prepare('UPDATE special_prices SET active = 1 - active WHERE id = ?')->execute([$id]);
    } elseif ($do === 'delete') {
        db()->prepare('DELETE FROM special_prices WHERE id = ?')->execute([$id]);
        admin_log('Date spéciale supprimée');
        flash('Date spéciale supprimée (les réservations déjà payées gardent leur prix).');
    }
    redirect('tarifs-speciaux.php');
}

$rows = db()->query('SELECT * FROM special_prices ORDER BY date_to < date(\'now\'), date_from')->fetchAll();
admin_header('Dates spéciales', 'tarifs');
?>
<h1>Tarifs & options</h1>
<?= tarifs_tabs('speciaux') ?>
<p class="intro">Réveillon, fêtes, haute saison… Le prix de la journée de ces dates est majoré (ou réduit) ; la location à l’heure suit la même proportion. Les prix affichés sur le calendrier en tiennent compte (★).</p>

<form method="post" class="card">
  <?= csrf_field() ?>
  <h2>Ajouter une date ou une période</h2>
  <div class="grid g3">
    <?= field('label', 'Nom (visible par le client)', '') ?>
    <?= field('date_from', 'Du', '', 'date') ?>
    <?= field('date_to', 'Au (inclus, vide = un seul jour)', '', 'date') ?>
  </div>
  <div class="grid g2">
    <?= field('kind', 'Type', 'percent', 'select', '', ['percent' => 'Majoration (ou remise) en % du prix habituel', 'fixed' => 'Prix fixe de la journée (24 h), TTC']) ?>
    <?= field('value', 'Valeur (ex. 50 pour +50 %, −20 pour −20 %, ou 600 € TTC)', '') ?>
  </div>
  <button class="btn gold" name="do" value="add">＋ Ajouter</button>
</form>

<div class="card">
  <h2>Dates spéciales</h2>
  <?php if (!$rows): ?><p class="muted">Aucune pour l’instant.</p><?php else: ?>
  <div style="overflow-x:auto"><table>
    <tr><th>Nom</th><th>Dates</th><th>Tarif</th><th></th></tr>
    <?php foreach ($rows as $r): $past = $r['date_to'] < bk_now()->format('Y-m-d'); ?>
      <tr style="<?= $r['active'] && !$past ? '' : 'opacity:.5' ?>">
        <td><strong><?= e($r['label']) ?></strong><?= $past ? ' <span class="pill">passée</span>' : '' ?><?= $r['active'] ? '' : ' <span class="pill">désactivée</span>' ?></td>
        <td><?= e(bk_dt($r['date_from'])->format('d/m/Y')) ?><?= $r['date_to'] !== $r['date_from'] ? ' → ' . e(bk_dt($r['date_to'])->format('d/m/Y')) : '' ?></td>
        <td><?= e(special_label($r)) ?></td>
        <td style="white-space:nowrap"><form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <button class="btn light sm" name="do" value="toggle"><?= $r['active'] ? 'Désactiver' : 'Activer' ?></button>
          <button class="btn danger sm" name="do" value="delete" onclick="return confirm('Supprimer cette date spéciale ?')">✕</button></form></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
  <?php endif; ?>
</div>
<?php admin_footer();
