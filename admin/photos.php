<?php
/** Gestion de la galerie : envoi, légende, ordre, masquage, suppression, photo d'accueil, plan. */
require __DIR__ . '/_init.php';
require_perm('contenu');

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

    // Photos de la page d'accueil (sélecteur)
    if ($do === 'home_photos') {
        $ids = array_map('intval', $pdo->query('SELECT id FROM photos')->fetchAll(PDO::FETCH_COLUMN));
        $slots = home_photo_slots();
        foreach ((array)($_POST['slot'] ?? []) as $key => $val) {
            if (!isset($slots[$key])) continue;
            $refs = [];
            foreach (explode(',', (string)$val) as $r) {
                if (preg_match('/^p(\d+)$/', trim($r), $m) && in_array((int)$m[1], $ids, true) && !in_array('p' . $m[1], $refs, true)) $refs[] = 'p' . $m[1];
            }
            home_photo_slot_save($key, implode(',', array_slice($refs, 0, $slots[$key][2])));
        }
        settings(true);
        admin_log('Photos de la page d’accueil modifiées');
        flash('Photos de la page d’accueil enregistrées.');
        redirect('photos.php#accueil');
    }

    // Formulaire de la galerie : on enregistre légendes / visibilité puis l'action
    $pdo->beginTransaction();
    $upd = $pdo->prepare('UPDATE photos SET alt = ?, visible = ?, category = ? WHERE id = ?');
    foreach ((array)($_POST['p'] ?? []) as $id => $row) {
        $cat = (string)($row['category'] ?? '');
        $upd->execute([mb_substr(trim((string)($row['alt'] ?? '')), 0, 300), empty($row['visible']) ? 0 : 1, isset(PHOTO_CATEGORIES[$cat]) ? $cat : '', (int)$id]);
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
        make_variants($photo['filename']);
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

$generated = ensure_all_variants();
if ($generated) {
    flash($generated . ' photo(s) optimisée(s) en WebP (vignettes + grand format).');
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

<?php
$allPhotos = photos(true);
$pickData = array_map(fn($p, $i) => ['ref' => 'p' . $p['id'], 'n' => $i + 1, 'src' => '../' . (variant_src($p['filename'], 'thumb') ?: photo_src($p['filename'])),
    'cat' => PHOTO_CATEGORIES[$p['category'] ?? ''] ?? '', 'hidden' => !(int)$p['visible'], 'alt' => $p['alt']], $allPhotos, array_keys($allPhotos));
$slots = home_photo_slots();
?>
<form method="post" class="card" id="accueil">
  <?= csrf_field() ?>
  <h2>Photos de la page d’accueil</h2>
  <p class="muted" style="margin-top:-6px">Cliquez sur « Choisir » puis sur les photos voulues. Les photos restent les mêmes même si vous changez l’ordre de la galerie.</p>
  <div class="slots">
    <?php foreach ($slots as $key => [$label, $multi, $max, $cur, $help]): $sel = $multi ? home_photo_refs($cur) : array_filter([home_photo_ref($cur)]); ?>
    <div class="slot" data-key="<?= e($key) ?>" data-multi="<?= $multi ? 1 : 0 ?>" data-max="<?= (int)$max ?>">
      <div class="slot-head"><strong><?= e($label) ?></strong><?php if ($help !== ''): ?><span class="muted"><?= e(mb_strimwidth($help, 0, 110, '…')) ?></span><?php endif; ?></div>
      <div class="slot-pics"><?php foreach ($sel as $ph): ?><img src="../<?= e(variant_src($ph['filename'], 'thumb') ?: photo_src($ph['filename'])) ?>" alt=""><?php endforeach; ?><?php if (!$sel): ?><span class="muted slot-empty"><?= $multi ? 'Automatique' : 'Aucune photo' ?></span><?php endif; ?></div>
      <input type="hidden" name="slot[<?= e($key) ?>]" value="<?= e(implode(',', array_map(fn($p) => 'p' . $p['id'], $sel))) ?>">
      <button type="button" class="btn light sm slot-btn">Choisir<?= $multi ? ' (jusqu’à ' . (int)$max . ')' : '' ?></button>
    </div>
    <?php endforeach; ?>
  </div>
  <div style="margin-top:14px"><button class="btn gold" name="do" value="home_photos">💾 Enregistrer les photos de l’accueil</button></div>
</form>

<dialog id="picker" class="picker">
  <form method="dialog" class="picker-box">
    <div class="picker-top"><strong id="pkTitle">Choisir</strong><span class="muted" id="pkHelp"></span></div>
    <div class="picker-grid" id="pkGrid"></div>
    <div class="picker-actions">
      <button type="button" class="btn light" id="pkClear">Aucune / automatique</button>
      <button type="button" class="btn light" id="pkCancel">Annuler</button>
      <button type="button" class="btn gold" id="pkOk">Valider</button>
    </div>
  </form>
</dialog>
<style>
.slots{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px}
.slot{border:1px solid var(--line);border-radius:12px;padding:12px;display:flex;flex-direction:column;gap:8px}
.slot-head{display:flex;flex-direction:column;gap:2px}.slot-head .muted{font-size:.8rem}
.slot-pics{display:flex;gap:6px;flex-wrap:wrap;min-height:48px;align-items:center}
.slot-pics img{width:64px;height:48px;object-fit:cover;border-radius:6px}
.slot .slot-btn{align-self:flex-start}
.picker{border:0;border-radius:16px;padding:0;width:min(920px,96vw);max-height:90vh}
.picker::backdrop{background:rgba(7,28,53,.55)}
.picker-box{display:flex;flex-direction:column;max-height:90vh}
.picker-top{padding:14px 16px;border-bottom:1px solid var(--line);display:flex;flex-direction:column;gap:2px}
.picker-grid{padding:12px;overflow:auto;display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:10px}
.pk{position:relative;border:3px solid transparent;border-radius:10px;padding:0;background:#eef1f4;cursor:pointer;overflow:hidden;text-align:left}
.pk img{width:100%;aspect-ratio:4/3;object-fit:cover;display:block}
.pk span{position:absolute;left:4px;top:4px;background:rgba(7,28,53,.8);color:#fff;font-size:.72rem;font-weight:700;padding:2px 6px;border-radius:6px}
.pk em{display:block;font-style:normal;font-size:.72rem;color:#617083;padding:3px 6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.pk.on{border-color:#c79a38}.pk.on::after{content:attr(data-order);position:absolute;right:6px;top:6px;width:24px;height:24px;border-radius:50%;background:#c79a38;color:#fff;font-weight:800;display:grid;place-items:center;font-size:.8rem}
.pk.off img{opacity:.45}
.picker-actions{padding:12px 16px;border-top:1px solid var(--line);display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap}
</style>
<script>
(function () {
  const PHOTOS = <?= json_encode($pickData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
  const dlg = document.getElementById('picker'), grid = document.getElementById('pkGrid');
  if (!dlg || typeof dlg.showModal !== 'function') return;
  let slot = null, sel = [];
  const draw = () => {
    grid.innerHTML = '';
    PHOTOS.forEach(p => {
      const b = document.createElement('button'); b.type = 'button';
      const i = sel.indexOf(p.ref);
      b.className = 'pk' + (i >= 0 ? ' on' : '') + (p.hidden ? ' off' : '');
      if (i >= 0) b.dataset.order = i + 1;
      b.innerHTML = '<img loading="lazy" alt=""><span></span><em></em>';
      b.querySelector('img').src = p.src; b.querySelector('span').textContent = '#' + p.n;
      b.querySelector('em').textContent = (p.cat || 'Sans espace') + (p.hidden ? ' · masquée' : '');
      b.title = p.alt || '';
      b.addEventListener('click', () => {
        const multi = slot.dataset.multi === '1', max = +slot.dataset.max;
        if (!multi) { sel = [p.ref]; apply(); dlg.close(); return; }
        const k = sel.indexOf(p.ref);
        if (k >= 0) sel.splice(k, 1); else if (sel.length < max) sel.push(p.ref);
        draw();
      });
      grid.appendChild(b);
    });
  };
  const apply = () => {
    slot.querySelector('input').value = sel.join(',');
    const box = slot.querySelector('.slot-pics'); box.innerHTML = '';
    sel.forEach(r => { const p = PHOTOS.find(x => x.ref === r); if (p) { const im = document.createElement('img'); im.src = p.src; im.alt = ''; box.appendChild(im); } });
    if (!sel.length) box.innerHTML = '<span class="muted slot-empty">' + (slot.dataset.multi === '1' ? 'Automatique' : 'Aucune photo') + '</span>';
  };
  document.querySelectorAll('.slot-btn').forEach(btn => btn.addEventListener('click', () => {
    slot = btn.closest('.slot');
    sel = slot.querySelector('input').value.split(',').filter(Boolean);
    document.getElementById('pkTitle').textContent = slot.querySelector('.slot-head strong').textContent;
    document.getElementById('pkHelp').textContent = slot.dataset.multi === '1' ? 'Cliquez sur plusieurs photos (jusqu’à ' + slot.dataset.max + '), dans l’ordre voulu.' : 'Cliquez sur la photo voulue.';
    document.getElementById('pkOk').hidden = slot.dataset.multi !== '1';
    draw(); dlg.showModal();
  }));
  document.getElementById('pkOk').addEventListener('click', () => { apply(); dlg.close(); });
  document.getElementById('pkCancel').addEventListener('click', () => dlg.close());
  document.getElementById('pkClear').addEventListener('click', () => { sel = []; apply(); dlg.close(); });
})();
</script>

<form method="post" class="card">
  <?= csrf_field() ?>
  <button name="do" value="save" tabindex="-1" aria-hidden="true" style="position:absolute;left:-9999px"></button>
  <h2>Galerie <span class="pill"><?= count($photos) ?> photos</span></h2>
  <p class="muted" style="margin-top:-6px">La légende sert aux malvoyants et au référencement Google. ★ = image de fond du bandeau d’accueil. L’« espace » (Salle, Cuisine, Bar, Audiovisuel…) crée les onglets de la galerie et remplit automatiquement les onglets d’équipements de l’accueil.</p>
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
          <select name="<?= $n ?>[category]" aria-label="Espace (onglets de la galerie)" title="Espace : sert aux onglets de la galerie de la nouvelle page d’accueil">
            <option value="">Espace : —</option>
            <?php foreach (PHOTO_CATEGORIES as $ck => $cl): ?><option value="<?= e($ck) ?>"<?= ($p['category'] ?? '') === $ck ? ' selected' : '' ?>><?= e($cl) ?></option><?php endforeach; ?>
          </select>
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
