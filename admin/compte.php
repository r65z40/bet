<?php
/** Changement d'identifiant / mot de passe. */
require __DIR__ . '/_init.php';
$admin = require_admin();

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
<?php admin_footer();
