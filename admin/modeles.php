<?php
/** Modèles d'e-mails : objet et texte de chaque e-mail automatique, apparence commune. */
require __DIR__ . '/_init.php';
require_perm('reglages');

$keys = array_keys(MAIL_TEMPLATES);
$cur = (string)($_GET['m'] ?? 'confirmation');
if ($cur !== 'apparence' && !isset(MAIL_TEMPLATES[$cur])) {
    $cur = 'confirmation';
}

/** E-mail d'exemple d'un modèle (données fictives). */
function sample_mail(string $key): array
{
    $b = mail_sample_booking();
    [$vars, $blocks] = mail_booking_data($b);
    $vars += ['identifiant' => 'marie', 'montant_rembourse' => money2(360), 'arrivee' => 'samedi 12 décembre 2026 à 10h00', 'depart' => 'dimanche 13 décembre 2026 à 10h00', 'jours' => '7', 'lien_avis' => review_url(), 'ancienne_date' => 'Du samedi 5 décembre 2026 à 10h00 au dimanche 6 décembre 2026 à 10h00', 'montant_caution' => money2(1000), 'montant_retenu' => money2(150), 'motif' => 'Une chaise cassée et une tache sur la moquette de l’entrée.'];
    $blocks += [
        'attention' => '',
        'option_cheque' => $key === 'caution_demande' ? '<p style="margin:0 0 14px">Vous préférez le chèque ? Remettez-le simplement lors de l’état des lieux d’entrée : il ne sera pas encaissé et vous sera rendu après l’état des lieux de sortie.</p>' : '',
        'changement' => mail_move_block('Du samedi 5 décembre 2026 à 10h00 au dimanche 6 décembre 2026 à 10h00', bk_slot_label($b)),
        'infos_pratiques' => s('bk_practical_info') !== '' ? '<div style="background:#f4f6f8;border-radius:8px;padding:12px 16px;margin:0 0 14px">' . render_mail_paragraphs(s('bk_practical_info')) . '</div>' : '',
        'bouton_avis' => '<p style="text-align:center;margin:20px 0">' . mail_button(review_url() ?: bk_url(''), '★ Laisser un avis Google') . '</p>',
        'remboursement' => '<p style="margin:0 0 14px">Un remboursement de <strong>' . money2(360) . '</strong> a été effectué sur votre moyen de paiement.</p>',
        'bouton' => '<p>' . mail_button(bk_url(''), $key === 'invitation' ? 'Créer mon mot de passe' : ($key === 'motdepasse' ? 'Choisir un nouveau mot de passe' : ($key === 'alerte' ? 'Voir dans l’administration' : ($key === 'caution_demande' ? '🔒 Déposer la caution' : 'Voir ma réservation')))) . '</p>',
    ];
    return mail_from_template($key, $vars, $blocks);
}

/** Corps de l'e-mail, sans les balises <html>/<body>, pour l'afficher dans la page. */
function preview_html(string $h): string
{
    return preg_match('#<body[^>]*>(.*)</body>#s', $h, $m) ? $m[1] : $h;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $do = (string)($_POST['do'] ?? 'save');
    if ($cur === 'apparence') {
        foreach (['mail_color_header', 'mail_color_accent'] as $k) {
            $v = (string)($_POST[$k] ?? '');
            if (preg_match('/^#[0-9a-fA-F]{6}$/', $v)) save_setting($k, strtolower($v));
        }
        save_setting('mail_show_logo', !empty($_POST['mail_show_logo']) ? '1' : '0');
        save_setting('mail_footer', mb_substr(trim(str_replace("\r\n", "\n", (string)($_POST['mail_footer'] ?? ''))), 0, 500));
        if ($do === 'reset') {
            foreach (MAIL_STYLE_DEFAULTS as $k => $v) save_setting($k, '');
        }
        settings(true);
        admin_log('Apparence des e-mails modifiée');
        flash($do === 'reset' ? 'Apparence d’origine rétablie.' : 'Apparence enregistrée.');
    } else {
        if ($do === 'reset') {
            save_setting('tpl_' . $cur . '_subject', '');
            save_setting('tpl_' . $cur . '_body', '');
            flash('Modèle d’origine rétabli.');
        } else {
            $subject = mb_substr(trim(str_replace(["\r", "\n"], ' ', (string)($_POST['subject'] ?? ''))), 0, 200);
            $body = mb_substr(trim(str_replace("\r\n", "\n", (string)($_POST['body'] ?? ''))), 0, 10000);
            // Valeur identique au modèle d'origine = on ne stocke rien (les améliorations futures s'appliqueront)
            save_setting('tpl_' . $cur . '_subject', $subject === MAIL_TEMPLATES[$cur]['subject'] ? '' : $subject);
            save_setting('tpl_' . $cur . '_body', $body === MAIL_TEMPLATES[$cur]['body'] ? '' : $body);
            if ($subject === '' || $body === '') {
                flash('Objet ou texte vide : le modèle d’origine est utilisé pour cette partie.', 'err');
            }
        }
        settings(true);
        admin_log('Modèle d’e-mail modifié : ' . MAIL_TEMPLATES[$cur]['label']);
        if ($do === 'test') {
            [$s, $h] = sample_mail($cur);
            $to = current_admin()['email'] ?: s('bk_notify_email');
            $ok = send_mail($to, '[TEST] ' . $s, $h);
            flash($ok ? 'Exemple envoyé à ' . $to . '.' : 'Échec de l’envoi : ' . mail_last_error(), $ok ? 'ok' : 'err');
        } elseif ($do !== 'reset') {
            flash('Modèle enregistré.');
        }
    }
    redirect('modeles.php?m=' . urlencode($cur));
}

