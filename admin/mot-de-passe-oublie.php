<?php
/**
 * Mot de passe oublié : envoie un lien à usage unique (valable 1 h) à l'adresse du compte.
 * La réponse est toujours la même, que le compte existe ou non (aucune information divulguée).
 */
require __DIR__ . '/_init.php';

if (current_admin()) {
    redirect('index.php');
}

$sent = false;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    if (login_blocked()) {
        $error = 'Trop de tentatives. Réessayez dans 15 minutes.';
    } else {
        login_failed(); // chaque demande compte dans la limite par adresse IP (5 / 15 min)
        $login = trim((string)($_POST['login'] ?? ''));
        $st = db()->prepare("SELECT * FROM admins WHERE active = 1 AND email != '' AND (lower(username) = lower(?) OR lower(email) = lower(?))");
        $st->execute([$login, $login]);
        $admin = $st->fetch();
        if ($admin) {
            // Au plus 3 liens par compte et par heure
            $recent = db()->prepare("SELECT COUNT(*) FROM password_resets WHERE admin_id = ? AND purpose = 'reset' AND expires_at > ?");
            $recent->execute([$admin['id'], time()]);
            if ((int)$recent->fetchColumn() < 3) {
                admin_send_link($admin, 'reset');
                admin_log_as($admin, 'Demande de réinitialisation du mot de passe');
            }
        } else {
            usleep(random_int(200000, 600000)); // temps de réponse comparable
        }
        $sent = true;
    }
}

admin_header('Mot de passe oublié');
?>
<div class="card">
  <h1>Mot de passe oublié</h1>
<?php if ($sent): ?>
  <div class="flash flash-ok">Si un compte correspond, un e-mail contenant un lien vient d’être envoyé. Il est valable <strong>1 heure</strong> et ne sert qu’une fois.</div>
  <p class="muted">Pensez à regarder dans les courriers indésirables. Rien reçu ? Demandez à un autre administrateur de vous envoyer un lien depuis « Équipe ».</p>
<?php else: ?>
  <p class="intro">Indiquez votre identifiant ou votre adresse e-mail : vous recevrez un lien pour choisir un nouveau mot de passe.</p>
  <?php if ($error): ?><div class="flash flash-err"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <?= field('login', 'Identifiant ou e-mail', '') ?>
    <button class="btn gold">Envoyer le lien</button>
  </form>
<?php endif; ?>
</div>
<p class="muted" style="text-align:center"><a href="login.php">← Connexion</a></p>
<?php admin_footer();
