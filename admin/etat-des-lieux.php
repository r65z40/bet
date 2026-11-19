<?php
/**
 * État des lieux sur tablette ou téléphone : état de chaque espace, photos, remarques,
 * signatures du client et du loueur. Une fois signé, le document est figé et envoyé au client.
 */
require __DIR__ . '/_init.php';
require_perm('reservations');

$b = bk_get((int)($_GET['id'] ?? 0));
$kind = (string)($_GET['k'] ?? 'entree');
if (!$b || !isset(EDL_KINDS[$kind]) || !edl_enabled()) {
    http_response_code(404);
    admin_header('État des lieux');
    echo '<h1>État des lieux indisponible</h1><p class="intro">' . (!edl_enabled() ? 'L’état des lieux sur tablette est désactivé (Réservations › Réglages › Caution, assurance & état des lieux).' : 'Réservation introuvable.') . '</p>';
    admin_footer();
    exit;
}
$id = (int)$b['id'];
$rooms = edl_rooms();
$entry = $kind === 'sortie' ? edl_get($id, 'entree') : null;
$entryRooms = $entry ? array_column(json_decode($entry['rooms_json'], true) ?: [], null, 'name') : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $fail = function (string $msg, int $code = 422) { http_response_code($code); echo json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE); exit; };
    if (empty($_POST)) {
        $fail('Envoi trop lourd pour le serveur : retirez quelques photos et réessayez.', 413);
    }
    check_csrf();
    if ($b['status'] !== 'confirmed') {
        $fail('Cette réservation n’est pas confirmée.');
    }
    if (edl_get($id, $kind)) {
        $fail('Cet état des lieux est déjà signé. Supprimez-le depuis la fiche pour le refaire.');
    }
    $sigC = edl_signature((string)($_POST['sig_client'] ?? ''));
    $sigO = edl_signature((string)($_POST['sig_owner'] ?? ''));
    $client = mb_substr(trim((string)($_POST['client_name'] ?? '')), 0, 80);
    if ($sigC === '' || $sigO === '' || $client === '') {
        $fail('Le nom du client et les deux signatures sont nécessaires.');
    }
    $out = [];
    $saved = [];
    $total = 0;
    foreach ($rooms as $i => $name) {
        $state = (string)($_POST['state'][$i] ?? '');
        if (!isset(EDL_STATES[$state])) {
            $fail('Indiquez l’état de « ' . $name . ' ».');
        }
        $photos = [];
        $files = $_FILES['photos_' . $i] ?? null;
        if ($files && is_array($files['tmp_name'])) {
            foreach ($files['tmp_name'] as $j => $tmp) {
                if (($files['error'][$j] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file($tmp) || ++$total > 80 || count($photos) >= 12) {
                    continue;
                }
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
                $file = $kind . '-' . bin2hex(random_bytes(8)) . '.jpg';
                if (in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) && image_reencode($tmp, edl_dir($id) . '/' . $file, 1600)) {
                    $photos[] = $file;
                    $saved[] = $file;
                }
            }
        }
        $out[] = ['name' => $name, 'state' => $state, 'comment' => mb_substr(trim((string)($_POST['comment'][$i] ?? '')), 0, 1000), 'photos' => $photos];
    }
    try {
        db()->prepare('INSERT INTO inspections(booking_id, kind, rooms_json, notes, keys_count, client_name, sig_client, sig_owner, admin, created_at) VALUES(?,?,?,?,?,?,?,?,?,?)')
            ->execute([$id, $kind, json_encode($out, JSON_UNESCAPED_UNICODE), mb_substr(trim((string)($_POST['notes'] ?? '')), 0, 3000),
                mb_substr(trim((string)($_POST['keys_count'] ?? '')), 0, 40), $client, $sigC, $sigO, current_admin()['username'], bk_now()->format(BK_FMT)]);
    } catch (PDOException) {
        foreach ($saved as $f) {
            @unlink(edl_dir($id) . '/' . $f);
        }
        $fail('Cet état des lieux vient d’être enregistré depuis un autre appareil.');
    }
    $degats = count(array_filter($out, fn($r) => $r['state'] === 'degat'));
    bk_log($id, 'État des lieux ' . EDL_KINDS[$kind] . ' signé (' . count($saved) . ' photo(s)' . ($degats ? ', ' . $degats . ' point(s) à signaler' : '') . ') — ' . current_admin()['username']);
    after_response(fn() => edl_mail(bk_get($id), $kind), 'état des lieux');
    flash('État des lieux ' . EDL_KINDS[$kind] . ' signé et enregistré.' . (notif_on('edl', 'client') ? ' Le client le reçoit par e-mail.' : ''));
    echo json_encode(['ok' => true, 'url' => 'reservation.php?id=' . $id . '#edl']);
    exit;
}

