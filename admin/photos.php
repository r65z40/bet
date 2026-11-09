<?php
/** Gestion de la galerie : envoi, légende, ordre, masquage, suppression, photo d'accueil, plan. */
require __DIR__ . '/_init.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $pdo = db();
    $do = (string)($_POST['do'] ?? 'save');

    if ($do === 'upload') {
        $files = isset($_FILES['photos']) ? normalize_files($_FILES['photos']) : [];
        $pos = (int)$pdo->query('SELECT COALESCE(MAX(position), 0) FROM photos')->fetchColumn();
        $count = (int)$pdo->query('SELECT COUNT(*) FROM photos')->fetchColumn();
        $ok = 0;
        foreach ($files as $f) {
            if ($f['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            try {
                [$name, $w, $h] = store_uploaded_image($f);
                $pdo->prepare('INSERT INTO photos(filename, alt, width, height, position) VALUES(?,?,?,?,?)')
                    ->execute([$name, s('site_name') . ' à Appoigny près d’Auxerre — photo ' . (++$count), $w, $h, ++$pos]);
                $ok++;
            } catch (RuntimeException $ex) {
                flash($ex->getMessage(), 'err');
            }
        }
        if ($ok) {
            flash($ok . ' photo(s) ajoutée(s) à la fin de la galerie.');
        } elseif (!$files || $files[0]['error'] === UPLOAD_ERR_NO_FILE) {
            flash('Choisissez au moins une photo.', 'err');
        }
        redirect('photos.php');
    }

    if ($do === 'plan') {
        try {
            [$name] = store_uploaded_image($_FILES['plan'] ?? []);
            $old = s('plan_image');
            save_setting('plan_image', $name);
            if ($old !== '') {
                delete_photo_file($old);
            }
            flash('Plan de la salle remplacé.');
        } catch (RuntimeException $ex) {
            flash($ex->getMessage(), 'err');
        }
        redirect('photos.php#plan');
    }

    // Formulaire de la galerie : on enregistre légendes / visibilité puis l'action
    $pdo->beginTransaction();
    $upd = $pdo->prepare('UPDATE photos SET alt = ?, visible = ? WHERE id = ?');
    foreach ((array)($_POST['p'] ?? []) as $id => $row) {
        $upd->execute([mb_substr(trim((string)($row['alt'] ?? '')), 0, 300), empty($row['visible']) ? 0 : 1, (int)$id]);
    }
    [$action, $id] = array_pad(explode(':', $do, 2), 2, '0');
    $id = (int)$id;
    $ids = $pdo->query('SELECT id FROM photos ORDER BY position, id')->fetchAll(PDO::FETCH_COLUMN);
    $ph = $pdo->prepare('SELECT * FROM photos WHERE id = ?');
    $ph->execute([$id]);
    $photo = $ph->fetch();

    if ($action === 'del' && $photo) {
        $pdo->prepare('DELETE FROM photos WHERE id = ?')->execute([$id]);
        delete_photo_file($photo['filename']);
        if (s('hero_photo') === $photo['filename']) {
            save_setting('hero_photo', '');
        }
        flash('Photo supprimée.');
    } elseif ($action === 'hero' && $photo) {
        save_setting('hero_photo', $photo['filename']);
        flash('Image d’accueil modifiée.');
    } elseif (in_array($action, ['up', 'down', 'first', 'last'], true) && ($i = array_search($id, $ids)) !== false) {
        array_splice($ids, $i, 1);
        $target = match ($action) {
            'up' => max(0, $i - 1), 'down' => min(count($ids), $i + 1), 'first' => 0, 'last' => count($ids),
        };
        array_splice($ids, $target, 0, [$id]);
        $pos = $pdo->prepare('UPDATE photos SET position = ? WHERE id = ?');
        foreach ($ids as $k => $iid) {
            $pos->execute([$k + 1, $iid]);
        }
    } else {
        flash('Modifications enregistrées.');
    }
    $pdo->commit();
    redirect('photos.php' . ($id ? '#ph' . $id : ''));
}

$photos = photos(true);
$hero = s('hero_photo');
$maxUpload = max_upload_bytes();
admin_header('Photos', 'photos');
?>
<h1>Photos</h1>
<p class="intro">Galerie de la page d’accueil, image du bandeau d’accueil et plan de la salle.</p>

