<?php
/** État des lieux signé (lecture et impression). Accès : lien secret du client, ou administrateur connecté. */
if (!empty($_GET['id'])) {
    define('ADMIN_AREA', true);
}
require __DIR__ . '/includes/bootstrap.php';

$admin = !empty($_GET['id']) && admin_can('reservations');
$b = $admin ? bk_get((int)$_GET['id']) : bk_find((string)($_GET['ref'] ?? ''), (string)($_GET['t'] ?? ''));
$kind = (string)($_GET['k'] ?? '');
$e = $b && isset(EDL_KINDS[$kind]) ? edl_get((int)$b['id'], $kind) : null;
if (!$e) {
    http_response_code(404);
    exit('Document indisponible.');
}
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex');
header('Cache-Control: private, no-store');

// Photo de cet état des lieux
if (isset($_GET['p'])) {
    $path = edl_photo_path((int)$b['id'], (string)$_GET['p']);
    if (!$path || !str_starts_with((string)$_GET['p'], $kind . '-')) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: image/jpeg');
    header('Content-Length: ' . filesize($path));
    header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: default-src 'none'; sandbox");
    readfile($path);
    exit;
}
$self = $admin ? 'etat-des-lieux.php?id=' . (int)$b['id'] . '&k=' . $kind : 'etat-des-lieux.php?ref=' . rawurlencode($b['ref']) . '&t=' . $b['token'] . '&k=' . $kind;
$rooms = json_decode($e['rooms_json'], true) ?: [];
$other = $kind === 'sortie' ? edl_get((int)$b['id'], 'entree') : null;
$before = $other ? array_column(json_decode($other['rooms_json'], true) ?: [], null, 'name') : [];
$stateColor = ['bon' => '#1d7a46', 'usure' => '#8a5a00', 'degat' => '#b42318'];
?><!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>État des lieux <?= e(EDL_KINDS[$kind]) ?> — <?= e($b['ref']) ?></title>
<style>
body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:#0b1f3a;margin:0;background:#f4f6f8}
.doc{max-width:860px;margin:24px auto;background:#fff;padding:32px 36px;border-radius:12px;box-shadow:0 2px 14px rgba(0,0,0,.06)}
h1{font-size:1.5rem;margin:0 0 4px}h2{font-size:1.05rem;margin:0}
.muted{color:#617083}.head{display:flex;justify-content:space-between;gap:20px;flex-wrap:wrap;border-bottom:2px solid #0b1f3a;padding-bottom:14px;margin-bottom:18px}
.room{border:1px solid #e3e7ec;border-radius:10px;padding:12px 14px;margin-bottom:12px;break-inside:avoid}
.room-top{display:flex;justify-content:space-between;gap:10px;align-items:baseline;flex-wrap:wrap}
.state{font-weight:700}.was{font-size:.88rem;color:#617083;margin:4px 0 0}
.photos{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px}.photos img{width:150px;height:112px;object-fit:cover;border-radius:6px;border:1px solid #e3e7ec}
.sigs{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:18px}.sigs img{max-width:100%;height:110px;object-fit:contain;border-bottom:1px solid #0b1f3a;display:block}
.print{text-align:center;margin:16px}.print button{font:inherit;padding:10px 18px;border-radius:8px;border:0;background:#0b1f3a;color:#fff;cursor:pointer}
@media (max-width:640px){.doc{padding:20px;margin:0;border-radius:0}.sigs{grid-template-columns:1fr}.photos img{width:46%;height:auto}}
@media print{body{background:#fff}.doc{box-shadow:none;margin:0;max-width:none}.print{display:none}}
</style></head><body>
<div class="print"><button onclick="window.print()">Imprimer / Enregistrer en PDF</button></div>
<div class="doc">
  <div class="head">
    <div><h1>État des lieux <?= e(EDL_KINDS[$kind]) ?></h1>
      <div class="muted"><?= e(s('bk_company_name') ?: s('site_name')) ?> — <?= e(str_replace("\n", ', ', s('bk_company_address'))) ?></div></div>
    <div style="text-align:right">Réservation <strong><?= e($b['ref']) ?></strong><br><?= e(bk_slot_label($b)) ?><br>
      Client : <?= e($b['first_name'] . ' ' . $b['last_name']) ?><br><span class="muted">Signé le <?= e(bk_dt($e['created_at'])->format('d/m/Y à H\hi')) ?></span></div>
  </div>
<?php foreach ($rooms as $r): $w = $before[$r['name']] ?? null; ?>
  <div class="room">
    <div class="room-top"><h2><?= e($r['name']) ?></h2><span class="state" style="color:<?= $stateColor[$r['state']] ?? '#0b1f3a' ?>"><?= e(EDL_STATES[$r['state']] ?? '') ?></span></div>
    <?php if ($w): ?><p class="was">À l’entrée : <?= e(EDL_STATES[$w['state']] ?? '') ?><?= ($w['comment'] ?? '') !== '' ? ' — ' . e($w['comment']) : '' ?></p><?php endif; ?>
    <?php if ($r['comment'] !== ''): ?><p style="margin:8px 0 0"><?= nl2br(e($r['comment'])) ?></p><?php endif; ?>
    <?php if ($r['photos']): ?><div class="photos"><?php foreach ($r['photos'] as $p): if (!preg_match('/^(entree|sortie)-[a-f0-9]{16}\.jpg$/', $p)) continue; ?>
      <a href="<?= e($self) ?>&amp;p=<?= e($p) ?>" target="_blank"><img src="<?= e($self) ?>&amp;p=<?= e($p) ?>" alt="Photo — <?= e($r['name']) ?>" loading="lazy"></a><?php endforeach; ?></div><?php endif; ?>
  </div>
<?php endforeach; ?>
<?php if ($e['keys_count'] !== '' || $e['notes'] !== ''): ?>
  <div class="room"><?php if ($e['keys_count'] !== ''): ?><p style="margin:0 0 6px"><strong>Clés :</strong> <?= e($e['keys_count']) ?></p><?php endif; ?>
    <?php if ($e['notes'] !== ''): ?><p style="margin:0"><strong>Remarques :</strong> <?= nl2br(e($e['notes'])) ?></p><?php endif; ?></div>
<?php endif; ?>
  <div class="sigs">
    <div><strong>Le client</strong> — <?= e($e['client_name']) ?><img src="<?= e($e['sig_client']) ?>" alt="Signature du client"></div>
    <div><strong>Le loueur</strong> — <?= e(s('bk_company_name') ?: s('site_name')) ?><img src="<?= e($e['sig_owner']) ?>" alt="Signature du loueur"></div>
  </div>
</div>
</body></html>
