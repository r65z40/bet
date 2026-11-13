<?php
/** Mise en page commune des pages publiques secondaires (réservation, ma réservation, conditions). */

function public_header(string $title, string $description = '', bool $index = true, string $extraHead = ''): void
{
    $name = s('site_name');
    ?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<?php if ($description !== ''): ?><meta name="description" content="<?= e($description) ?>"><?php endif; ?>
<meta name="robots" content="<?= $index ? 'index, follow' : 'noindex, nofollow' ?>">
<?php if ($index && preg_match('#^https?://#', s('site_url'))): $canon = rtrim(s('site_url'), '/') . '/' . u(basename($_SERVER['SCRIPT_NAME'] ?? '')); ?>
<link rel="canonical" href="<?= e($canon) ?>">
<meta property="og:type" content="website"><meta property="og:locale" content="fr_FR"><meta property="og:site_name" content="<?= e($name) ?>">
<meta property="og:url" content="<?= e($canon) ?>"><meta property="og:title" content="<?= e($title) ?>"><?php if ($description !== ''): ?><meta property="og:description" content="<?= e($description) ?>"><?php endif; ?>
<meta property="og:image" content="<?= e(rtrim(s('site_url'), '/') . '/' . hero_image()) ?>">
<?php endif; ?>
<meta name="theme-color" content="#071c35">
<link rel="icon" href="favicon.ico" sizes="any"><link rel="icon" href="icon-rn6.png" type="image/png"><link rel="apple-touch-icon" href="apple-touch-icon.png">
<link rel="stylesheet" href="assets/public.css?v=<?= @filemtime(ROOT . '/assets/public.css') ?>">
<?= $extraHead ?>
</head><body>
<header class="top"><div class="wrap nav"><a class="brand" href="./" aria-label="Accueil <?= e($name) ?>"><div class="brand-mark"><span class="rn">RN</span><span class="six">6</span></div><span><?= e($name) ?><small><?= e(s('site_location')) ?></small></span></a><a class="back" href="./">← Retour au site</a></div></header>
<main>
<?php
}

function public_footer(string $page = ''): void
{
    ?>
</main>
<footer class="foot-bar"><div class="wrap foot"><span>© <?= date('Y') ?> <?= e(s('footer_text')) ?></span><span><a href="<?= e(u('conditions.php')) ?>">Conditions de location</a> · <a href="<?= e(u('mentions-legales.php')) ?>">Mentions légales</a> · <a href="<?= e(u('confidentialite.php')) ?>">Confidentialité</a> · <a href="<?= e(mailto()) ?>" data-track="email"><?= e(s('contact_email')) ?></a></span></div></footer>
<?= $page !== '' ? tracking_script($page) : '' ?>
</body></html>
<?php
}
