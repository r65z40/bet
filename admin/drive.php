<?php
/** Retour de Google après « Connecter Google Drive » (Réglages › Sauvegardes). */
require __DIR__ . '/_init.php';
require_perm('reglages');

$state = (string)($_GET['state'] ?? '');
$expected = (string)($_SESSION['drive_state'] ?? '');
$started = (int)($_SESSION['drive_state_at'] ?? 0);
unset($_SESSION['drive_state'], $_SESSION['drive_state_at']);

if ($expected === '' || !hash_equals($expected, $state) || time() - $started > 900) {
    flash('Connexion à Google Drive expirée ou invalide : recommencez depuis cette page.', 'err');
} elseif (isset($_GET['error'])) {
    flash('Connexion à Google Drive annulée' . ($_GET['error'] === 'access_denied' ? '.' : ' (' . (string)$_GET['error'] . ').'), 'err');
} else {
    try {
        $email = drive_connect((string)($_GET['code'] ?? ''));
        admin_log('Google Drive connecté pour les sauvegardes' . ($email !== '' ? ' (' . $email . ')' : ''));
        try {
            $sent = drive_sync(false);
            flash('Google Drive connecté' . ($email !== '' ? ' (' . $email . ')' : '') . '. ' . ($sent ? 'La dernière sauvegarde y a été copiée.' : 'Les prochaines sauvegardes y seront copiées.'));
        } catch (Throwable $e) {
            flash('Google Drive connecté, mais la première copie a échoué : ' . $e->getMessage(), 'err');
        }
    } catch (Throwable $e) {
        flash('Connexion à Google Drive impossible : ' . $e->getMessage(), 'err');
    }
}
redirect('sauvegardes.php');
