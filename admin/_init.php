<?php
define('ADMIN_AREA', true);
require dirname(__DIR__) . '/includes/bootstrap.php';
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');

function admin_header(string $title, string $active = ''): void
{
    $admin = current_admin();
    $menu = [
        'index'    => ['index.php', '🏠', 'Tableau de bord'],
        'textes'   => ['textes.php', '✏️', 'Textes'],
        'contenus' => ['listes.php', '☰', 'Listes & cartes'],
        'tarifs'   => ['listes.php?g=tarif_jours', '€', 'Tarifs'],
        'photos'   => ['photos.php', '📷', 'Photos'],
        'avis'     => ['avis.php', '★', 'Avis'],
        'stats'    => ['stats.php', '📊', 'Statistiques'],
        'compte'   => ['compte.php', '🔑', 'Mon compte'],
    ];
    ?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> · Administration <?= e(s('site_name')) ?></title>
<link rel="icon" href="../favicon.ico">
<link rel="stylesheet" href="admin.css">
</head>
<body>
<?php if ($admin): ?>
<header class="top">
  <a class="logo" href="index.php"><span class="mark">RN6</span> Administration</a>
  <nav>
    <?php foreach ($menu as $key => [$href, $icon, $label]): ?>
      <a href="<?= e($href) ?>" class="<?= $key === $active ? 'on' : '' ?>"><span aria-hidden="true"><?= $icon ?></span> <?= e($label) ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="right">
    <a href="../" target="_blank" rel="noopener">Voir le site ↗</a>
    <form method="post" action="logout.php"><?= csrf_field() ?><button class="link">Déconnexion</button></form>
  </div>
</header>
<?php endif; ?>
<main class="<?= $admin ? 'page' : 'auth' ?>">
<?= render_flash() ?>
<?php
}

function admin_footer(): void
{
    echo "</main>\n</body>\n</html>\n";
}

/** Champ de formulaire simple. */
function field(string $name, string $label, string $value, string $type = 'text', string $help = '', array $options = []): string
{
    $id = 'f_' . preg_replace('/\W/', '_', $name);
    $html = '<label class="field" for="' . $id . '"><span>' . e($label) . '</span>';
    if ($type === 'select') {
        $html .= '<select id="' . $id . '" name="' . e($name) . '">';
        foreach ($options as $k => $lbl) {
            $html .= '<option value="' . e((string)$k) . '"' . ((string)$k === $value ? ' selected' : '') . '>' . e($lbl) . '</option>';
        }
        $html .= '</select>';
    } elseif ($type === 'textarea') {
        $rows = max(3, min(10, substr_count($value, "\n") + 2, (int)ceil(mb_strlen($value) / 90) + 1));
        $html .= '<textarea id="' . $id . '" name="' . e($name) . '" rows="' . $rows . '">' . e($value) . '</textarea>';
    } else {
        $html .= '<input id="' . $id . '" type="' . e($type) . '" name="' . e($name) . '" value="' . e($value) . '">';
    }
    if ($help !== '') {
        $html .= '<small>' . e($help) . '</small>';
    }
    return $html . '</label>';
}
