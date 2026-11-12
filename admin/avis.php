<?php
/** Gestion des avis clients (Google, 1001Salles…) affichés sur la page d'accueil. */
require __DIR__ . '/_init.php';
require_perm('contenu');

/** Nettoie un avis envoyé par le formulaire. */
function review_input(array $r): array
{
    $t = fn($k, $max = 200) => mb_substr(trim(str_replace("\r\n", "\n", (string)($r[$k] ?? ''))), 0, $max);
    return [
        'author' => $t('author', 80),
        'source' => isset(REVIEW_SOURCES[$r['source'] ?? '']) ? $r['source'] : 'google',
        'rating' => max(1, min(5, (int)($r['rating'] ?? 5))),
        'review_date' => $t('review_date', 40),
        'event' => $t('event', 80),
        'text' => $t('text', 3000),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $pdo = db();
    $pdo->beginTransaction();

    $upd = $pdo->prepare('UPDATE reviews SET author=?, source=?, rating=?, review_date=?, event=?, text=?, visible=? WHERE id=?');
    foreach ((array)($_POST['r'] ?? []) as $id => $row) {
        $v = review_input((array)$row);
        if ($v['author'] === '' || $v['text'] === '') {
            continue; // on ne vide pas un avis par erreur
        }
        $upd->execute([...array_values($v), empty($row['visible']) ? 0 : 1, (int)$id]);
    }

    [$do, $id] = array_pad(explode(':', (string)($_POST['do'] ?? 'save'), 2), 2, '0');
    $id = (int)$id;
    $ids = $pdo->query('SELECT id FROM reviews ORDER BY position, id')->fetchAll(PDO::FETCH_COLUMN);

    if ($do === 'add') {
        $v = review_input((array)($_POST['new'] ?? []));
        if ($v['author'] === '' || $v['text'] === '') {
            flash('Indiquez au moins le nom et le texte de l’avis.', 'err');
        } else {
            // les nouveaux avis apparaissent en premier
            $pdo->exec('UPDATE reviews SET position = position + 1');
            $pdo->prepare('INSERT INTO reviews(author, source, rating, review_date, event, text, position) VALUES(?,?,?,?,?,?,0)')
                ->execute(array_values($v));
            flash('Avis ajouté en tête de liste.');
        }
    } elseif ($do === 'del' && in_array($id, $ids)) {
        $pdo->prepare('DELETE FROM reviews WHERE id = ?')->execute([$id]);
        flash('Avis supprimé.');
    } elseif (($do === 'up' || $do === 'down') && ($i = array_search($id, $ids)) !== false) {
        $j = $do === 'up' ? $i - 1 : $i + 1;
        if (isset($ids[$j])) {
            [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        }
        $pos = $pdo->prepare('UPDATE reviews SET position = ? WHERE id = ?');
        foreach ($ids as $k => $iid) {
            $pos->execute([$k + 1, $iid]);
        }
    } else {
        flash('Avis enregistrés.');
    }
    $pdo->commit();
    redirect('avis.php' . ($id ? '#av' . $id : ''));
}

$reviews = reviews(true);
admin_header('Avis clients', 'avis');

function review_fields(string $n, array $r): string
{
    ob_start(); ?>
    <div class="grid g4">
      <?= field($n . '[author]', 'Nom affiché', $r['author'] ?? '') ?>
      <?= field($n . '[source]', 'Source', $r['source'] ?? 'google', 'select', '', REVIEW_SOURCES) ?>
      <?= field($n . '[rating]', 'Note', (string)($r['rating'] ?? 5), 'select', '', [5 => '★★★★★ 5/5', 4 => '★★★★ 4/5', 3 => '★★★ 3/5', 2 => '★★ 2/5', 1 => '★ 1/5']) ?>
      <?= field($n . '[review_date]', 'Date (ex. « juin 2026 »)', $r['review_date'] ?? '') ?>
    </div>
    <?= field($n . '[event]', 'Événement (facultatif, ex. « Mariage », « Séminaire »)', $r['event'] ?? '') ?>
    <?= field($n . '[text]', 'Texte de l’avis', $r['text'] ?? '', 'textarea') ?>
    <?php return ob_get_clean();
}
?>
<h1>Avis clients</h1>
<p class="intro">Copiez ici vos meilleurs avis Google et 1001Salles : ils s’affichent dans la section « Avis » de la page d’accueil.
  Les notes globales et liens se règlent dans <a href="textes.php?s=avis">Textes › Avis clients</a>.</p>
<div class="hint">Recopiez les avis tels quels (prénom + initiale du nom, comme sur Google). N’inventez ni ne modifiez d’avis : c’est interdit par le Code de la consommation.</div>

<form method="post" class="card">
  <?= csrf_field() ?>
  <h2>Ajouter un avis</h2>
  <?= review_fields('new', []) ?>
  <button class="btn gold" name="do" value="add">＋ Ajouter l’avis</button>
</form>

<form method="post" class="card">
  <?= csrf_field() ?>
  <button name="do" value="save" tabindex="-1" aria-hidden="true" style="position:absolute;left:-9999px"></button>
  <h2>Avis publiés <span class="pill"><?= count($reviews) ?></span></h2>
  <?php if (!$reviews): ?><p class="muted">Aucun avis pour l’instant : la section « Avis » s’affiche sur le site dès qu’un avis ou une note globale est renseigné.</p><?php endif; ?>
  <?php foreach ($reviews as $i => $r): $n = 'r[' . $r['id'] . ']'; ?>
    <div class="item-row <?= $r['visible'] ? '' : 'hidden' ?>" id="av<?= $r['id'] ?>" style="grid-template-columns:1fr">
      <?= review_fields($n, $r) ?>
      <div class="ctrl">
        <label><input type="checkbox" name="<?= $n ?>[visible]" value="1" <?= $r['visible'] ? 'checked' : '' ?>> Visible sur le site</label>
        <button class="btn light sm" name="do" value="up:<?= $r['id'] ?>" <?= $i === 0 ? 'disabled' : '' ?>>▲ Monter</button>
        <button class="btn light sm" name="do" value="down:<?= $r['id'] ?>" <?= $i === count($reviews) - 1 ? 'disabled' : '' ?>>▼ Descendre</button>
        <button class="btn danger sm" name="do" value="del:<?= $r['id'] ?>" onclick="return confirm('Supprimer cet avis ?')">✕ Supprimer</button>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if ($reviews): ?><div class="sticky-save"><button class="btn gold" name="do" value="save">💾 Enregistrer</button></div><?php endif; ?>
</form>
<?php admin_footer();