admin_header('Modèles d’e-mails', 'emails');
?>
<h1>E-mails clients</h1>
<nav class="tabs">
  <?php if (admin_can('emails')): ?><a href="emails.php">✉ Écrire</a><a href="emails.php?vue=historique">Envois</a><?php endif; ?>
  <a href="modeles.php" class="on">Modèles</a>
  <?php if (admin_can('emails')): ?><a href="emails.php?vue=desinscrits">Désinscrits</a><?php endif; ?>
</nav>

<div class="layout">
  <aside class="side">
    <h3>E-mails automatiques</h3>
    <?php foreach (MAIL_TEMPLATES as $k => $t): ?>
      <a href="?m=<?= e($k) ?>" class="<?= $k === $cur ? 'on' : '' ?>"><?= e($t['label']) ?><?= s('tpl_' . $k . '_subject') . s('tpl_' . $k . '_body') !== '' ? ' ✎' : '' ?></a>
    <?php endforeach; ?>
    <h3>Mise en forme</h3>
    <a href="?m=apparence" class="<?= $cur === 'apparence' ? 'on' : '' ?>">Apparence (couleurs, logo, pied de page)</a>
  </aside>
  <div>
<?php if ($cur === 'apparence'): ?>
    <form method="post" class="card">
      <?= csrf_field() ?>
      <h2>Apparence de tous les e-mails</h2>
      <div class="grid g2">
        <label class="field"><span>Couleur du bandeau</span><input type="color" name="mail_color_header" value="<?= e(mail_style('mail_color_header')) ?>" style="height:46px"></label>
        <label class="field"><span>Couleur des boutons</span><input type="color" name="mail_color_accent" value="<?= e(mail_style('mail_color_accent')) ?>" style="height:46px"></label>
      </div>
      <label style="display:block;margin-bottom:14px"><input type="checkbox" name="mail_show_logo" value="1" <?= mail_style('mail_show_logo') === '1' ? 'checked' : '' ?>> Afficher le logo dans le bandeau</label>
      <?= field('mail_footer', 'Pied de page', mail_style('mail_footer'), 'textarea', 'Variables : {site} {adresse} {email_contact}') ?>
      <div class="actions">
        <button class="btn gold" name="do" value="save">💾 Enregistrer</button>
        <button class="btn danger sm" name="do" value="reset" onclick="return confirm('Rétablir les couleurs et le pied de page d’origine ?')" style="margin-left:auto">Rétablir l’apparence d’origine</button>
      </div>
    </form>
    <?php [$s, $h] = sample_mail('confirmation'); ?>
    <div class="card"><h2>Aperçu</h2><div class="mail-preview" style="background:#f4f6f8;border-radius:10px;overflow:auto"><?= preview_html($h) ?></div></div>
<?php else: $t = MAIL_TEMPLATES[$cur]; ?>
    <form method="post" class="card">
      <?= csrf_field() ?>
      <h2><?= e($t['label']) ?></h2>
      <?= field('subject', 'Objet', mail_tpl_subject($cur)) ?>
      <label class="field"><span>Texte</span><textarea name="body" id="tplBody" rows="14"><?= e(mail_tpl_body($cur)) ?></textarea></label>
      <p class="muted" style="margin:-4px 0 6px;font-size:.88rem">Cliquez pour insérer. Les <strong>blocs</strong> se placent seuls sur une ligne. <code>**gras**</code> autorisé ; ligne vide = nouveau paragraphe.</p>
      <div class="actions" style="gap:6px;margin-bottom:16px">
        <?php foreach (array_merge($t['vars'], ['site']) as $v): ?><button type="button" class="btn light sm ins" data-v="{<?= e($v) ?>}" title="<?= e(MAIL_VAR_HELP[$v] ?? '') ?>">{<?= e($v) ?>}</button><?php endforeach; ?>
        <?php foreach ($t['blocks'] as $v): ?><button type="button" class="btn light sm ins blk" data-v="{<?= e($v) ?>}" title="<?= e(MAIL_VAR_HELP[$v] ?? '') ?>" style="border-color:#e7c360;background:#fff8e8">▦ {<?= e($v) ?>}</button><?php endforeach; ?>
      </div>
      <div class="actions">
        <button class="btn gold" name="do" value="save">💾 Enregistrer</button>
        <button class="btn light" name="do" value="test">✉ Enregistrer et m’envoyer un exemple</button>
        <button class="btn danger sm" name="do" value="reset" onclick="return confirm('Rétablir le texte d’origine de cet e-mail ?')" style="margin-left:auto">Rétablir l’original</button>
      </div>
    </form>
    <?php [$s, $h] = sample_mail($cur); ?>
    <div class="card">
      <h2>Aperçu <small class="muted" style="font-family:Inter,sans-serif;font-size:.85rem">(exemple fictif, version enregistrée)</small></h2>
      <p><strong>Objet :</strong> <?= e($s) ?></p>
      <div class="mail-preview" style="background:#f4f6f8;border-radius:10px;overflow:auto"><?= preview_html($h) ?></div>
    </div>
    <script>
    document.querySelectorAll('.ins').forEach(b => b.addEventListener('click', () => {
      const ta = document.getElementById('tplBody');
      const v = b.classList.contains('blk') ? '\n' + b.dataset.v + '\n' : b.dataset.v;
      const s = ta.selectionStart, e = ta.selectionEnd;
      ta.value = ta.value.slice(0, s) + v + ta.value.slice(e);
      ta.focus(); ta.selectionStart = ta.selectionEnd = s + v.length;
    }));
    </script>
<?php endif; ?>
  </div>
</div>
<?php admin_footer();
