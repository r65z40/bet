<?php
/** Ordre et affichage des sections de la page d'accueil. */
require __DIR__ . '/_init.php';
require_perm('contenu');

$v2 = isset($_GET['v']) ? $_GET['v'] === '2' : s('home_design') === 'nouveau'; // version dont on règle les sections
$sfx = $v2 ? '_v2' : '';
$self = 'sections.php?v=' . ($v2 ? '2' : '1');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    if (($_POST['do'] ?? '') === 'design') {
        $d = ($_POST['design'] ?? '') === 'nouveau' ? 'nouveau' : 'classique';
        save_setting('home_design', $d);
        admin_log('Page d’accueil : ' . ($d === 'nouveau' ? 'nouvelle version activée' : 'version d’origine rétablie'));
        flash($d === 'nouveau' ? 'La nouvelle page d’accueil est en ligne.' : 'La page d’accueil d’origine est rétablie.');
        redirect('sections.php?v=' . ($d === 'nouveau' ? '2' : '1'));
    }
    $order = array_keys(home_sections($v2));
    $visible = array_map('strval', (array)($_POST['visible'] ?? []));
    $hidden = array_values(array_diff($order, $visible));

    [$do, $key] = array_pad(explode(':', (string)($_POST['do'] ?? 'save'), 2), 2, '');
    if (!isset(HOME_SECTIONS[$key])) {
        $key = '';
    }
    $i = array_search($key, $order, true);
    if ($i !== false && in_array($do, ['up', 'down', 'first', 'last'], true)) {
        array_splice($order, $i, 1);
        $target = match ($do) {
            'up' => max(0, $i - 1), 'down' => min(count($order), $i + 1), 'first' => 0, 'last' => count($order),
        };
        array_splice($order, $target, 0, [$key]);
    } elseif ($do === 'reset') {
        $order = $v2 ? HOME_V2_ORDER : array_keys(array_filter(HOME_SECTIONS, fn($d) => empty($d['v2'])));
        $hidden = [];
    }
    save_setting('sections_order' . $sfx, implode(',', $order));
    save_setting('sections_hidden' . $sfx, implode(',', $hidden));
    flash($do === 'reset' ? 'Ordre d’origine rétabli.' : ($do === 'save' ? 'Sections enregistrées.' : 'Ordre modifié.'));
    redirect($self . ($key !== '' && $do !== 'reset' ? '#s-' . $key : ''));
}

$sections = home_sections($v2);
$live = s('home_design') === 'nouveau';
$keys = array_keys($sections);
$notes = [
    'avis'   => s('google_rating') === '' && s('salles1001_rating') === '' && !reviews() ? 'Masquée automatiquement tant qu’aucun avis ni note n’est saisi.' : '',
    'plan'   => 'Le lien « Plan » du menu reste toujours disponible.',
    'equipements' => $v2 ? 'Regroupe aussi le mobilier et la vaisselle, avec une photo.' : '',
    'tarifs' => $v2 ? 'Avec les prochaines dates libres, lues dans votre planning.' : '',
    'contact' => $v2 ? 'Avec formulaire de contact, téléphone et WhatsApp (Textes › Nouvelle page d’accueil).' : '',
    'bandeau1' => $v2 ? 'Photo et phrase : Textes › Nouvelle page d’accueil.' : '',
    'bandeau2' => $v2 ? 'Photo et phrase : Textes › Nouvelle page d’accueil.' : '',
    'chiffres' => $v2 ? 'Les chiffres se modifient dans Listes & cartes.' : '',
    'evenements' => $v2 ? 'Les cartes (titre, texte, photo) se modifient dans Listes & cartes.' : '',
    'faq' => $v2 ? 'Les questions se modifient dans Listes & cartes.' : '',
];
admin_header('Sections', 'sections');
?>
<h1>Ordre des sections</h1>
<p class="intro">Choisissez l’ordre d’affichage des blocs de la page d’accueil, ou masquez-en certains. Le menu du site suit le même ordre.
  Le bandeau d’accueil et ses 4 points forts restent toujours en haut.</p>

