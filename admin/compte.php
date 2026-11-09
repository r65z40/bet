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
    $new = (string)($_POST['new'] ?? '');

    if (!password_verify((string)($_POST['current'] ?? ''), $hash)) {
        flash('Mot de passe actuel incorrect.', 'err');
    } elseif ($user === '' || mb_strlen($user) > 50) {
        flash('Identifiant invalide.', 'err');
    } elseif ($new !== '' && mb_strlen($new) < 10) {
        flash('Le nouveau mot de passe doit contenir au moins 10 caractères.', 'err');
    } elseif ($new !== ($_POST['new2'] ?? '')) {
        flash('Les deux nouveaux mots de passe ne correspondent pas.', 'err');
    } else {
        try {
            db()->prepare('UPDATE admins SET username = ?, password_hash = ? WHERE id = ?')
                ->execute([$user, $new !== '' ? password_hash($new, PASSWORD_DEFAULT) : $hash, $admin['id']]);
            session_regenerate_id(true);
            flash('Compte mis à jour.');
        } catch (PDOException) {
            flash('Cet identifiant est déjà utilisé.', 'err');
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
  <?= field('new', 'Nouveau mot de passe (laisser vide pour ne pas changer)', '', 'password') ?>
  <?= field('new2', 'Confirmer le nouveau mot de passe', '', 'password') ?>
  <hr style="border:0;border-top:1px solid var(--line);margin:8px 0 16px">
  <?= field('current', 'Mot de passe actuel (obligatoire)', '', 'password') ?>
  <button class="btn gold">Enregistrer</button>
</form>
<?php admin_footer();
