<?php
/** Options payantes proposées lors de la réservation (aussi affichées dans la section Tarifs de l'accueil). */
require __DIR__ . '/_init.php';
require_perm('tarifs');

function option_input(array $r): array
{
    $t = fn($k, $max) => mb_substr(trim((string)($r[$k] ?? '')), 0, $max);
    return [
        'label' => $t('label', 120),
        'description' => $t('description', 300),
        'price' => max(0, round((float)str_replace(',', '.', (string)($r['price'] ?? '0')), 2)),
        'unit' => isset(OPTION_UNITS[$r['unit'] ?? '']) ? $r['unit'] : 'forfait',
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $pdo = db();
    $pdo->beginTransaction();
    $upd = $pdo->prepare('UPDATE booking_options SET label = ?, description = ?, price = ?, unit = ?, visible = ? WHERE id = ?');
    foreach ((array)($_POST['o'] ?? []) as $id => $row) {
        $v = option_input((array)$row);
        if ($v['label'] !== '') {
            $upd->execute([...array_values($v), empty($row['visible']) ? 0 : 1, (int)$id]);
        }
    }
    [$do, $id] = array_pad(explode(':', (string)($_POST['do'] ?? 'save'), 2), 2, '0');
    $id = (int)$id;
    $ids = $pdo->query('SELECT id FROM booking_options ORDER BY position, id')->fetchAll(PDO::FETCH_COLUMN);
    if ($do === 'add') {
        $v = option_input((array)($_POST['new'] ?? []));
        if ($v['label'] === '') {
            flash('Indiquez le nom de l’option.', 'err');
        } else {
            $pdo->prepare('INSERT INTO booking_options(label, description, price, unit, position) VALUES(?,?,?,?,?)')->execute([...array_values($v), count($ids) + 1]);
            flash('Option ajoutée.');
        }
    } elseif ($do === 'del' && in_array($id, $ids)) {
        $pdo->prepare('DELETE FROM booking_options WHERE id = ?')->execute([$id]);
        flash('Option supprimée (les réservations déjà payées ne sont pas modifiées).');
    } elseif (($do === 'up' || $do === 'down') && ($i = array_search($id, $ids)) !== false) {
        $j = $do === 'up' ? $i - 1 : $i + 1;
        if (isset($ids[$j])) {
            [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        }
        $pos = $pdo->prepare('UPDATE booking_options SET position = ? WHERE id = ?');
        foreach ($ids as $k => $iid) {
            $pos->execute([$k + 1, $iid]);
        }
    } else {
        flash('Options enregistrées.');
    }
    $pdo->commit();
    admin_log('Options payantes modifiées');
    redirect('options.php');
}

$options = bk_options(true);
admin_header('Options payantes', 'tarifs');

function option_fields(string $n, array $o): string
{
    return '<div class="grid" style="grid-template-columns:2fr 1fr 1fr;gap:10px">'
        . field($n . '[label]', 'Nom', $o['label'] ?? '')
        . field($n . '[price]', 'Prix HT (€)', isset($o['price']) ? rating_label((string)(float)$o['price']) : '', 'text', isset($o['price']) ? 'TTC : ' . money2(ttc((float)$o['price'])) : '')
        . field($n . '[unit]', 'Facturation', $o['unit'] ?? 'forfait', 'select', '', OPTION_UNITS)
        . '</div>' . field($n . '[description]', 'Description (facultatif)', $o['description'] ?? '');
}
?>
<h1>Tarifs & options</h1>
<?= tarifs_tabs('options') ?>
<p class="intro">Proposées lors de la réservation en ligne et affichées dans la section Tarifs de l’accueil. « Par personne » est multiplié par le nombre d’invités.</p>

<form method="post" class="card">
  <?= csrf_field() ?>
  <button name="do" value="save" tabindex="-1" aria-hidden="true" style="position:absolute;left:-9999px"></button>
  <h2>Options <span class="pill"><?= count($options) ?></span></h2>
  <?php foreach ($options as $i => $o): $n = 'o[' . $o['id'] . ']'; ?>
    <div class="item-row <?= $o['visible'] ? '' : 'hidden' ?>" style="grid-template-columns:1fr">
      <?= option_fields($n, $o) ?>
      <div class="ctrl">
        <label><input type="checkbox" name="<?= $n ?>[visible]" value="1" <?= $o['visible'] ? 'checked' : '' ?>> Proposée</label>
        <button class="btn light sm" name="do" value="up:<?= $o['id'] ?>" <?= $i === 0 ? 'disabled' : '' ?>>▲</button>
        <button class="btn light sm" name="do" value="down:<?= $o['id'] ?>" <?= $i === count($options) - 1 ? 'disabled' : '' ?>>▼</button>
        <button class="btn danger sm" name="do" value="del:<?= $o['id'] ?>" onclick="return confirm('Supprimer cette option ?')">✕ Supprimer</button>
      </div>
    </div>
  <?php endforeach; ?>
  <div class="sticky-save"><button class="btn gold" name="do" value="save">💾 Enregistrer</button></div>
</form>

<form method="post" class="card">
  <?= csrf_field() ?>
  <h2>Ajouter une option</h2>
  <?= option_fields('new', []) ?>
  <button class="btn" name="do" value="add">＋ Ajouter</button>
</form>
<?php admin_footer();
