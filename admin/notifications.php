<?php
/** Réglage des notifications : chaque étape d'une réservation × e-mail client, e-mail administration, appli. */
require __DIR__ . '/_init.php';
require_perm('reglages');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    require_reauth('notifications.php'); // les alertes contiennent les coordonnées des clients
    $emails = trim((string)($_POST['bk_notify_email'] ?? ''));
    $bad = array_filter(preg_split('/[\s,;]+/', $emails), fn($a) => $a !== '' && !filter_var($a, FILTER_VALIDATE_EMAIL));
    if ($bad) {
        flash('Adresse(s) e-mail non valide(s) : ' . implode(', ', $bad), 'err');
        redirect('notifications.php');
    }
    save_setting('bk_notify_email', implode(', ', array_filter(preg_split('/[\s,;]+/', $emails))));
    $on = (array)($_POST['n'] ?? []);
    foreach (NOTIF_EVENTS as $ev => [, , $channels]) {
        foreach ($channels as $ch => $default) {
            save_setting(notif_key($ev, $ch), !empty($on[$ev][$ch]) ? '1' : '0');
        }
    }
    settings(true);
    admin_log('Réglages des notifications modifiés');
    flash('Notifications enregistrées.');
    redirect('notifications.php');
}

$devices = (int)db()->query('SELECT COUNT(*) FROM push_subs')->fetchColumn();
admin_header('Notifications', 'reglages');
?>
<h1>Réglages des réservations</h1>
<p class="intro">Choisissez qui est prévenu, et comment, à chaque étape d’une réservation.</p>
<div class="layout">
  <aside class="side">
    <?php foreach (BOOKING_SETTINGS as $key => $sec): if ($key === 'tarifs') continue; ?>
      <a href="reglages.php?s=<?= e($key) ?>"><?= e($sec['title']) ?></a>
    <?php endforeach; ?>
    <a href="notifications.php" class="on">Notifications</a>
    <a href="sauvegardes.php">Sauvegardes</a>
  </aside>
  <div style="min-width:0">
    <form method="post" class="card">
      <?= csrf_field() ?>
      <h2>Notifications</h2>
      <p class="muted" style="margin-top:-6px">Cochez ce qui doit partir automatiquement. Le texte des e-mails aux clients se modifie dans <a href="modeles.php">Modèles d’e-mails</a>.
        Pour une annulation, un changement de date ou une caution faits depuis la fiche, la case « Prévenir le client » est simplement pré-cochée selon ce réglage : vous pouvez la décocher au cas par cas.</p>

      <div style="overflow-x:auto;margin:14px 0">
      <table class="notif">
        <tr><th>Étape</th><?php foreach (NOTIF_CHANNELS as $ch => $label): ?><th class="c"><?= e($label) ?></th><?php endforeach; ?></tr>
        <?php foreach (NOTIF_EVENTS as $ev => [$label, $help, $channels]): ?>
        <tr>
          <td><strong><?= e($label) ?></strong><br><span class="muted" style="font-size:.85rem"><?= e($help) ?></span></td>
          <?php foreach (NOTIF_CHANNELS as $ch => $cl): ?>
          <td class="c"><?php if (isset($channels[$ch])): ?><label class="tick" title="<?= e($cl) ?>"><input type="checkbox" name="n[<?= e($ev) ?>][<?= e($ch) ?>]" value="1"<?= notif_on($ev, $ch) ? ' checked' : '' ?>><span class="sr"><?= e($label . ' — ' . $cl) ?></span></label><?php else: ?><span class="muted" aria-label="sans objet">—</span><?php endif; ?></td>
          <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
      </table>
      </div>

      <?= field('bk_notify_email', 'Adresse(s) qui reçoivent les e-mails d’administration (plusieurs possibles, séparées par des virgules)', s('bk_notify_email'), 'text', 'Ex. lasallern6@gmail.com, associe@exemple.fr') ?>
      <div class="hint" style="margin:6px 0 16px">
        <strong>Notifications sur l’appli :</strong> elles arrivent sur les téléphones où un administrateur a activé les notifications (<a href="compte.php">Mon compte</a> › Application et notifications) — <?= $devices ?> appareil(s) actuellement.
        Chaque administrateur peut aussi, pour lui seul, couper certaines de ces notifications dans <a href="compte.php#mes-notifications">Mon compte</a>.
      </div>
      <?= reauth_field() ?>
      <button class="btn gold">Enregistrer</button>
    </form>
  </div>
</div>
<style>
table.notif th.c,table.notif td.c{text-align:center;width:120px}
table.notif td{vertical-align:middle}
table.notif label.tick{display:inline-flex;padding:8px;cursor:pointer;position:relative}
table.notif input[type=checkbox]{width:20px;height:20px;cursor:pointer}
.sr{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)}
@media(max-width:640px){table.notif th.c{font-size:.72rem;width:70px}table.notif td.c{width:70px}}
</style>
<?php admin_footer();
