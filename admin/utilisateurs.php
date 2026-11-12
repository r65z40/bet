<?php
/** Équipe : comptes administrateurs, droits, invitations, journal d'activité. */
require __DIR__ . '/_init.php';
$me = require_perm('utilisateurs');

$editId = isset($_GET['id']) ? (int)$_GET['id'] : null; // 0 = nouveau compte

function find_admin(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM admins WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $do = (string)($_POST['do'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    $target = $id ? find_admin($id) : null;
    $self = $target && (int)$target['id'] === (int)$me['id'];
    if (in_array($do, ['save', 'link', 'delete'], true)) {
        require_reauth('utilisateurs.php' . ($do === 'save' ? '?id=' . $id : ''));
    }

    if ($do === 'save') {
        $username = trim((string)($_POST['username'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $perms = array_values(array_intersect((array)($_POST['perms'] ?? []), array_keys(ADMIN_PERMS)));
        $active = !empty($_POST['active']) ? 1 : 0;
        $errors = [];
        if ($username === '' || mb_strlen($username) > 50 || !preg_match('/^[\p{L}0-9._@ -]+$/u', $username)) $errors[] = 'Identifiant invalide (lettres, chiffres, point, tiret).';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Une adresse e-mail valide est obligatoire (invitation et mot de passe oublié).';
        if ($self && !in_array('utilisateurs', $perms, true)) $errors[] = 'Vous ne pouvez pas retirer votre propre droit « Utilisateurs ».';
        if ($self && !$active) $errors[] = 'Vous ne pouvez pas désactiver votre propre compte.';
        if ($target && !$self && (!$active || !in_array('utilisateurs', $perms, true)) && in_array('utilisateurs', admin_perms($target), true) && admin_managers_count((int)$target['id']) === 0) {
            $errors[] = 'Il doit rester au moins un compte actif avec le droit « Utilisateurs ».';
        }
        $dup = db()->prepare('SELECT id FROM admins WHERE (lower(username) = lower(?) OR (email != \'\' AND lower(email) = lower(?))) AND id != ?');
        $dup->execute([$username, $email, $id]);
        if ($dup->fetch()) $errors[] = 'Cet identifiant ou cet e-mail est déjà utilisé par un autre compte.';
        if ($errors) {
            foreach ($errors as $e) flash($e, 'err');
            redirect('utilisateurs.php?id=' . $id);
        }
        if ($target) {
            db()->prepare('UPDATE admins SET username = ?, email = ?, perms = ?, active = ? WHERE id = ?')
                ->execute([$username, $email, implode(',', $perms), $active, $id]);
            admin_log('Compte « ' . $username . ' » modifié — droits : ' . (implode(', ', $perms) ?: 'aucun') . ($active ? '' : ' — DÉSACTIVÉ'));
            flash('Compte enregistré.' . ($active ? '' : ' Il est désactivé : ses sessions sont fermées.'));
            redirect('utilisateurs.php');
        }
        // Nouveau compte : mot de passe inutilisable jusqu'à l'invitation
        db()->prepare('INSERT INTO admins(username, email, perms, active, password_hash) VALUES(?,?,?,?,?)')
            ->execute([$username, $email, implode(',', $perms), 1, password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT)]);
        $new = find_admin((int)db()->lastInsertId());
        [$ok, $url] = admin_send_link($new, 'invite');
        admin_log('Compte « ' . $username . ' » créé — droits : ' . (implode(', ', $perms) ?: 'aucun'));
        if ($ok) {
            flash('Compte créé. Une invitation a été envoyée à ' . $email . ' (lien valable 48 h).');
        } else {
            $_SESSION['invite_link'] = $url;
            flash('Compte créé, mais l’e-mail d’invitation n’a pas pu partir (' . mail_last_error() . '). Transmettez le lien ci-dessous à la personne.', 'err');
        }
        redirect('utilisateurs.php');
    }
    if ($do === 'link' && $target && (int)$target['active']) {
        [$ok, $url] = admin_send_link($target, 'reset');
        admin_log('Lien de réinitialisation envoyé à « ' . $target['username'] . ' »');
        if ($ok) {
            flash('Lien de réinitialisation envoyé à ' . $target['email'] . ' (valable 1 h).');
        } else {
            $_SESSION['invite_link'] = $url;
            flash('L’e-mail n’a pas pu partir (' . mail_last_error() . '). Transmettez le lien ci-dessous à la personne.', 'err');
        }
        redirect('utilisateurs.php');
    }
    if ($do === 'delete' && $target) {
        if ($self) {
            flash('Vous ne pouvez pas supprimer votre propre compte.', 'err');
        } elseif (in_array('utilisateurs', admin_perms($target), true) && admin_managers_count((int)$target['id']) === 0) {
            flash('Il doit rester au moins un compte actif avec le droit « Utilisateurs ».', 'err');
        } else {
            db()->prepare('DELETE FROM admins WHERE id = ?')->execute([$id]);
            db()->prepare('DELETE FROM password_resets WHERE admin_id = ?')->execute([$id]);
            admin_log('Compte « ' . $target['username'] . ' » supprimé');
            flash('Compte supprimé.');
        }
        redirect('utilisateurs.php');
    }
    redirect('utilisateurs.php');
}

$vue = (string)($_GET['vue'] ?? '');
$inviteLink = $_SESSION['invite_link'] ?? null;
unset($_SESSION['invite_link']);
admin_header('Équipe', 'utilisateurs');
?>
<h1>Équipe</h1>
<nav class="tabs">
  <a href="utilisateurs.php" class="<?= $vue === '' && $editId === null ? 'on' : '' ?>">Comptes</a>
  <a href="utilisateurs.php?id=0" class="<?= $editId === 0 ? 'on' : '' ?>">＋ Ajouter</a>
  <a href="utilisateurs.php?vue=journal" class="<?= $vue === 'journal' ? 'on' : '' ?>">Journal d’activité</a>
</nav>
<?php if ($inviteLink): ?>
  <div class="hint">Lien à transmettre (usage unique) : <code style="word-break:break-all"><?= e($inviteLink) ?></code></div>
<?php endif; ?>

<?php if ($editId !== null):
    $a = $editId ? find_admin($editId) : null;
    if ($editId && !$a) { echo '<p>Compte introuvable.</p>'; admin_footer(); exit; }
    $a ??= ['id' => 0, 'username' => '', 'email' => '', 'perms' => 'reservations', 'active' => 1];
    $mine = admin_perms($a);
?>
<form method="post" class="card" style="max-width:820px">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
  <h2><?= $a['id'] ? 'Modifier « ' . e($a['username']) . ' »' : 'Nouveau compte' ?></h2>
  <div class="grid g2">
    <?= field('username', 'Identifiant', $a['username']) ?>
    <?= field('email', 'E-mail', $a['email'], 'email', $a['id'] ? 'Sert à la connexion et au mot de passe oublié.' : 'Une invitation y sera envoyée pour choisir le mot de passe.') ?>
  </div>
  <strong style="display:block;margin:6px 0 8px;color:var(--navy)">Rôle</strong>
  <div class="actions" style="margin-bottom:12px">
    <?php foreach (ADMIN_ROLES as $key => [$label, $perms]): ?>
      <button type="button" class="btn light sm role" data-perms="<?= e(implode(',', $perms)) ?>"><?= e($label) ?></button>
    <?php endforeach; ?>
  </div>
  <div class="grid g2" style="gap:8px">
    <?php foreach (ADMIN_PERMS as $key => [$label, $help]): ?>
      <label style="display:flex;gap:10px;align-items:flex-start;border:1px solid var(--line);border-radius:10px;padding:10px 12px;cursor:pointer">
        <input type="checkbox" name="perms[]" value="<?= e($key) ?>" <?= in_array($key, $mine, true) ? 'checked' : '' ?> style="margin-top:3px">
        <span><strong><?= e($label) ?></strong><br><small class="muted"><?= e($help) ?></small></span>
      </label>
    <?php endforeach; ?>
  </div>
  <?php if ($a['id']): ?>
    <label style="display:block;margin:14px 0"><input type="checkbox" name="active" value="1" <?= (int)$a['active'] ? 'checked' : '' ?>> Compte actif <small class="muted">(décocher pour bloquer l’accès sans supprimer le compte)</small></label>
  <?php else: ?>
    <input type="hidden" name="active" value="1">
  <?php endif; ?>
  <?= reauth_field() ?>
  <div class="actions" style="margin-top:14px">
    <button class="btn gold" name="do" value="save"><?= $a['id'] ? '💾 Enregistrer' : '✉ Créer et envoyer l’invitation' ?></button>
    <a class="btn light" href="utilisateurs.php">Annuler</a>
  </div>
</form>
<script>
document.querySelectorAll('.role').forEach(b => b.addEventListener('click', () => {
  const p = b.dataset.perms.split(',');
  document.querySelectorAll('input[name="perms[]"]').forEach(c => { c.checked = p.includes(c.value); });
}));
</script>

<?php elseif ($vue === 'journal'): ?>
<div class="card">
  <p class="muted" style="margin-top:0">Connexions, modifications sensibles, remboursements, envois d’e-mails… (300 dernières actions)</p>
  <div style="overflow-x:auto"><table>
    <tr><th>Date</th><th>Compte</th><th>Action</th></tr>
    <?php foreach (db()->query('SELECT * FROM admin_log ORDER BY id DESC LIMIT 300') as $l): ?>
      <tr><td style="white-space:nowrap"><?= e(date('d/m/Y H:i', strtotime($l['at']))) ?></td><td><?= e($l['username']) ?></td><td><?= e($l['action']) ?></td></tr>
    <?php endforeach; ?>
  </table></div>
</div>

<?php else: ?>
<div class="card">
  <div style="overflow-x:auto"><table>
    <tr><th>Compte</th><th>Rôle</th><th>Dernière connexion</th><th></th></tr>
    <?php foreach (db()->query('SELECT * FROM admins ORDER BY active DESC, username') as $a): ?>
      <tr style="<?= (int)$a['active'] ? '' : 'opacity:.55' ?>">
        <td><strong><?= e($a['username']) ?></strong><?= (int)$a['id'] === (int)$me['id'] ? ' <span class="pill">vous</span>' : '' ?><br><small class="muted"><?= e($a['email'] ?: 'pas d’e-mail') ?></small></td>
        <td><?= e(admin_role_label($a)) ?><?= (int)$a['active'] ? '' : ' <span class="pill" style="background:#fdecea;color:#b42318">désactivé</span>' ?><br>
          <small class="muted"><?= e(implode(', ', array_map(fn($p) => ADMIN_PERMS[$p][0], admin_perms($a)))) ?></small></td>
        <td><?= $a['last_login'] ? e(date('d/m/Y H:i', strtotime($a['last_login']))) : '<span class="muted">jamais</span>' ?></td>
        <td style="white-space:nowrap">
          <a class="btn light sm" href="utilisateurs.php?id=<?= (int)$a['id'] ?>">Modifier</a>
          <?php if ((int)$a['id'] !== (int)$me['id']): ?>
          <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><?php if (reauth_needed()): ?><input type="password" name="reauth" placeholder="Votre mot de passe" aria-label="Votre mot de passe (confirmation)" style="width:150px;padding:6px 8px;margin-right:4px"><?php endif; ?>
            <?php if ((int)$a['active'] && $a['email'] !== ''): ?><button class="btn light sm" name="do" value="link" title="Envoyer un lien pour choisir un nouveau mot de passe">🔑 Lien</button><?php endif; ?>
            <button class="btn danger sm" name="do" value="delete" onclick="return confirm(<?= e(json_encode('Supprimer définitivement le compte « ' . $a['username'] . ' » ?', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>)">✕</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </table></div>
</div>
<div class="hint">Chaque personne a son propre compte : ne partagez pas d’identifiants. Un compte désactivé ou supprimé est déconnecté immédiatement. Les droits modifiés s’appliquent dès la page suivante.</div>
<?php endif; ?>
<?php admin_footer();
