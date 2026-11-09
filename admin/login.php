<?php
require __DIR__ . '/_init.php';

if (admin_count() === 0) {
    redirect('setup.php');
}
if (current_admin()) {
    redirect('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    if (login_blocked()) {
        $error = 'Trop de tentatives. Réessayez dans 15 minutes.';
    } else {
        $st = db()->prepare('SELECT * FROM admins WHERE username = ?');
        $st->execute([trim((string)($_POST['username'] ?? ''))]);
        $admin = $st->fetch();
        if ($admin && password_verify((string)($_POST['password'] ?? ''), $admin['password_hash'])) {
            login_admin((int)$admin['id']);
            redirect('index.php');
        }
        login_failed();
        usleep(500000);
        $error = 'Identifiant ou mot de passe incorrect.';
    }
}

admin_header('Connexion');
?>
<div class="card">
  <h1>Administration</h1>
  <p class="intro"><?= e(s('site_name')) ?></p>
  <?php if ($error): ?><div class="flash flash-err"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <?= field('username', 'Identifiant', (string)($_POST['username'] ?? '')) ?>
    <?= field('password', 'Mot de passe', '', 'password') ?>
    <button class="btn gold">Se connecter</button>
  </form>
</div>
<p class="muted" style="text-align:center"><a href="../">← Retour au site</a></p>
<?php admin_footer();
