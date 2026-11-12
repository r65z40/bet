<?php
/** Fiche d'une réservation : détails, notes, caution, e-mails, annulation et remboursement. */
require __DIR__ . '/_init.php';
require_perm('reservations');

$b = bk_get((int)($_GET['id'] ?? 0));
if (!$b) {
    flash('Réservation introuvable.', 'err');
    redirect('reservations.php');
}
$id = (int)$b['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $do = (string)($_POST['do'] ?? '');
    if ($do === 'notes') {
        $dep = (string)($_POST['deposit_status'] ?? '');
        db()->prepare('UPDATE bookings SET admin_notes = ?, deposit_status = ? WHERE id = ?')
            ->execute([mb_substr(trim((string)($_POST['admin_notes'] ?? '')), 0, 5000), in_array($dep, ['', 'recue', 'restituee', 'retenue'], true) ? $dep : '', $id]);
        flash('Notes enregistrées.');
    } elseif ($do === 'resend' && $b['status'] === 'confirmed') {
        $ok = bk_mail_confirmation($b);
        flash($ok ? 'E-mail de confirmation renvoyé à ' . $b['email'] . '.' : 'Échec de l’envoi : ' . mail_last_error(), $ok ? 'ok' : 'err');
    } elseif ($do === 'cancel' || $do === 'refund') {
        if (!admin_can('remboursements')) {
            flash('Votre compte n’a pas le droit d’annuler ou de rembourser.', 'err');
            redirect('reservation.php?id=' . $id);
        }
        $amount = round((float)str_replace([',', ' '], ['.', ''], (string)($_POST['amount'] ?? '0')), 2);
        try {
            $done = bk_cancel($b, $amount, !empty($_POST['notify']), !empty($_POST['restore_gift']));
            bk_log($id, 'Action effectuée par ' . current_admin()['username']);
            admin_log(($do === 'cancel' ? 'Annulation' : 'Remboursement') . ' de la réservation ' . $b['ref'] . ($done > 0 ? ' — ' . money2($done) . ' remboursés' : ''));
            flash($done > 0 ? 'Remboursement de ' . money2($done) . ' effectué' . ($do === 'cancel' ? ' et réservation annulée.' : '.') : 'Réservation annulée sans remboursement.');
        } catch (Throwable $e) {
            flash('Opération refusée : ' . $e->getMessage(), 'err');
        }
    } elseif ($do === 'release' && in_array($b['status'], ['pending', 'expired'], true)) {
        db()->prepare("UPDATE bookings SET status = 'cancelled', cancelled_at = ?, hold_until = NULL WHERE id = ?")->execute([bk_now()->format(BK_FMT), $id]);
        bk_log($id, 'Demande non payée annulée par l’administrateur');
        flash('Demande annulée, créneau libéré.');
    }
    redirect('reservation.php?id=' . $id);
}

[$sl, $sc] = BOOKING_STATUSES[$b['status']] ?? ['?', 'gray'];
$paid = !empty($b['paid_at']);
$remaining = round(bk_due($b) - (float)$b['refunded_amount'], 2); // part payée en ligne encore remboursable
$pct = bk_refund_percent($b);
$suggest = round($remaining * $pct / 100, 2);
$log = db()->prepare('SELECT * FROM booking_log WHERE booking_id = ? ORDER BY id DESC');
$log->execute([$id]);
admin_header('Réservation ' . $b['ref'], 'resa');
?>
<p><a href="reservations.php">← Réservations</a></p>
<h1><?= e($b['first_name'] . ' ' . $b['last_name']) ?> <span class="pill" style="vertical-align:middle;<?= $sc === 'green' ? 'background:#e7f6ec;color:#1d7a46' : ($sc === 'red' ? 'background:#fdecea;color:#b42318' : '') ?>"><?= e($sl) ?></span></h1>
<p class="intro">Réf. <?= e($b['ref']) ?> · créée le <?= e(bk_dt($b['created_at'])->format('d/m/Y à H\hi')) ?><?= $paid ? ' · payée le ' . e(bk_dt($b['paid_at'])->format('d/m/Y à H\hi')) . ' (' . e(provider_label($b['provider'])) . ')' : '' ?></p>

