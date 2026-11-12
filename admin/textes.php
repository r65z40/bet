<?php
/** Édition de tous les textes du site, regroupés par section. */
require __DIR__ . '/_init.php';
require_perm('contenu');

$sections = SETTINGS_SCHEMA;
$current = (string)($_GET['s'] ?? 'general');
if (!isset($sections[$current])) {
    $current = 'general';
}
$fields = $sections[$current]['fields'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $errors = save_settings_fields($fields);
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
      <?php if (isset($f['perm']) && !admin_can($f['perm'])): ?>
        <?= field($key, $f['label'], (string)($f['options'][s($key)] ?? s($key)), 'readonly', 'Modifiable uniquement avec le droit « ' . ADMIN_PERMS[$f['perm']][0] . ' ».') ?>
      <?php else: ?>
        <?= field($key, $f['label'], s($key) === '' && isset($f['fallback']) ? constant($f['fallback']) : s($key), $f['type'] ?? 'text', $f['help'] ?? '', $f['options'] ?? []) ?>
      <?php endif; ?>
    <?php endforeach; ?>
    <div class="sticky-save"><button class="btn gold">💾 Enregistrer</button></div>
  </form>
</div>
<?php admin_footer();
