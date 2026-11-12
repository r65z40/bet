<?php
/** Choix d'un nouveau mot de passe depuis un lien reçu par e-mail (mot de passe oublié ou invitation). */
require __DIR__ . '/_init.php';
header('Referrer-Policy: no-referrer'); // le lien contient un jeton secret

$raw = (string)($_GET['t'] ?? '');
$found = admin_find_token($raw);
$error = '';

if ($found && $_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    [$reset, $admin] = $found;
    $pass = (string)($_POST['password'] ?? '');
    if (mb_strlen($pass) < 10) {
        $error = 'Le mot de passe doit contenir au moins 10 caractères.';
    } elseif ($pass !== ($_POST['password2'] ?? '')) {
        $error = 'Les deux mots de passe ne correspondent pas.';
    } elseif (strcasecmp($pass, $admin['username']) === 0 || strcasecmp($pass, $admin['email']) === 0) {
        $error = 'Le mot de passe ne doit pas être votre identifiant ou votre e-mail.';
    } else {
        // Marque le lien comme utilisé (et tous les autres liens du compte), ferme les sessions ouvertes
        admin_set_password((int)$admin['id'], $pass);
        admin_log_as($admin, $reset['purpose'] === 'invite' ? 'Compte activé (invitation)' : 'Mot de passe réinitialisé par e-mail');
        $_SESSION = [];
        session_regenerate_id(true);
        flash($reset['purpose'] === 'invite' ? 'Votre compte est activé : connectez-vous.' : 'Mot de passe modifié : connectez-vous avec le nouveau.');
        redirect('login.php');
    }
}

$invite = $found && $found[0]['purpose'] === 'invite';
admin_header($invite ? 'Créer mon mot de passe' : 'Nouveau mot de passe');
?>
<div class="card">
<?php if (!$found): ?>
  <h1>Lien invalide</h1>
  <p class="intro">Ce lien a expiré ou a déjà été utilisé.</p>
  <p><a class="btn gold" href="mot-de-passe-oublie.php">Demander un nouveau lien</a></p>
<?php else: ?>
  <h1><?= $invite ? 'Bienvenue !' : 'Nouveau mot de passe' ?></h1>
  <p class="intro">Compte <strong><?= e($found[1]['username']) ?></strong> — choisissez un mot de passe d’au moins 10 caractères.</p>
  <?php if ($error): ?><div class="flash flash-err"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <?= field('password', 'Mot de passe', '', 'password') ?>
    <?= field('password2', 'Confirmer le mot de passe', '', 'password') ?>
    <button class="btn gold"><?= $invite ? 'Activer mon compte' : 'Enregistrer' ?></button>
  </form>
<?php endif; ?>
</div>
<?php admin_footer();