<form method="post" enctype="multipart/form-data" class="card">
  <?= csrf_field() ?>
  <h2>Ajouter des photos</h2>
  <div class="drop">
    <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required>
    <p class="muted" style="margin:8px 0 0">JPG, PNG ou WebP · <?= round($maxUpload / 1048576) ?> Mo max par photo · plusieurs fichiers possibles.<br>Conseil : photos en paysage, environ 1600 px de large.</p>
  </div>
  <div style="margin-top:14px"><button class="btn gold" name="do" value="upload">⬆ Envoyer</button></div>
</form>

<form method="post" class="card">
  <?= csrf_field() ?>
  <button name="do" value="save" tabindex="-1" aria-hidden="true" style="position:absolute;left:-9999px"></button>
  <h2>Galerie <span class="pill"><?= count($photos) ?> photos</span></h2>
  <p class="muted" style="margin-top:-6px">La légende sert aux malvoyants et au référencement Google. ★ = image de fond du bandeau d’accueil.</p>
  <div class="photos">
    <?php foreach ($photos as $i => $p): $n = 'p[' . $p['id'] . ']'; ?>
      <div class="photo <?= $p['visible'] ? '' : 'hidden' ?>" id="ph<?= $p['id'] ?>">
        <div class="thumb">
          <a href="../<?= e(photo_src($p['filename'])) ?>" target="_blank"><img src="../<?= e(photo_src($p['filename'])) ?>" alt="" loading="lazy"></a>
          <span class="badge">
            <span class="pill">#<?= $i + 1 ?></span>
            <?php if ($p['filename'] === $hero): ?><span class="pill gold">★ Accueil</span><?php endif; ?>
          </span>
        </div>
        <div class="body">
          <textarea name="<?= $n ?>[alt]" rows="2" aria-label="Légende"><?= e($p['alt']) ?></textarea>
          <label><input type="checkbox" name="<?= $n ?>[visible]" value="1" <?= $p['visible'] ? 'checked' : '' ?>> Visible sur le site</label>
          <div class="ctrl">
            <button class="btn light sm" name="do" value="first:<?= $p['id'] ?>" title="Mettre en premier" <?= $i === 0 ? 'disabled' : '' ?>>⇤</button>
            <button class="btn light sm" name="do" value="up:<?= $p['id'] ?>" title="Avancer" <?= $i === 0 ? 'disabled' : '' ?>>◀</button>
            <button class="btn light sm" name="do" value="down:<?= $p['id'] ?>" title="Reculer" <?= $i === count($photos) - 1 ? 'disabled' : '' ?>>▶</button>
            <button class="btn light sm" name="do" value="last:<?= $p['id'] ?>" title="Mettre en dernier" <?= $i === count($photos) - 1 ? 'disabled' : '' ?>>⇥</button>
            <?php if ($p['filename'] !== $hero): ?><button class="btn light sm" name="do" value="hero:<?= $p['id'] ?>" title="Utiliser comme image d’accueil">★</button><?php endif; ?>
            <button class="btn danger sm" name="do" value="del:<?= $p['id'] ?>" onclick="return confirm('Supprimer définitivement cette photo ?')" title="Supprimer">🗑</button>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="sticky-save"><button class="btn gold" name="do" value="save">💾 Enregistrer les légendes</button></div>
</form>

<form method="post" enctype="multipart/form-data" class="card" id="plan">
  <?= csrf_field() ?>
  <h2>Plan de la salle</h2>
  <div class="grid g2" style="align-items:center">
    <a href="../<?= e(plan_image()) ?>" target="_blank"><img src="../<?= e(plan_image()) ?>" alt="Plan actuel" style="width:100%;border-radius:10px;border:1px solid var(--line)"></a>
    <div>
      <p class="muted">Image affichée sur la page « Plan de la salle ».</p>
      <input type="file" name="plan" accept="image/jpeg,image/png,image/webp" required>
      <div style="margin-top:12px"><button class="btn" name="do" value="plan">Remplacer le plan</button></div>
    </div>
  </div>
</form>
<?php admin_footer();
