<?php
/** Mentions légales (texte modifiable dans Admin › Contenu du site › Textes › Pages légales). */
require __DIR__ . '/includes/bootstrap.php';
public_header('Mentions légales | ' . s('site_name'), 'Mentions légales du site ' . s('site_name') . '.');
?>
<section class="page-hero"><div class="wrap"><div class="eyebrow"><?= e(s('site_name')) ?></div><h1>Mentions légales</h1></div></section>
<section><div class="wrap"><div class="card legal" style="max-width:860px;margin:0 auto">
<?= render_doc(legal_text('legal_mentions'), legal_vars()) ?>
</div></div></section>
<?php public_footer();
