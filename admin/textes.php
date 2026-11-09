<?php
/** Édition de tous les textes du site, regroupés par section. */
require __DIR__ . '/_init.php';
require_admin();

$sections = SETTINGS_SCHEMA;
$current = (string)($_GET['s'] ?? 'general');
if (!isset($sections[$current])) {
    $current = 'general';
}
$fields = $sections[$current]['fields'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $errors = [];
    foreach ($fields as $key => $f) {
        $v = trim(str_replace("\r\n", "\n", (string)($_POST[$key] ?? '')));
        $type = $f['type'] ?? 'text';
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
        save_setting($key, mb_substr($v, 0, 5000));
    }
    foreach ($errors as $err) {
        flash($err, 'err');
    }
    if (!$errors) {
        flash('Textes enregistrés.');
    }
    redirect('textes.php?s=' . urlencode($current));
}

admin_header('Textes', 'textes');
?>
<h1>Textes du site</h1>
<p class="intro">Modifiez les titres et paragraphes. Astuce : entourez un mot de <code>**deux étoiles**</code> pour le mettre en <strong>gras</strong>.</p>

<div class="layout">
  <aside class="side">
    <?php foreach ($sections as $key => $sec): ?>
      <a href="?s=<?= e($key) ?>" class="<?= $key === $current ? 'on' : '' ?>"><?= e($sec['title']) ?></a>
    <?php endforeach; ?>
  </aside>
  <form method="post" class="card">
    <?= csrf_field() ?>
    <h2><?= e($sections[$current]['title']) ?></h2>
    <?php foreach ($fields as $key => $f): ?>
      <?= field($key, $f['label'], s($key), $f['type'] ?? 'text', $f['help'] ?? '', $f['options'] ?? []) ?>
    <?php endforeach; ?>
    <div class="sticky-save"><button class="btn gold">💾 Enregistrer</button></div>
  </form>
</div>
<?php admin_footer();
