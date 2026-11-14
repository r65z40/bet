<?php
/** Changement d'identifiant / mot de passe. */
require __DIR__ . '/_init.php';
$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'notifprefs') {
    check_csrf();
    $want = array_keys((array)($_POST['p'] ?? []));
    $prev = admin_notif_off(db()->query('SELECT notif_off FROM admins WHERE id = ' . (int)$admin['id'])->fetch() ?: []);
    // une notification coupée pour tout le monde n'est pas modifiable ici : son choix personnel est conservé
    $off = array_values(array_filter(array_keys(NOTIF_EVENTS), fn($ev) => isset(NOTIF_EVENTS[$ev][2]['push'])
        && (notif_on($ev, 'push') ? !in_array($ev, $want, true) : in_array($ev, $prev, true))));
    db()->prepare('UPDATE admins SET notif_off = ? WHERE id = ?')->execute([implode(',', $off), $admin['id']]);
    // « Ne pas déranger »
    $hm = fn($v) => preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string)$v) ? (string)$v : '';
    $from = !empty($_POST['quiet']) ? $hm($_POST['quiet_from'] ?? '') : '';
    $to = $from !== '' ? $hm($_POST['quiet_to'] ?? '') : '';
    db()->prepare('UPDATE admins SET quiet_from = ?, quiet_to = ?, quiet_urgent = ? WHERE id = ?')
        ->execute([$to !== '' ? $from : '', $to, !empty($_POST['quiet_urgent']) ? 1 : 0, $admin['id']]);
    flash('Vos notifications sont enregistrées.');
    redirect('compte.php#mes-notifications');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $st = db()->prepare('SELECT password_hash FROM admins WHERE id = ?');
    $st->execute([$admin['id']]);
    $hash = (string)$st->fetchColumn();
    $user = trim((string)($_POST['username'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $new = (string)($_POST['new'] ?? '');

    if (!password_verify((string)($_POST['current'] ?? ''), $hash)) {
        flash('Mot de passe actuel incorrect.', 'err');
    } elseif ($user === '' || mb_strlen($user) > 50 || !preg_match('/^[\p{L}0-9._@ -]+$/u', $user)) {
        flash('Identifiant invalide (lettres, chiffres, point, tiret).', 'err');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('Adresse e-mail invalide.', 'err');
    } elseif ($new !== '' && mb_strlen($new) < 10) {
        flash('Le nouveau mot de passe doit contenir au moins 10 caractères.', 'err');
    } elseif ($new !== ($_POST['new2'] ?? '')) {
        flash('Les deux nouveaux mots de passe ne correspondent pas.', 'err');
    } else {
        $dup = db()->prepare("SELECT id FROM admins WHERE (lower(username) = lower(?) OR (email != '' AND lower(email) = lower(?))) AND id != ?");
        $dup->execute([$user, $email, $admin['id']]);
        if ($dup->fetch()) {
            flash('Cet identifiant ou cet e-mail est déjà utilisé par un autre compte.', 'err');
        } else {
            db()->prepare('UPDATE admins SET username = ?, email = ? WHERE id = ?')->execute([$user, $email, $admin['id']]);
            if ($new !== '') {
                admin_set_password((int)$admin['id'], $new, true); // les autres appareils sont déconnectés
                admin_log('Mot de passe modifié');
            }
            flash('Compte mis à jour.' . ($new !== '' ? ' Vos autres sessions ont été fermées.' : ''));
        }
    }
    redirect('compte.php');
}

admin_header('Mon compte', 'compte');
?>
<h1>Mon compte</h1>
<form method="post" class="card" style="max-width:520px">
  <?= csrf_field() ?>
  <?= field('username', 'Identifiant', $admin['username']) ?>
  <?= field('email', 'E-mail (connexion et mot de passe oublié)', $admin['email'], 'email') ?>
  <?= field('new', 'Nouveau mot de passe (laisser vide pour ne pas changer)', '', 'password') ?>
  <?= field('new2', 'Confirmer le nouveau mot de passe', '', 'password') ?>
  <hr style="border:0;border-top:1px solid var(--line);margin:8px 0 16px">
  <?= field('current', 'Mot de passe actuel (obligatoire)', '', 'password') ?>
  <button class="btn gold">Enregistrer</button>
</form>
<p class="muted">Vos droits : <?= e(implode(', ', array_map(fn($p) => ADMIN_PERMS[$p][0], $admin['perms_list'])) ?: 'aucun') ?>.</p>

<div class="card" id="app" style="max-width:640px">
  <h2>📱 Application et notifications</h2>
  <div id="installHelp">
    <p class="muted" style="margin-top:-6px">Installez l’administration sur l’écran d’accueil de votre téléphone : elle s’ouvre comme une application et peut vous prévenir à chaque nouvelle réservation.</p>
    <ol style="line-height:1.7;padding-left:20px">
      <li><strong>iPhone</strong> : ouvrez cette page dans <strong>Safari</strong>, touchez <strong>Partager</strong> (carré avec une flèche) › <strong>Sur l’écran d’accueil</strong> › Ajouter. Ouvrez ensuite l’application « RN6 Admin », connectez-vous, revenez ici et activez les notifications (iOS 16.4 ou plus récent).</li>
      <li><strong>Android</strong> (Chrome) : menu ⋮ › <strong>Installer l’application</strong> (ou « Ajouter à l’écran d’accueil »).</li>
      <li><strong>Ordinateur</strong> (Chrome, Edge) : icône d’installation dans la barre d’adresse ; les notifications fonctionnent aussi sans installer.</li>
    </ol>
  </div>
  <p id="pushState" class="muted"></p>
  <div class="actions">
    <button class="btn gold" type="button" id="pushOn" hidden>🔔 Activer les notifications sur cet appareil</button>
    <button class="btn light" type="button" id="pushTest" hidden>Envoyer une notification de test</button>
    <button class="btn danger sm" type="button" id="pushOff" hidden>Désactiver sur cet appareil</button>
  </div>
  <?php $nbDev = db()->prepare('SELECT COUNT(*) FROM push_subs WHERE admin_id = ?'); $nbDev->execute([$admin['id']]); ?>
  <p class="muted" style="font-size:.85rem;margin-bottom:0">Appareils abonnés sur votre compte : <?= (int)$nbDev->fetchColumn() ?>.<?= admin_can('reservations') ? '' : ' Les notifications de réservation nécessitent le droit « Réservations ».' ?></p>
</div>

<?php $myOff = admin_notif_off(db()->query('SELECT notif_off FROM admins WHERE id = ' . (int)$admin['id'])->fetch() ?: []);
$q = db()->query('SELECT quiet_from, quiet_to, quiet_urgent FROM admins WHERE id = ' . (int)$admin['id'])->fetch(); $quietOn = $q['quiet_from'] !== ''; ?>
<form method="post" class="card" id="mes-notifications" style="max-width:640px">
  <?= csrf_field() ?><input type="hidden" name="do" value="notifprefs">
  <h2>🔔 Mes notifications sur l’appli</h2>
  <p class="muted" style="margin-top:-6px">Ce que <strong>vous</strong> recevez sur vos appareils. Une notification désactivée pour tout le monde (<?= admin_can('reglages') ? '<a href="notifications.php">Réglages › Notifications</a>' : 'par l’administrateur' ?>) n’est envoyée à personne.</p>
  <?php foreach (NOTIF_EVENTS as $ev => [$label, $help, $channels]): if (!isset($channels['push'])) continue; $global = notif_on($ev, 'push'); ?>
    <label style="display:flex;gap:10px;align-items:flex-start;margin:0 0 10px<?= $global ? '' : ';opacity:.55' ?>">
      <input type="checkbox" name="p[<?= e($ev) ?>]" value="1" style="margin-top:4px"<?= !in_array($ev, $myOff, true) ? ' checked' : '' ?><?= $global ? '' : ' disabled' ?>>
      <span><strong><?= e($label) ?></strong><?= $global ? '' : ' <em class="muted">(désactivée pour tout le monde)</em>' ?><br><span class="muted" style="font-size:.85rem"><?= e($help) ?></span></span>
    </label>
  <?php endforeach; ?>
  <div style="border-top:1px solid var(--line);margin:16px 0 14px;padding-top:14px">
    <label style="display:flex;gap:10px;align-items:center;margin-bottom:8px"><input type="checkbox" name="quiet" value="1"<?= $quietOn ? ' checked' : '' ?>> <strong>🌙 Ne pas déranger</strong></label>
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-left:28px">
      de <input type="time" name="quiet_from" value="<?= e($quietOn ? $q['quiet_from'] : '20:00') ?>" style="width:auto"> à <input type="time" name="quiet_to" value="<?= e($quietOn ? $q['quiet_to'] : '08:00') ?>" style="width:auto">
    </div>
    <p class="muted" style="font-size:.85rem;margin:6px 0 8px 28px">Pendant cette plage, rien n’arrive sur vos appareils : les notifications sont gardées et vous parviennent en une fois à la fin (vers <?= e($quietOn ? $q['quiet_to'] : '08:00') ?>, au passage suivant de la tâche planifiée). Les e-mails ne sont pas concernés.</p>
    <label style="display:flex;gap:10px;align-items:center;margin-left:28px"><input type="checkbox" name="quiet_urgent" value="1"<?= (int)$q['quiet_urgent'] ? ' checked' : '' ?>> Laisser passer les alertes urgentes (conflit avec l’agenda après un paiement)</label>
  </div>
  <button class="btn">Enregistrer mes notifications</button>
</form>
<script>
(function () {
  const KEY = <?= json_encode(vapid_public_b64()) ?>;
  const CSRF = <?= json_encode(csrf_token()) ?>;
  const $ = id => document.getElementById(id);
  const state = t => { $('pushState').textContent = t; };
  const standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
  const ios = /iPhone|iPad|iPod/.test(navigator.userAgent);
  if (standalone) $('installHelp').hidden = true;
  const api = (data) => fetch('push.php', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(Object.assign({ csrf: CSRF }, data)) }).then(r => r.json());
  const key = () => { const s = KEY.replace(/-/g, '+').replace(/_/g, '/'); const raw = atob(s + '='.repeat((4 - s.length % 4) % 4)); return Uint8Array.from(raw, c => c.charCodeAt(0)); };
  if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
    state(ios && !standalone ? 'Sur iPhone, installez d’abord l’application (voir ci-dessus), puis ouvrez-la pour activer les notifications.' : 'Ce navigateur ne permet pas les notifications.');
    return;
  }
  async function refresh() {
    const reg = await navigator.serviceWorker.ready;
    const sub = await reg.pushManager.getSubscription();
    $('pushOn').hidden = !!sub; $('pushOff').hidden = !sub; $('pushTest').hidden = !sub;
    state(sub ? '✅ Notifications activées sur cet appareil.' : (Notification.permission === 'denied' ? 'Notifications bloquées : autorisez-les dans les réglages du téléphone ou du navigateur.' : 'Notifications non activées sur cet appareil.'));
  }
  $('pushOn').addEventListener('click', async () => {
    try {
      const perm = await Notification.requestPermission();
      if (perm !== 'granted') { state('Autorisation refusée.'); return; }
      const reg = await navigator.serviceWorker.ready;
      const sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: key() });
      const r = await api({ do: 'subscribe', subscription: sub.toJSON() });
      if (r.error) { state(r.error); await sub.unsubscribe(); }
      refresh();
    } catch (e) { state('Activation impossible : ' + e.message); }
  });
  $('pushOff').addEventListener('click', async () => {
    const reg = await navigator.serviceWorker.ready; const sub = await reg.pushManager.getSubscription();
    if (sub) { await api({ do: 'unsubscribe', subscription: sub.toJSON() }); await sub.unsubscribe(); }
    refresh();
  });
  $('pushTest').addEventListener('click', async () => { const r = await api({ do: 'test' }); state(r.sent ? 'Notification envoyée : elle doit apparaître dans quelques secondes.' : 'Échec de l’envoi de la notification.'); });
  refresh();
})();
</script>
<?php admin_footer();