<div class="grid g2" style="align-items:start">
  <div>
    <div class="card">
      <h2><?= e(bk_slot_label($b)) ?></h2>
      <p><?= e($b['event_type']) ?> · <strong><?= (int)$b['guests'] ?> personne(s)</strong></p>
      <table>
        <?php foreach (json_decode($b['lines_json'], true) ?: [] as $l): ?>
          <tr><td><?= e($l['label']) ?></td><td class="num"><?= money2((float)$l['total_ht']) ?> HT</td><td class="num"><?= money2(ttc((float)$l['total_ht'])) ?> TTC</td></tr>
        <?php endforeach; ?>
        <?php if ((float)$b['discount_ht'] > 0): ?><tr><td>Remise association<?= $b['asso_name'] ? ' (' . e($b['asso_name']) . ')' : '' ?></td><td class="num">− <?= money2((float)$b['discount_ht']) ?></td><td class="num">− <?= money2(ttc((float)$b['discount_ht'])) ?></td></tr><?php endif; ?>
        <tr><td><strong>Total</strong></td><td class="num"><?= money2((float)$b['total_ht']) ?> HT</td><td class="num"><strong><?= money2((float)$b['total_ttc']) ?> TTC</strong></td></tr>
        <?php if ((float)$b['promo_ht'] > 0): ?><tr><td>Code promo <?= e($b['promo_code']) ?></td><td class="num">− <?= money2((float)$b['promo_ht']) ?></td><td class="num">− <?= money2(ttc((float)$b['promo_ht'])) ?></td></tr><?php endif; ?>
        <?php if ((float)$b['gift_amount'] > 0): ?><tr><td>Réglé par bon cadeau <?= e($b['gift_code']) ?><?= (int)$b['gift_restored'] ? ' <span class="pill">recrédité</span>' : '' ?></td><td></td><td class="num">− <?= money2((float)$b['gift_amount']) ?></td></tr>
          <tr><td><strong>Payé en ligne</strong></td><td></td><td class="num"><strong><?= money2(bk_due($b)) ?></strong></td></tr><?php endif; ?>
        <?php if ((float)$b['refunded_amount'] > 0): ?><tr><td>Remboursé</td><td></td><td class="num">− <?= money2((float)$b['refunded_amount']) ?></td></tr><?php endif; ?>
      </table>
      <p class="actions" style="margin-top:14px">
        <?php if ($b['invoice_number'] !== ''): ?><a class="btn light sm" href="../facture.php?id=<?= $id ?>" target="_blank">🧾 Facture <?= e($b['invoice_number']) ?></a><?php endif; ?>
        <?php if ($b['credit_number'] !== ''): ?><a class="btn light sm" href="../facture.php?id=<?= $id ?>&amp;avoir=1" target="_blank">🧾 Avoir <?= e($b['credit_number']) ?></a><?php endif; ?>
        <a class="btn light sm" href="<?= e(bk_client_url($b)) ?>" target="_blank">Page client ↗</a>
        <?php if (admin_can('emails')): ?><a class="btn light sm" href="emails.php?b=<?= $id ?>">✉ Écrire au client</a><?php endif; ?>
      </p>
    </div>

    <div class="card">
      <h2>Client</h2>
      <p><?= e($b['first_name'] . ' ' . $b['last_name']) ?><?= $b['company'] ? '<br>' . e($b['company']) : '' ?><br>
        <a href="mailto:<?= e($b['email']) ?>"><?= e($b['email']) ?></a> · <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $b['phone'])) ?>"><?= e($b['phone']) ?></a><br>
        <?= e(trim($b['address'] . ' ' . $b['zip'] . ' ' . $b['city'])) ?></p>
      <?php if ($b['message'] !== ''): ?><p style="background:#f4f6f8;padding:10px 12px;border-radius:8px"><?= nl2br(e($b['message'])) ?></p><?php endif; ?>
    </div>

    <form method="post" class="card">
      <?= csrf_field() ?>
      <h2>Suivi</h2>
      <?= field('deposit_status', 'Caution (chèque à l’état des lieux)', $b['deposit_status'], 'select', '', ['' => '— Non reçue', 'recue' => 'Reçue', 'restituee' => 'Restituée', 'retenue' => 'Retenue (en partie ou totalité)']) ?>
      <?= field('admin_notes', 'Notes internes', $b['admin_notes'], 'textarea') ?>
      <button class="btn" name="do" value="notes">Enregistrer</button>
    </form>
  </div>

  <div>
    <?php if (admin_can('remboursements') && ($b['status'] === 'confirmed' || ($b['status'] === 'cancelled' && $paid && $remaining > 0))): ?>
    <form method="post" class="card">
      <?= csrf_field() ?>
      <h2><?= $b['status'] === 'confirmed' ? 'Annuler / rembourser' : 'Rembourser à nouveau' ?></h2>
      <p class="muted" style="margin-top:-6px">Selon vos conditions, à <?= max(0, (int)floor((bk_dt($b['start_at'])->getTimestamp() - time()) / 86400)) ?> jour(s) de l’événement : <strong><?= $pct ?> %</strong> remboursable, soit <?= money2($suggest) ?>.
        Le remboursement est effectué directement sur le moyen de paiement du client (<?= e(provider_label($b['provider'])) ?>).</p>
      <?= field('amount', 'Montant à rembourser TTC (€) — maximum ' . money2($remaining), rating_label((string)$suggest), 'text') ?>
      <?php if ((float)$b['gift_amount'] > 0 && !(int)$b['gift_restored']): ?>
        <label style="display:block;margin-bottom:10px"><input type="checkbox" name="restore_gift" value="1"> Recréditer le bon cadeau <?= e($b['gift_code']) ?> de <?= money2((float)$b['gift_amount']) ?></label>
      <?php endif; ?>
      <label style="display:block;margin-bottom:14px"><input type="checkbox" name="notify" value="1" checked> Prévenir le client par e-mail</label>
      <?php if ($b['status'] === 'confirmed'): ?>
        <button class="btn danger" name="do" value="cancel" onclick="return confirm('Annuler la réservation et rembourser le montant indiqué ? Cette action est définitive.')">Annuler la réservation</button>
      <?php else: ?>
        <button class="btn danger" name="do" value="refund" onclick="return confirm('Rembourser ce montant ?')">Rembourser</button>
      <?php endif; ?>
    </form>
    <?php elseif (in_array($b['status'], ['pending', 'expired'], true)): ?>
    <form method="post" class="card">
      <?= csrf_field() ?>
      <h2>Paiement non finalisé</h2>
      <p class="muted">Le client n’a pas (encore) payé. <?= $b['status'] === 'pending' ? 'Le créneau lui est réservé jusqu’à ' . e(bk_dt($b['hold_until'])->format('H\hi')) . '.' : 'Le créneau est libre.' ?> Il peut reprendre le paiement depuis le lien « Page client ».</p>
      <button class="btn danger sm" name="do" value="release">Annuler cette demande</button>
    </form>
    <?php endif; ?>

    <?php if ($b['status'] === 'confirmed'): ?>
    <form method="post" class="card"><?= csrf_field() ?>
      <h2>E-mails</h2>
      <button class="btn light" name="do" value="resend">✉ Renvoyer la confirmation au client</button>
    </form>
    <?php endif; ?>

    <div class="card">
      <h2>Historique</h2>
      <?php foreach ($log as $l): ?>
        <p style="margin:0 0 8px;font-size:.9rem;<?= preg_match('/ALERTE|ATTENTION|ÉCHEC|Erreur/u', $l['message']) ? 'color:#b42318;font-weight:600' : '' ?>"><span class="muted"><?= e(bk_dt($l['at'])->format('d/m H\hi')) ?></span> — <?= e($l['message']) ?></p>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php admin_footer();
