<?php
/** Conditions générales de location (modifiables dans Admin › Réservations › Réglages). */
require __DIR__ . '/includes/bootstrap.php';
track_visit('conditions');
public_header('Conditions de location | ' . s('site_name'), 'Conditions générales de location de ' . s('site_name') . '.');
?>
<section class="page-hero"><div class="wrap"><div class="eyebrow"><?= e(s('site_name')) ?></div><h1>Conditions de location</h1></div></section>
<section><div class="wrap"><div class="card legal" style="max-width:860px;margin:0 auto">
<?= render_doc(s('bk_cgv')) ?>
  <p class="muted" style="margin-top:28px;font-size:.88rem"><?= e(s('bk_company_name')) ?><?= s('bk_company_form') ? ', ' . e(s('bk_company_form')) : '' ?> — <?= e(str_replace("\n", ', ', s('bk_company_address'))) ?><?= s('bk_company_siren') ? ' — SIREN ' . e(s('bk_company_siren')) : '' ?></p>
</div></div></section>
<?php public_footer();
