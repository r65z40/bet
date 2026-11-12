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
        $login = trim((string)($_POST['username'] ?? ''));
        $st = db()->prepare("SELECT * FROM admins WHERE active = 1 AND (lower(username) = lower(?) OR (email != '' AND lower(email) = lower(?)))");
        $st->execute([$login, $login]);
        $admin = $st->fetch();
        // password_verify est toujours exécuté (même identifiant inconnu) : temps de réponse identique
        $hash = $admin['password_hash'] ?? '$2y$10$ZerNfhZOyf7d1fQOxn1ejO5PPK0Ig4chEqEUNkZZHr.tLdfaRzl9e';
        $acctKey = $admin ? 'compte:' . $admin['id'] : '';
        $acctBlocked = $acctKey !== '' && account_blocked($acctKey);
        if (!$acctBlocked && password_verify((string)($_POST['password'] ?? ''), $hash) && $admin) {
            if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
                db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')
                    ->execute([password_hash((string)$_POST['password'], PASSWORD_DEFAULT), $admin['id']]);
            }
            login_admin((int)$admin['id']);
            admin_log_as($admin, 'Connexion');
            redirect('index.php');
        }
        login_failed();
        if ($acctKey !== '') {
            db()->prepare('INSERT INTO login_attempts(ip, at) VALUES(?, ?)')->execute([$acctKey, time()]);
            if ($acctBlocked) {
                admin_log_as($admin, 'Connexion bloquée : trop d’essais sur ce compte');
            }
        }
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
    <?= field('username', 'Identifiant ou e-mail', (string)($_POST['username'] ?? '')) ?>
    <?= field('password', 'Mot de passe', '', 'password') ?>
    <button class="btn gold">Se connecter</button>
  </form>
</div>
<p class="muted" style="text-align:center"><a href="mot-de-passe-oublie.php">Mot de passe oublié ?</a> · <a href="../">← Retour au site</a></p>
<?php admin_footer();
