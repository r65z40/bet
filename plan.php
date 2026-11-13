<?php
require __DIR__ . '/includes/bootstrap.php';
track_visit('plan');
$name = s('site_name');
$siteUrl = rtrim(s('site_url'), '/');
?><!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(s('plan_meta_title')) ?></title>
  <meta name="description" content="<?= e(s('plan_meta_desc')) ?>">
  <meta name="robots" content="index, follow, max-image-preview:large">
  <meta name="theme-color" content="#071c35">
  <link rel="canonical" href="<?= e($siteUrl) ?>/plan.php">
  <meta property="og:type" content="website">
  <meta property="og:locale" content="fr_FR">
  <meta property="og:site_name" content="<?= e($name) ?>">
  <meta property="og:url" content="<?= e($siteUrl) ?>/plan.php">
  <meta property="og:image" content="<?= e($siteUrl . '/' . plan_image()) ?>">
  <meta property="og:image:alt" content="Plan de <?= e($name) ?>">
  <meta property="og:title" content="<?= e(s('plan_meta_title')) ?>">
  <meta property="og:description" content="<?= e(s('plan_meta_desc')) ?>">
  <link rel="icon" href="favicon.ico" sizes="any">
  <link rel="icon" href="icon-rn6.png" type="image/png"><link rel="apple-touch-icon" href="apple-touch-icon.png">
  <style>
    :root{--navy:#071c35;--gold:#c79a38;--gold2:#f2d47b;--cream:#fbf8f1;--ink:#152131;--muted:#617083;--line:rgba(7,28,53,.10);--shadow:0 18px 55px rgba(7,28,53,.12)}
    *{box-sizing:border-box} body{margin:0;font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:var(--ink);background:var(--cream);line-height:1.6} a{color:inherit}.wrap{width:min(1160px,calc(100% - 36px));margin:auto}
    header{position:sticky;top:0;z-index:20;background:rgba(5,18,31,.94);border-bottom:1px solid rgba(255,255,255,.1);backdrop-filter:blur(14px)}
    .nav{min-height:76px;display:flex;align-items:center;justify-content:space-between;gap:20px}.brand{display:flex;align-items:center;gap:12px;text-decoration:none;font-weight:800;color:#fff}.brand-mark{width:48px;height:48px;border:2px solid var(--gold);border-radius:50%;display:grid;place-items:center;font-family:Georgia,serif;font-size:.9rem;background:#fff;color:var(--navy)}.brand small{display:block;font-size:.68rem;letter-spacing:.16em;color:var(--gold2);text-transform:uppercase}.back{color:#fff;text-decoration:none;border:1px solid rgba(242,212,123,.65);padding:10px 16px;border-radius:10px;font-weight:700}.back:hover{color:var(--gold2)}
    main{padding:72px 0 80px}.eyebrow{text-transform:uppercase;letter-spacing:.18em;font-size:.78rem;font-weight:800;color:#8a6516;margin-bottom:8px}h1{font-family:Georgia,serif;font-size:clamp(2.2rem,5vw,4.3rem);line-height:1.05;margin:0 0 16px;color:var(--navy)}.lead{max-width:760px;color:var(--muted);font-size:1.08rem;margin:0 0 34px}
    .facts{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:30px}.fact{background:#fff;border:1px solid var(--line);border-radius:16px;padding:18px}.fact strong{display:block;color:var(--navy);font-size:1.05rem}.fact span{color:var(--muted);font-size:.92rem}
    .plan-card{background:#fff;border:1px solid var(--line);border-radius:22px;padding:18px;box-shadow:var(--shadow)}.plan-card img{display:block;width:100%;height:auto;border-radius:13px}.hint{text-align:center;color:var(--muted);font-size:.9rem;margin:14px 0 0}.actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:28px}.btn{display:inline-flex;align-items:center;justify-content:center;padding:13px 18px;border-radius:11px;text-decoration:none;font-weight:800}.primary{background:var(--navy);color:#fff}.secondary{border:1px solid var(--line);background:#fff;color:var(--navy)}
    footer{border-top:1px solid var(--line);padding:24px 0 38px;color:#5d6979;font-size:.9rem;background:#fff}.foot{display:flex;justify-content:space-between;gap:20px;flex-wrap:wrap}
    @media(max-width:760px){main{padding-top:45px}.facts{grid-template-columns:1fr 1fr}.back{font-size:.88rem}.brand span{display:none}.plan-card{padding:8px;border-radius:14px}}
    @media(max-width:480px){.facts{grid-template-columns:1fr}}
  </style>
</head>
<body>
<header><div class="wrap nav"><a class="brand" href="./"><div class="brand-mark">RN6</div><span><?= e($name) ?><small><?= e(s('site_location')) ?></small></span></a><a class="back" href="./">← Retour au site</a></div></header>
<main><div class="wrap">
  <div class="eyebrow"><?= e($name) ?> · Appoigny</div>
  <h1><?= e(s('plan_title')) ?></h1>
  <p class="lead"><?= rich(s('plan_lead')) ?></p>
  <div class="facts">
<?php foreach (items('plan_facts') as $it): ?>
    <div class="fact"><strong><?= e($it['title']) ?></strong><span><?= e($it['text']) ?></span></div>
<?php endforeach; ?>
  </div>
  <div class="plan-card"><picture><?php if ($small = plan_image_small()): ?><source media="(max-width:640px)" srcset="<?= e($small) ?>" type="image/webp"><?php endif; ?><img src="<?= e(plan_image()) ?>" alt="Plan de <?= e($name) ?> à Appoigny"<?php if ($sz = image_size(plan_image())): ?> width="<?= $sz[0] ?>" height="<?= $sz[1] ?>"<?php endif; ?> fetchpriority="high" decoding="async"></picture><p class="hint"><?= e(s('plan_hint')) ?></p></div>
  <div class="actions"><a class="btn primary" href="reservation.php" data-track="reserver">Réserver en ligne</a><a class="btn secondary" href="<?= e(mailto()) ?>" data-track="email">Demander une réservation</a><a class="btn secondary" href="./#photos">Voir les photos</a></div>
</div></main>
<footer><div class="wrap foot"><span>© <?= date('Y') ?> <?= e(s('footer_text')) ?> · <a href="mentions-legales.php" style="color:inherit">Mentions légales</a> · <a href="confidentialite.php" style="color:inherit">Confidentialité</a></span><span><?= e(s('footer_tagline')) ?></span></div></footer>
<?= tracking_script('plan') ?>
</body></html>
