<?php
/** Édition des listes (cartes, équipements, tarifs…) : ajout, modification, ordre, masquage. */
require __DIR__ . '/_init.php';
require_perm('contenu');

$groups = ITEM_GROUPS;
$g = (string)($_GET['g'] ?? 'hero_facts');
if (!isset($groups[$g])) {
    $g = 'hero_facts';
}
$conf = $groups[$g];
$cols = $conf['fields'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $pdo = db();
    $pdo->beginTransaction();

    // 1. Enregistre toujours les modifications en cours
    $upd = $pdo->prepare('UPDATE items SET icon = ?, title = ?, text = ?, value = ?, visible = ? WHERE id = ? AND grp = ?');
    foreach ((array)($_POST['items'] ?? []) as $id => $row) {
        $val = fn($k) => mb_substr(trim(str_replace("\r\n", "\n", (string)($row[$k] ?? ''))), 0, 2000);
        $upd->execute([$val('icon'), $val('title'), $val('text'), $val('value'), empty($row['visible']) ? 0 : 1, (int)$id, $g]);
    }

    // 2. Action demandée
    [$do, $id] = array_pad(explode(':', (string)($_POST['do'] ?? 'save'), 2), 2, '0');
    $id = (int)$id;
    $list = $pdo->prepare('SELECT id FROM items WHERE grp = ? ORDER BY position, id');
    $list->execute([$g]);
    $ids = $list->fetchAll(PDO::FETCH_COLUMN);

    if ($do === 'add') {
        $n = $_POST['new'] ?? [];
        $v = fn($k) => mb_substr(trim((string)($n[$k] ?? '')), 0, 2000);
        if ($v('title') . $v('text') . $v('value') === '') {
            flash('Remplissez au moins un champ pour ajouter un élément.', 'err');
        } else {
            $pdo->prepare('INSERT INTO items(grp, icon, title, text, value, position) VALUES(?,?,?,?,?,?)')
                ->execute([$g, $v('icon'), $v('title'), $v('text'), $v('value'), count($ids) + 1]);
            flash('Élément ajouté.');
        }
    } elseif ($do === 'del' && in_array($id, $ids)) {
        $pdo->prepare('DELETE FROM items WHERE id = ? AND grp = ?')->execute([$id, $g]);
        flash('Élément supprimé.');
    } elseif (($do === 'up' || $do === 'down') && ($i = array_search($id, $ids)) !== false) {
        $j = $do === 'up' ? $i - 1 : $i + 1;
        if (isset($ids[$j])) {
            [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        }
        $pos = $pdo->prepare('UPDATE items SET position = ? WHERE id = ?');
        foreach ($ids as $k => $iid) {
            $pos->execute([$k + 1, $iid]);
        }
    } else {
        flash('Modifications enregistrées.');
    }
    $pdo->commit();
    redirect('listes.php?g=' . urlencode($g));
}

$items = items($g, true);
$isTarif = $conf['menu'] === 'tarifs';
admin_header($conf['label'], $isTarif ? 'tarifs' : 'contenus');

$template = implode(' ', array_map(fn($k) => $k === 'icon' ? '70px' : ($k === 'text' ? '2fr' : '1fr'), array_keys($cols))) . ' auto';
?>
<h1><?= $isTarif ? 'Tarifs' : 'Listes & cartes' ?></h1>
<p class="intro"><?= $isTarif
    ? 'Modifiez les prix affichés sur le site. Le prix est un texte libre : « 250 € », « 2 € HT / personne », « Gratuit »…'
    : 'Les blocs répétés de la page d’accueil. Utilisez ▲▼ pour changer l’ordre, décochez « Visible » pour masquer sans supprimer.' ?></p>

<div class="layout">
  <aside class="side">
    <h3>Tarifs</h3>
    <?php foreach ($groups as $key => $gr) if ($gr['menu'] === 'tarifs'): ?>
      <a href="?g=<?= e($key) ?>" class="<?= $key === $g ? 'on' : '' ?>"><?= e($gr['label']) ?></a>
    <?php endif; ?>
    <h3>Contenus</h3>
    <?php foreach ($groups as $key => $gr) if ($gr['menu'] === 'contenus'): ?>
      <a href="?g=<?= e($key) ?>" class="<?= $key === $g ? 'on' : '' ?>"><?= e($gr['label']) ?></a>
    <?php endif; ?>
  </aside>

  <form method="post" class="card">
    <?= csrf_field() ?>
    <button name="do" value="save" tabindex="-1" aria-hidden="true" style="position:absolute;left:-9999px"></button>
    <h2><?= e($conf['label']) ?> <span class="pill"><?= count($items) ?></span></h2>
    <?php if ($isTarif): ?><p class="muted" style="margin-top:-6px">Les titres de cette section (« Tarifs de location », mention HT…) se modifient dans <a href="textes.php?s=tarifs">Textes › Tarifs</a>.</p><?php endif; ?>

    <?php if (!$items): ?><p class="muted">Aucun élément pour l’instant.</p><?php endif; ?>
    <?php foreach ($items as $i => $it): $n = 'items[' . $it['id'] . ']'; ?>
      <div class="item-row <?= $it['visible'] ? '' : 'hidden' ?>" style="grid-template-columns:<?= $template ?>">
        <?php foreach ($cols as $k => $label): ?>
          <label class="field" style="margin:0"><span><?= e($label) ?></span>
            <?php if ($k === 'text' && mb_strlen($it[$k]) > 60): ?>
              <textarea name="<?= $n ?>[<?= $k ?>]" rows="2"><?= e($it[$k]) ?></textarea>
            <?php else: ?>
              <input type="text" name="<?= $n ?>[<?= $k ?>]" value="<?= e($it[$k]) ?>" class="<?= $k === 'icon' ? 'icon-in' : '' ?>">
            <?php endif; ?>
          </label>
        <?php endforeach; ?>
        <div class="ctrl" style="padding-top:24px">
          <label title="Afficher sur le site"><input type="checkbox" name="<?= $n ?>[visible]" value="1" <?= $it['visible'] ? 'checked' : '' ?>> Visible</label>
          <button class="btn light sm" name="do" value="up:<?= $it['id'] ?>" <?= $i === 0 ? 'disabled' : '' ?> title="Monter">▲</button>
          <button class="btn light sm" name="do" value="down:<?= $it['id'] ?>" <?= $i === count($items) - 1 ? 'disabled' : '' ?> title="Descendre">▼</button>
          <button class="btn danger sm" name="do" value="del:<?= $it['id'] ?>" onclick="return confirm('Supprimer cet élément ?')" title="Supprimer">✕</button>
        </div>
      </div>
    <?php endforeach; ?>

    <div class="sticky-save"><button class="btn gold" name="do" value="save">💾 Enregistrer les modifications</button></div>

    <h2 style="margin-top:26px">Ajouter un élément</h2>
    <div class="item-row" style="grid-template-columns:<?= $template ?>">
      <?php foreach ($cols as $k => $label): ?>
        <label class="field" style="margin:0"><span><?= e($label) ?></span><input type="text" name="new[<?= $k ?>]" class="<?= $k === 'icon' ? 'icon-in' : '' ?>"></label>
      <?php endforeach; ?>
      <div class="ctrl" style="padding-top:24px"><button class="btn" name="do" value="add">＋ Ajouter</button></div>
    </div>
  </form>
</div>
<?php admin_footer();
