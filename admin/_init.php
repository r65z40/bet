<?php
define('ADMIN_AREA', true);
require dirname(__DIR__) . '/includes/bootstrap.php';
define('SETUP_CODE_FILE', DATA_DIR . '/setup-code.php');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; frame-ancestors 'none'; form-action 'self'; base-uri 'none'; object-src 'none'");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');

function admin_header(string $title, string $active = ''): void
{
    $admin = current_admin();
    $menu = [
        'index'    => ['index.php', '🏠', 'Tableau de bord'],
        'textes'   => ['textes.php', '✏️', 'Textes'],
        'sections' => ['sections.php', '⇅', 'Sections'],
        'contenus' => ['listes.php', '☰', 'Listes & cartes'],
        'resa'     => ['reservations.php', '📅', 'Réservations'],
        'tarifs'   => ['reglages.php?s=tarifs', '€', 'Tarifs & options'],
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
    } elseif ($type === 'secret') {
        $html .= '<input id="' . $id . '" type="password" name="' . e($name) . '" value="" autocomplete="new-password" placeholder="'
            . ($value !== '' ? '•••••••• enregistré — laisser vide pour conserver' : 'non renseigné') . '">';
        if ($value !== '') {
            $html .= '<small><label style="font-weight:600"><input type="checkbox" name="' . e($name) . '__clear" value="1"> Effacer la valeur enregistrée</label></small>';
        }
    } else {
        $html .= '<input id="' . $id . '" type="' . e($type) . '" name="' . e($name) . '" value="' . e($value) . '"' . ($type === 'number' ? ' step="any"' : '') . '>';
    }
    if ($help !== '') {
        $html .= '<small>' . e($help) . '</small>';
    }
    return $html . '</label>';
}

/** Enregistre les champs d'une section de réglages ; retourne la liste des erreurs. */
function save_settings_fields(array $fields): array
{
    $errors = [];
    foreach ($fields as $key => $f) {
        $type = $f['type'] ?? 'text';
        $v = trim(str_replace("\r\n", "\n", (string)($_POST[$key] ?? '')));
        if ($type === 'secret') {
            if (!empty($_POST[$key . '__clear'])) {
                save_setting($key, '');
            } elseif ($v !== '') {
                save_setting($key, $v);
            }
            continue;
        }
        if ($type === 'email' && $v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
            $errors[] = $f['label'] . ' : adresse e-mail invalide.';
            continue;
        }
        if ($type === 'select' && !isset($f['options'][$v])) {
            continue;
        }
        if ($type === 'url' && $v !== '' && !preg_match('#^https?://#i', $v)) {
            $errors[] = $f['label'] . ' : le lien doit commencer par https://';
            continue;
        }
        if ($type === 'number' && $v !== '' && !is_numeric(str_replace(',', '.', $v))) {
            $errors[] = $f['label'] . ' : nombre attendu.';
            continue;
        }
        if ($type === 'time' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v)) {
            $errors[] = $f['label'] . ' : heure attendue (ex. 10:00).';
            continue;
        }
        save_setting($key, mb_substr($v, 0, 20000));
    }
    settings(true);
    return $errors;
}
