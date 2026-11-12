<?php
/** Création du premier compte administrateur (accessible uniquement si aucun compte n'existe). */
require __DIR__ . '/_init.php';

if (admin_count() > 0) {
    @unlink(SETUP_CODE_FILE);
    redirect('login.php');
}

// Code d'installation : écrit dans un fichier lisible uniquement par FTP, pour
// qu'un inconnu ne puisse pas créer le compte avant le propriétaire du site.
if (!is_file(SETUP_CODE_FILE)) {
    $code = strtoupper(substr(bin2hex(random_bytes(6)), 0, 10));
    file_put_contents(SETUP_CODE_FILE, "<?php /*\n\nCODE D'INSTALLATION : $code\n\nRecopiez ce code dans la page /admin/setup.php.\nCe fichier est supprimé automatiquement une fois le compte créé.\n\n*/\n");
}
preg_match('/INSTALLATION : ([A-F0-9]+)/', (string)file_get_contents(SETUP_CODE_FILE), $m);
$setupCode = $m[1] ?? '';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    if (login_blocked()) {
        http_response_code(429);
        exit('Trop de tentatives. Réessayez dans 15 minutes.');
    }
    $user = trim((string)($_POST['username'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $pass = (string)($_POST['password'] ?? '');
    $pass2 = (string)($_POST['password2'] ?? '');
    if ($setupCode === '' || !hash_equals($setupCode, strtoupper(trim((string)($_POST['code'] ?? ''))))) {
        login_failed();
        $error = 'Code d’installation incorrect.';
    } elseif ($user === '' || mb_strlen($user) > 50) {
        $error = 'Choisissez un identifiant.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Indiquez une adresse e-mail valide (utile si vous oubliez votre mot de passe).';
    } elseif (mb_strlen($pass) < 10) {
        $error = 'Le mot de passe doit contenir au moins 10 caractères.';
    } elseif ($pass !== $pass2) {
        $error = 'Les deux mots de passe ne correspondent pas.';
    } else {
        db()->prepare('INSERT INTO admins(username, email, perms, active, password_hash) VALUES(?, ?, ?, 1, ?)')
            ->execute([$user, $email, implode(',', array_keys(ADMIN_PERMS)), password_hash($pass, PASSWORD_DEFAULT)]);
        @unlink(SETUP_CODE_FILE);
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
  <div class="hint">🔐 Par sécurité, ouvrez par <strong>FTP</strong> le fichier <code>data/setup-code.php</code> (avec le Bloc-notes) et recopiez le <strong>code d’installation</strong> qu’il contient.</div>
  <?php if ($error): ?><div class="flash flash-err"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <?= field('code', 'Code d’installation (dans data/setup-code.php)', '') ?>
    <?= field('username', 'Identifiant', (string)($_POST['username'] ?? '')) ?>
    <?= field('email', 'E-mail', (string)($_POST['email'] ?? s('contact_email')), 'email') ?>
    <?= field('password', 'Mot de passe (10 caractères minimum)', '', 'password') ?>
    <?= field('password2', 'Confirmer le mot de passe', '', 'password') ?>
    <button class="btn gold">Créer le compte</button>
  </form>
</div>
<?php admin_footer();
