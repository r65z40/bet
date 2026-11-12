<?php
/**
 * Désinscription des envois groupés (lien en bas de chaque e-mail groupé, et bouton
 * « Se désabonner » des messageries via l'en-tête List-Unsubscribe-Post).
 * Les e-mails liés à une réservation (confirmation, facture, annulation) restent envoyés.
 */
require __DIR__ . '/includes/bootstrap.php';

$email = strtolower(trim((string)($_GET['e'] ?? '')));
$token = (string)($_GET['k'] ?? '');
$valid = filter_var($email, FILTER_VALIDATE_EMAIL) && hash_equals(optout_token($email), $token);
$done = false;

if ($valid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    db()->prepare('INSERT OR IGNORE INTO mail_optout(email, at) VALUES(?, ?)')->execute([$email, bk_now()->format(BK_FMT)]);
    $done = true;
    // Désinscription « en un clic » depuis la messagerie : pas de page à afficher
    if (($_POST['List-Unsubscribe'] ?? '') === 'One-Click') {
        http_response_code(200);
        exit('ok');
    }
} elseif ($valid && is_opted_out($email)) {
    $done = true;
}

if (!$valid) {
    http_response_code(400);
}
public_header('Désinscription | ' . s('site_name'), '', false);
?>
<section class="page-hero"><div class="wrap"><div class="eyebrow"><?= e(s('site_name')) ?></div><h1>Désinscription</h1></div></section>
<section><div class="wrap"><div class="card" style="max-width:640px;margin:0 auto;text-align:center">
<?php if (!$valid): ?>
  <p>Ce lien de désinscription n’est pas valide. Écrivez-nous à <a href="<?= e(mailto()) ?>"><?= e(s('contact_email')) ?></a> et nous vous retirerons de la liste.</p>
<?php elseif ($done): ?>
  <p class="alert alert-ok" style="text-align:left">C’est fait : <strong><?= e($email) ?></strong> ne recevra plus nos envois groupés.</p>
  <p class="muted">Les e-mails liés à une réservation (confirmation, facture) vous seront toujours envoyés.</p>
<?php else: ?>
  <p>Ne plus recevoir les envois groupés de <?= e(s('site_name')) ?> à l’adresse <strong><?= e($email) ?></strong> ?</p>
  <form method="post"><button class="btn btn-gold">Me désinscrire</button></form>
<?php endif; ?>
</div></div></section>
<?php public_footer();
