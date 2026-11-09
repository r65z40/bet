<?php
/** Création du premier compte administrateur (accessible uniquement si aucun compte n'existe). */
require __DIR__ . '/_init.php';

if (admin_count() > 0) {
    redirect('login.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $user = trim((string)($_POST['username'] ?? ''));
    $pass = (string)($_POST['password'] ?? '');
    $pass2 = (string)($_POST['password2'] ?? '');
    if ($user === '' || mb_strlen($user) > 50) {
        $error = 'Choisissez un identifiant.';
    } elseif (mb_strlen($pass) < 10) {
        $error = 'Le mot de passe doit contenir au moins 10 caractères.';
    } elseif ($pass !== $pass2) {
        $error = 'Les deux mots de passe ne correspondent pas.';
    } else {
        db()->prepare('INSERT INTO admins(username, password_hash) VALUES(?, ?)')
            ->execute([$user, password_hash($pass, PASSWORD_DEFAULT)]);
        login_admin((int)db()->lastInsertId());
        flash('Compte créé. Bienvenue dans l’administration !');
        redirect('index.php');
    }
}

admin_header('Installation');
?>
<div class="card">
  <h1>Bienvenue 👋</h1>
  <p class="intro">Créez le compte administrateur du site. Cette page ne sera plus accessible ensuite.</p>
  <?php if ($error): ?><div class="flash flash-err"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <?= field('username', 'Identifiant', (string)($_POST['username'] ?? '')) ?>
    <?= field('password', 'Mot de passe (10 caractères minimum)', '', 'password') ?>
    <?= field('password2', 'Confirmer le mot de passe', '', 'password') ?>
    <button class="btn gold">Créer le compte</button>
  </form>
</div>
<?php admin_footer();
