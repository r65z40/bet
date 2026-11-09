<?php
require __DIR__ . '/includes/bootstrap.php';
track_visit('reservation');
$name = s('site_name');
$siteUrl = rtrim(s('site_url'), '/');
$maintenance = s('resa_maintenance') === '1';
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(s('resa_meta_title')) ?></title>
<meta name="description" content="<?= e(s('resa_lead')) ?>">
<meta name="robots" content="<?= $maintenance ? 'noindex, follow' : 'index, follow' ?>"><link rel="canonical" href="<?= e($siteUrl) ?>/reservation.php"><link rel="icon" href="favicon.ico" sizes="any"><link rel="icon" href="logo-rn6.png" type="image/png">
<style>
:root{--navy:#071c35;--gold:#c79a38;--gold2:#f2d47b;--cream:#fbf8f1;--white:#fff;--ink:#152131;--muted:#617083;--line:rgba(7,28,53,.10)}*{box-sizing:border-box}body{margin:0;font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:var(--ink);background:var(--cream);line-height:1.6}.wrap{width:min(1160px,calc(100% - 36px));margin:auto}header{position:sticky;top:0;z-index:50;background:rgba(5,18,31,.96);border-bottom:1px solid rgba(255,255,255,.10);box-shadow:0 8px 28px rgba(0,0,0,.12)}.nav{min-height:76px;display:flex;align-items:center;justify-content:space-between;gap:20px}.brand{display:flex;align-items:center;gap:12px;text-decoration:none;font-weight:800;color:#fff}.brand-mark{width:48px;height:48px;border:2px solid #273CF5;border-radius:50%;display:flex;align-items:center;justify-content:center;font-family:Georgia,serif;font-size:.9rem;background:#fff;font-weight:900;letter-spacing:-.04em}.rn{color:#273CF5}.six{color:#B50E0E}.brand small{display:block;font-size:.68rem;letter-spacing:.16em;color:var(--gold2);text-transform:uppercase}.back{color:#fff;text-decoration:none;font-weight:800;padding:10px 14px;border:1px solid rgba(255,255,255,.2);border-radius:10px}.hero{padding:60px 0 28px;text-align:center}.eyebrow{font-size:.8rem;font-weight:900;letter-spacing:.2em;text-transform:uppercase;color:#8f6818}h1{font-family:Georgia,"Times New Roman",serif;color:var(--navy);font-size:clamp(2.5rem,6vw,4.5rem);line-height:1;margin:12px 0 16px}.hero p{max-width:760px;margin:0 auto;color:var(--muted);font-size:1.05rem}.booking{padding:18px 0 70px}.booking-card{background:#fff;border:1px solid var(--line);border-radius:22px;padding:20px;box-shadow:0 18px 55px rgba(7,28,53,.10);min-height:650px}.notice{display:flex;gap:12px;align-items:flex-start;background:#fff8e8;border:1px solid rgba(199,154,56,.28);border-radius:14px;padding:14px 16px;margin-bottom:18px;color:#5f4b22}.notice strong{color:var(--navy)}footer{border-top:1px solid var(--line);padding:24px 0 38px;color:#768394;font-size:.9rem;background:#fff}.foot{display:flex;justify-content:space-between;gap:18px;flex-wrap:wrap}@media(max-width:620px){.wrap{width:min(100% - 24px,1160px)}.nav{min-height:66px}.brand span{font-size:.9rem}.back{font-size:.88rem;padding:9px 10px}.hero{padding:42px 0 20px}.booking-card{padding:10px;border-radius:16px}.notice{font-size:.9rem}}
/* Mode maintenance */
.maint{background:#fff;border:1px solid var(--line);border-radius:22px;padding:56px 36px;box-shadow:0 18px 55px rgba(7,28,53,.10);text-align:center;max-width:820px;margin:0 auto}
.maint-icon{width:78px;height:78px;margin:0 auto 22px;border-radius:50%;display:grid;place-items:center;font-size:2.1rem;background:#fff8e8;border:1px solid rgba(199,154,56,.35)}
.maint h2{font-family:Georgia,"Times New Roman",serif;color:var(--navy);font-size:clamp(1.6rem,3.4vw,2.3rem);line-height:1.15;margin:0 0 16px}
.maint p{color:#425166;font-size:1.06rem;margin:0 auto;max-width:620px}
.maint-actions{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-top:30px}
.maint-actions a{display:inline-flex;align-items:center;gap:8px;padding:14px 20px;border-radius:10px;text-decoration:none;font-weight:900}
.maint-actions .primary{background:linear-gradient(135deg,#f0cf72,#d8a83f);color:#152131;box-shadow:0 12px 30px rgba(0,0,0,.12)}
.maint-actions .secondary{border:1px solid var(--line);color:var(--navy);background:#fff}
@media(max-width:620px){.maint{padding:38px 20px}.maint-actions a{width:100%;justify-content:center}}
</style>
</head><body>
<header><div class="wrap nav"><a class="brand" href="./" aria-label="Accueil <?= e($name) ?>"><div class="brand-mark"><span class="rn">RN</span><span class="six">6</span></div><span><?= e($name) ?><small><?= e(s('site_location')) ?></small></span></a><a class="back" href="./">← Retour au site</a></div></header>
<main><section class="hero"><div class="wrap"><div class="eyebrow">Réservation en ligne</div><h1><?= e(s('resa_title')) ?></h1><?php if (!$maintenance): ?><p><?= rich(s('resa_lead')) ?></p><?php endif; ?></div></section>
<?php if ($maintenance): ?>
<section class="booking"><div class="wrap"><div class="maint" role="status">
  <div class="maint-icon" aria-hidden="true">🛠</div>
  <h2><?= e(s('resa_maint_title')) ?></h2>
  <p><?= rich(s('resa_maint_message')) ?></p>
<?php if (s('resa_maint_contact') !== '0'): ?>
  <div class="maint-actions">
    <a class="primary" href="<?= e(mailto()) ?>" data-track="email">✉ Nous contacter</a>
    <a class="secondary" href="./">← Retour au site</a>
  </div>
<?php endif; ?>
</div></div></section></main>
<?php else: ?>
<section class="booking"><div class="wrap"><div class="notice"><span>ℹ️</span><div><strong><?= e(s('resa_notice_title')) ?></strong><br><?= rich(s('resa_notice')) ?></div></div><div class="booking-card">
<script src="https://cdn.supersaas.net/widget.js"></script>
<script class="supersaas-widget">var supersaas = new SuperSaaS(<?= json_encode(s('supersaas_account'), JSON_HEX_TAG) ?>, <?= json_encode(s('supersaas_schedule'), JSON_HEX_TAG) ?>, {"widget_type":"frame","view":"week","legend":"show","domain":"www.supersaas.fr"})</script>
</div></div></section></main>
<?php endif; ?>
<footer><div class="wrap foot"><span>© <?= date('Y') ?> <?= e(s('footer_text')) ?></span><span><a href="<?= e(mailto()) ?>" data-track="email" style="color:inherit"><?= e(s('contact_email')) ?></a></span></div></footer>
<?= tracking_script('reservation') ?>
</body></html>