if (edl_get($id, $kind)) {
    redirect('../etat-des-lieux.php?id=' . $id . '&k=' . $kind);
}
admin_header('État des lieux ' . EDL_KINDS[$kind], 'reservations');
?>
<style>
.edl-room{border:1px solid var(--line);border-radius:12px;padding:14px 16px;margin-bottom:14px;background:#fff}
.edl-room h3{margin:0 0 10px;font-size:1.05rem}
.edl-states{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px}
.edl-states label{flex:1;min-width:120px;border:1.5px solid var(--line);border-radius:10px;padding:12px 10px;text-align:center;cursor:pointer;font-weight:600}
.edl-states input{position:absolute;opacity:0;pointer-events:none}
.edl-states label:has(input:checked){border-color:var(--navy,#0b1f3a);background:#eef3fa}
.edl-states label.degat:has(input:checked){border-color:#b42318;background:#fdecea;color:#8a1c12}
.edl-room textarea{width:100%;min-height:54px}
.edl-photos{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px}
.edl-photos img{width:84px;height:84px;object-fit:cover;border-radius:8px;border:1px solid var(--line)}
.edl-photos .rm{position:relative}.edl-photos .rm button{position:absolute;top:-6px;right:-6px;width:24px;height:24px;border-radius:50%;border:0;background:#b42318;color:#fff;font-weight:700;cursor:pointer}
.edl-add{display:inline-flex;align-items:center;gap:6px;cursor:pointer}
.edl-add input{display:none}
.edl-was{font-size:.88rem;color:var(--muted);margin:-4px 0 10px}
.sig-pad{width:100%;height:180px;border:1.5px dashed #9aa6b3;border-radius:10px;background:#fff;touch-action:none;display:block}
.sig-wrap{display:grid;grid-template-columns:1fr 1fr;gap:16px}
@media (max-width:760px){.sig-wrap{grid-template-columns:1fr}}
</style>
<p><a href="reservation.php?id=<?= $id ?>" class="muted">← Fiche de la réservation</a></p>
<h1>État des lieux <?= e(EDL_KINDS[$kind]) ?></h1>
<p class="intro"><?= e($b['first_name'] . ' ' . $b['last_name']) ?> — <?= e(bk_slot_label($b)) ?> · réf. <?= e($b['ref']) ?><?= $entry ? '<br>État à l’entrée rappelé sous chaque espace (signé le ' . e(bk_dt($entry['created_at'])->format('d/m/Y')) . ').' : '' ?></p>

<form id="edlForm" novalidate>
  <?= csrf_field() ?>
  <?php foreach ($rooms as $i => $name): $was = $entryRooms[$name] ?? null; ?>
  <div class="edl-room" data-i="<?= $i ?>">
    <h3><?= e($name) ?></h3>
    <?php if ($was): ?><p class="edl-was">À l’entrée : <strong><?= e(EDL_STATES[$was['state']] ?? '') ?></strong><?= $was['comment'] !== '' ? ' — ' . e($was['comment']) : '' ?><?= $was['photos'] ? ' · ' . count($was['photos']) . ' photo(s)' : '' ?></p><?php endif; ?>
    <div class="edl-states" role="radiogroup" aria-label="État : <?= e($name) ?>">
      <?php foreach (EDL_STATES as $sk => $sl): ?>
      <label class="<?= $sk ?>"><input type="radio" name="state[<?= $i ?>]" value="<?= $sk ?>"> <?= e($sl) ?></label>
      <?php endforeach; ?>
    </div>
    <textarea name="comment[<?= $i ?>]" placeholder="Remarques (facultatif) : tache, rayure, ampoule…" maxlength="1000"></textarea>
    <div class="edl-photos"><label class="btn light sm edl-add">📷 Ajouter des photos<input type="file" accept="image/*" capture="environment" multiple></label></div>
  </div>
  <?php endforeach; ?>

  <div class="card">
    <div class="grid g2">
      <label class="field"><span>Clés remises<?= $kind === 'sortie' ? ' / rendues' : '' ?></span><input type="text" name="keys_count" maxlength="40" placeholder="ex. 2 clés + 1 badge"></label>
      <label class="field"><span>Nom du client qui signe</span><input type="text" name="client_name" maxlength="80" value="<?= e($b['first_name'] . ' ' . $b['last_name']) ?>"></label>
    </div>
    <label class="field"><span>Remarques générales</span><textarea name="notes" maxlength="3000" style="min-height:70px"></textarea></label>
    <div class="sig-wrap">
      <div><strong>Signature du client</strong><canvas class="sig-pad" data-sig="sig_client"></canvas><button type="button" class="btn light sm" data-clear="sig_client" style="margin-top:6px">Effacer</button></div>
      <div><strong>Signature du loueur</strong><canvas class="sig-pad" data-sig="sig_owner"></canvas><button type="button" class="btn light sm" data-clear="sig_owner" style="margin-top:6px">Effacer</button></div>
    </div>
    <p class="muted" style="margin:12px 0">En signant, le client et le loueur reconnaissent l’exactitude de cet état des lieux. Une fois enregistré, il ne peut plus être modifié et une copie est envoyée au client.</p>
    <button class="btn gold" type="submit" id="edlSave">✍️ Enregistrer l’état des lieux signé</button>
    <p id="edlMsg" role="status" aria-live="polite" style="margin:10px 0 0;font-weight:600"></p>
  </div>
</form>

<script>
(function () {
  const form = document.getElementById('edlForm'), msg = document.getElementById('edlMsg');
  const photos = {}; // index de l'espace → [Blob]
  // Photo réduite (1600 px, JPEG) avant l'envoi : rapide même en 4G, et sans données de localisation
  async function shrink(file) {
    try {
      const bmp = await createImageBitmap(file, { imageOrientation: 'from-image' });
      const k = Math.min(1, 1600 / Math.max(bmp.width, bmp.height));
      const c = document.createElement('canvas'); c.width = Math.round(bmp.width * k); c.height = Math.round(bmp.height * k);
      c.getContext('2d').drawImage(bmp, 0, 0, c.width, c.height);
      const blob = await new Promise(r => c.toBlob(r, 'image/jpeg', 0.8));
      const t = document.createElement('canvas'), tk = 160 / Math.max(c.width, c.height); t.width = c.width * tk; t.height = c.height * tk;
      t.getContext('2d').drawImage(c, 0, 0, t.width, t.height);
      return { blob, thumb: t.toDataURL('image/jpeg', 0.6) };
    } catch (e) { return null; }
  }
  form.querySelectorAll('.edl-room').forEach(room => {
    const i = room.dataset.i, box = room.querySelector('.edl-photos'), input = room.querySelector('input[type=file]');
    photos[i] = [];
    input.addEventListener('change', async () => {
      for (const f of input.files) {
        if (photos[i].length >= 12) { alert('12 photos au plus par espace.'); break; }
        const p = await shrink(f);
        if (!p) { alert('Une photo n’a pas pu être lue (format non pris en charge).'); continue; }
        photos[i].push(p.blob);
        const w = document.createElement('span'); w.className = 'rm';
        const img = document.createElement('img'); img.src = p.thumb; img.alt = 'Photo';
        const x = document.createElement('button'); x.type = 'button'; x.textContent = '×'; x.setAttribute('aria-label', 'Retirer la photo');
        x.addEventListener('click', () => { photos[i].splice(photos[i].indexOf(p.blob), 1); w.remove(); });
        w.append(img, x); box.insertBefore(w, box.lastElementChild);
      }
      input.value = '';
    });
  });
  // Signatures au doigt ou au stylet
  const pads = {};
  form.querySelectorAll('.sig-pad').forEach(cv => {
    const ctx = cv.getContext('2d'); let drawing = false, used = false;
    const fit = () => { const r = cv.getBoundingClientRect(); cv.width = r.width * 2; cv.height = r.height * 2; ctx.scale(2, 2); ctx.lineWidth = 2.2; ctx.lineCap = 'round'; ctx.strokeStyle = '#0b1f3a'; used = false; };
    fit();
    const pos = e => { const r = cv.getBoundingClientRect(); return [e.clientX - r.left, e.clientY - r.top]; };
    cv.addEventListener('pointerdown', e => { drawing = true; used = true; cv.setPointerCapture(e.pointerId); ctx.beginPath(); ctx.moveTo(...pos(e)); });
    cv.addEventListener('pointermove', e => { if (drawing) { ctx.lineTo(...pos(e)); ctx.stroke(); } });
    ['pointerup', 'pointercancel'].forEach(t => cv.addEventListener(t, () => { drawing = false; }));
    pads[cv.dataset.sig] = { used: () => used, data: () => cv.toDataURL('image/png'), clear: () => { ctx.setTransform(1, 0, 0, 1, 0, 0); ctx.clearRect(0, 0, cv.width, cv.height); fit(); } };
  });
  form.querySelectorAll('[data-clear]').forEach(btn => btn.addEventListener('click', () => pads[btn.dataset.clear].clear()));

  form.addEventListener('submit', async ev => {
    ev.preventDefault();
    const missing = [...form.querySelectorAll('.edl-room')].find(r => !r.querySelector('input[type=radio]:checked'));
    if (missing) { msg.style.color = '#b42318'; msg.textContent = 'Indiquez l’état de « ' + missing.querySelector('h3').textContent + ' ».'; missing.scrollIntoView({ block: 'center' }); return; }
    if (!pads.sig_client.used() || !pads.sig_owner.used()) { msg.style.color = '#b42318'; msg.textContent = 'Les deux signatures sont nécessaires.'; return; }
    const fd = new FormData(form);
    fd.append('sig_client', pads.sig_client.data()); fd.append('sig_owner', pads.sig_owner.data());
    Object.entries(photos).forEach(([i, list]) => list.forEach((b, j) => fd.append('photos_' + i + '[]', b, 'photo-' + j + '.jpg')));
    const btn = document.getElementById('edlSave'); btn.disabled = true; msg.style.color = ''; msg.textContent = 'Enregistrement en cours…';
    try {
      const r = await fetch(location.href, { method: 'POST', body: fd, credentials: 'same-origin' });
      const j = await r.json().catch(() => ({ error: 'Réponse inattendue du serveur (' + r.status + ').' }));
      if (j.ok) { location.href = j.url; return; }
      msg.style.color = '#b42318'; msg.textContent = j.error;
    } catch (e) { msg.style.color = '#b42318'; msg.textContent = 'Connexion interrompue : rien n’a été enregistré, réessayez.'; }
    btn.disabled = false;
  });
})();
</script>
<?php admin_footer();