<form method="post" class="card" style="max-width:820px">
  <?= csrf_field() ?>
  <h2>Version de la page d’accueil</h2>
  <label style="display:block;margin:6px 0"><input type="radio" name="design" value="classique" <?= $live ? '' : 'checked' ?>> Version d’origine</label>
  <label style="display:block;margin:6px 0"><input type="radio" name="design" value="nouveau" <?= $live ? 'checked' : '' ?>> <strong>Nouvelle version</strong> : bandeaux photo, cartes événements, chiffres clés, avis qui défilent, galerie en mosaïque, prochaines dates libres, FAQ, formulaire de contact</label>
  <div class="actions" style="margin-top:12px">
    <button class="btn gold" name="do" value="design">Mettre en ligne cette version</button>
    <?php if (!$live): ?><a class="btn light" href="../?apercu=1" target="_blank">👁 Aperçu de la nouvelle version ↗</a><?php endif; ?>
  </div>
</form>

<div style="display:flex;gap:8px;margin:0 0 12px;max-width:820px">
  <a class="btn <?= $v2 ? 'light' : '' ?> sm" href="sections.php?v=1">Sections — version d’origine<?= $live ? '' : ' (en ligne)' ?></a>
  <a class="btn <?= $v2 ? '' : 'light' ?> sm" href="sections.php?v=2">Sections — nouvelle version<?= $live ? ' (en ligne)' : '' ?></a>
</div>

<form method="post" action="<?= e($self) ?>" class="card" style="max-width:820px">
  <?= csrf_field() ?>
  <button name="do" value="save" tabindex="-1" aria-hidden="true" style="position:absolute;left:-9999px"></button>
  <div class="item-row" style="grid-template-columns:40px 1fr;opacity:.7">
    <span class="pill">⌂</span>
    <div><strong>Bandeau d’accueil + points forts</strong> <span class="muted">— toujours en premier</span></div>
  </div>
  <?php foreach ($sections as $k => $on): $i = array_search($k, $keys, true); ?>
    <div class="item-row <?= $on ? '' : 'hidden' ?>" id="s-<?= e($k) ?>" style="grid-template-columns:40px 1fr auto;align-items:center">
      <span class="pill"><?= $i + 1 ?></span>
      <div>
        <strong><?= e(HOME_SECTIONS[$k]['label']) ?></strong>
        <?php if (!empty($notes[$k])): ?><br><small class="muted"><?= e($notes[$k]) ?></small><?php endif; ?>
      </div>
      <div class="ctrl">
        <label><input type="checkbox" name="visible[]" value="<?= e($k) ?>" <?= $on ? 'checked' : '' ?>> Visible</label>
        <button class="btn light sm" name="do" value="first:<?= e($k) ?>" title="Tout en haut" <?= $i === 0 ? 'disabled' : '' ?>>⤒</button>
        <button class="btn light sm" name="do" value="up:<?= e($k) ?>" title="Monter" <?= $i === 0 ? 'disabled' : '' ?>>▲</button>
        <button class="btn light sm" name="do" value="down:<?= e($k) ?>" title="Descendre" <?= $i === count($keys) - 1 ? 'disabled' : '' ?>>▼</button>
        <button class="btn light sm" name="do" value="last:<?= e($k) ?>" title="Tout en bas" <?= $i === count($keys) - 1 ? 'disabled' : '' ?>>⤓</button>
      </div>
    </div>
  <?php endforeach; ?>
  <div class="actions" style="margin-top:18px">
    <button class="btn gold" name="do" value="save">💾 Enregistrer</button>
    <a class="btn light" href="../<?= $v2 && !$live ? '?apercu=1' : '' ?>" target="_blank">Voir le résultat ↗</a>
    <button class="btn danger" name="do" value="reset" onclick="return confirm('Rétablir l’ordre d’origine et réafficher toutes les sections ?')" style="margin-left:auto">Rétablir l’ordre d’origine</button>
  </div>
</form>
<?php admin_footer();
