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
        $dep = (string)($_POST['deposit_status'] ?? $b['deposit_status']);
        if ($b['deposit_pi'] !== '' || !in_array($dep, ['', 'recue', 'restituee', 'retenue'], true)) {
            $dep = $b['deposit_status']; // caution par empreinte : statut géré par Stripe, jamais à la main
        }
        $noReview = !empty($_POST['no_review']) ? 1 : 0;
        db()->prepare('UPDATE bookings SET admin_notes = ?, deposit_status = ?, no_review = ? WHERE id = ?')
            ->execute([mb_substr(trim((string)($_POST['admin_notes'] ?? '')), 0, 5000), $dep, $noReview, $id]);
        if ($noReview !== (int)$b['no_review']) {
            bk_log($id, ($noReview ? 'Litige : pas de demande d’avis' : 'Demande d’avis réactivée') . ' (' . current_admin()['username'] . ')');
        }
        flash('Notes enregistrées.');
    } elseif ($do === 'deposit_amount' && in_array($b['deposit_status'], ['', 'expiree'], true)) {
        $v = round((float)str_replace([',', ' '], ['.', ''], (string)($_POST['deposit_amount'] ?? '')), 2);
        db()->prepare('UPDATE bookings SET deposit_amount = ? WHERE id = ?')->execute([$v > 0 ? min($v, 50000) : null, $id]);
        bk_log($id, 'Montant de la caution ' . ($v > 0 ? 'fixé à ' . money2($v) : 'remis au calcul automatique') . ' par ' . current_admin()['username']);
        flash('Montant de la caution enregistré.');
    } elseif ($do === 'deposit_send' && $b['status'] === 'confirmed') {
        $ok = deposit_mail($b, 'caution_demande');
        flash($ok ? 'Demande de caution envoyée à ' . $b['email'] . '.' : 'Échec de l’envoi : ' . mail_last_error(), $ok ? 'ok' : 'err');
    } elseif ($do === 'deposit_release' || $do === 'deposit_capture') {
        if (!admin_can('remboursements')) {
            flash('Votre compte n’a pas le droit de gérer les cautions.', 'err');
            redirect('reservation.php?id=' . $id);
        }
        try {
            if ($do === 'deposit_release') {
                deposit_release($b, !empty($_POST['notify']), current_admin()['username']);
                admin_log('Caution libérée — réservation ' . $b['ref']);
                flash('Empreinte libérée : rien n’est débité au client.');
            } else {
                $got = deposit_capture($b, (float)str_replace([',', ' '], ['.', ''], (string)($_POST['capture_amount'] ?? '0')), (string)($_POST['capture_reason'] ?? ''), !empty($_POST['notify']));
                bk_log($id, 'Action effectuée par ' . current_admin()['username']);
                admin_log('Retenue sur caution de ' . money2($got) . ' — réservation ' . $b['ref']);
                flash(money2($got) . ' encaissés sur la caution ; le reste de l’empreinte est libéré.');
            }
        } catch (Throwable $e) {
            flash('Opération refusée : ' . $e->getMessage(), 'err');
        }
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
            flash(($done > 0 ? 'Remboursement de ' . money2($done) . ' effectué' . ($do === 'cancel' ? ' et réservation annulée.' : '.') : 'Réservation annulée sans remboursement.')
                . ($do === 'cancel' && $b['deposit_status'] === 'recue' ? ' Pensez à rendre (ou détruire) le chèque de caution, puis passez-le en « Restituée ».' : ''));
        } catch (Throwable $e) {
            flash('Opération refusée : ' . $e->getMessage(), 'err');
        }
    } elseif ($do === 'move' && $b['status'] === 'confirmed') {
        $date = (string)($_POST['new_date'] ?? '');
        $time = $b['mode'] === 'hour' ? (string)($_POST['new_time'] ?? '') : bk_dt($b['start_at'])->format('H:i');
        $ns = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && preg_match('/^\d{2}:\d{2}$/', $time)
            ? DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $time, bk_now()->getTimezone()) : false;
        if (!$ns) {
            flash('Date ou heure non valide.', 'err');
            redirect('reservation.php?id=' . $id);
        }
        try {
            $oldLabel = bk_slot_label($b);
            $nb = bk_move($b, $ns);
            bk_log($id, 'Action effectuée par ' . current_admin()['username']);
            admin_log('Déplacement de la réservation ' . $b['ref'] . ' : ' . $oldLabel . ' → ' . bk_slot_label($nb));
            admin_notify('moved', 'Réservation déplacée : ' . $b['first_name'] . ' ' . $b['last_name'], [$oldLabel . ' → ' . bk_slot_label($nb), 'Par ' . current_admin()['username']], 'reservation.php?id=' . $id);
            $msg = 'Réservation déplacée : ' . bk_slot_label($nb) . '.';
            if (!empty($_POST['notify'])) {
                after_response(fn() => bk_mail_moved($nb, $oldLabel), 'e-mail déplacement');
                $msg .= ' Le client est prévenu par e-mail (envoi en cours : le résultat s’affiche dans l’historique).';
            }
            // Tarif différent à la nouvelle date (week-end, date spéciale…) : information seulement
            $lines = json_decode($b['lines_json'], true) ?: [];
            $paidRental = (float)($lines[0]['total_ht'] ?? 0);
            $newRental = bk_rental_price($b['mode'], $ns, (float)$b['hours'], max(1, (int)round((float)$b['hours'] / 24)));
            if (abs($newRental - $paidRental) >= 0.01) {
                $msg .= ' Info : à cette date, la location coûterait ' . money2(ttc($newRental)) . ' TTC (payé : ' . money2(ttc($paidRental)) . ' TTC). Le prix payé n’est pas modifié.';
            }
            flash($msg);
        } catch (DomainException $e) {
            flash('Déplacement impossible : ' . $e->getMessage(), 'err');
        }
    } elseif ($do === 'purge') {
        if (!admin_can('reglages')) {
            flash('Seul un administrateur peut supprimer définitivement une réservation.', 'err');
            redirect('reservation.php?id=' . $id);
        }
        require_reauth('reservation.php?id=' . $id);
        try {
            bk_purge($b);
            admin_log('Réservation supprimée définitivement : ' . $b['ref'] . ' (' . $b['first_name'] . ' ' . $b['last_name'] . ($b['invoice_number'] ? ', facture ' . $b['invoice_number'] : '') . ')');
            flash('Réservation ' . $b['ref'] . ' supprimée définitivement.');
            redirect('reservations.php');
        } catch (Throwable $e) {
            flash('Suppression impossible : ' . $e->getMessage(), 'err');
        }
    } elseif ($do === 'balance_send' && $b['status'] === 'confirmed' && bk_balance($b) > 0) {
        $ok = bk_mail_balance($b, empty($b['balance_requested_at']) ? 'solde_demande' : 'solde_relance');
        flash($ok ? 'Lien de paiement du solde envoyé à ' . $b['email'] . '.' : 'Échec de l’envoi : ' . mail_last_error(), $ok ? 'ok' : 'err');
    } elseif ($do === 'balance_offline' && $b['status'] === 'confirmed' && bk_balance($b) > 0) {
        if (!admin_can('remboursements')) {
            flash('Votre compte n’a pas le droit d’enregistrer un paiement.', 'err');
            redirect('reservation.php?id=' . $id);
        }
        $m = (string)($_POST['method'] ?? '');
        if (!isset(BALANCE_OFFLINE[$m])) {
            flash('Choisissez le moyen de paiement.', 'err');
            redirect('reservation.php?id=' . $id . '#solde');
        }
        bk_balance_paid($id, $m, mb_substr(trim((string)($_POST['note'] ?? '')), 0, 120), (int)round(bk_balance($b) * 100));
        bk_log($id, 'Action effectuée par ' . current_admin()['username']);
        admin_log('Solde reçu (' . BALANCE_OFFLINE[$m] . ') — réservation ' . $b['ref']);
        flash('Solde enregistré comme reçu (' . BALANCE_OFFLINE[$m] . ').');
    } elseif ($do === 'ins_paper') {
        $on = !empty($_POST['on']) ? 1 : 0;
        db()->prepare('UPDATE bookings SET insurance_paper = ? WHERE id = ?')->execute([$on, $id]);
        bk_log($id, ($on ? 'Attestation d’assurance notée reçue (papier)' : 'Attestation d’assurance : mention « reçue » retirée') . ' — ' . current_admin()['username']);
        flash($on ? 'Attestation notée comme reçue.' : 'Mention retirée.');
    } elseif ($do === 'ins_delete' && $b['insurance_file'] !== '') {
        if ($f = insurance_path($b)) {
            @unlink($f);
        }
        db()->prepare("UPDATE bookings SET insurance_file = '', insurance_at = NULL WHERE id = ?")->execute([$id]);
        bk_log($id, 'Attestation d’assurance supprimée (illisible ou non conforme) — ' . current_admin()['username']);
        flash('Attestation supprimée : le client peut en déposer une nouvelle.');
    } elseif ($do === 'edl_delete' && isset(EDL_KINDS[$_POST['k'] ?? ''])) {
        require_reauth('reservation.php?id=' . $id . '#edl');
        $k = (string)$_POST['k'];
        foreach (glob(edl_dir($id) . '/' . $k . '-*.jpg') ?: [] as $f) {
            @unlink($f);
        }
        db()->prepare('DELETE FROM inspections WHERE booking_id = ? AND kind = ?')->execute([$id, $k]);
        bk_log($id, 'État des lieux ' . EDL_KINDS[$k] . ' supprimé — ' . current_admin()['username']);
        admin_log('État des lieux ' . EDL_KINDS[$k] . ' supprimé — réservation ' . $b['ref']);
        flash('État des lieux supprimé : vous pouvez le refaire.');
    } elseif ($do === 'release' && in_array($b['status'], ['pending', 'expired'], true)) {
        db()->prepare("UPDATE bookings SET status = 'cancelled', cancelled_at = ?, hold_until = NULL WHERE id = ?")->execute([bk_now()->format(BK_FMT), $id]);
        bk_log($id, 'Demande non payée annulée par l’administrateur');
        flash('Demande annulée, créneau libéré.');
    }
    redirect('reservation.php?id=' . $id);
}

[$sl, $sc] = BOOKING_STATUSES[$b['status']] ?? ['?', 'gray'];
$paid = !empty($b['paid_at']);
$remaining = round(bk_paid_online($b) - (float)$b['refunded_amount'], 2); // part payée en ligne encore remboursable
$pct = bk_refund_percent($b);
// Part conservée = (100 − %) du prix ; le client récupère ce qu'il a versé au-delà (ex. 30 % conservés = l'acompte)
$keptPart = round(bk_due($b) * (100 - $pct) / 100, 2);
$suggest = round(max(0, min($remaining, bk_paid_total($b) - $keptPart - (float)$b['refunded_amount'])), 2);
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
      <?php if (!empty($b['moved_at'])): ?><p class="muted" style="margin-top:-6px">Date modifiée le <?= e(bk_dt($b['moved_at'])->format('d/m/Y')) ?> — date initiale : <?= e($b['moved_from']) ?></p><?php endif; ?>
      <p><?= e($b['event_type']) ?> · <strong><?= (int)$b['guests'] ?> personne(s)</strong></p>
      <table>
        <?php foreach (json_decode($b['lines_json'], true) ?: [] as $l): ?>
          <tr><td><?= e($l['label']) ?></td><td class="num"><?= money2((float)$l['total_ht']) ?> HT</td><td class="num"><?= money2(ttc((float)$l['total_ht'])) ?> TTC</td></tr>
        <?php endforeach; ?>
        <?php if ((float)$b['discount_ht'] > 0): ?><tr><td>Remise association<?= $b['asso_name'] ? ' (' . e($b['asso_name']) . ')' : '' ?></td><td class="num">− <?= money2((float)$b['discount_ht']) ?></td><td class="num">− <?= money2(ttc((float)$b['discount_ht'])) ?></td></tr><?php endif; ?>
        <tr><td><strong>Total</strong></td><td class="num"><?= money2((float)$b['total_ht']) ?> HT</td><td class="num"><strong><?= money2((float)$b['total_ttc']) ?> TTC</strong></td></tr>
        <?php if ((float)$b['promo_ht'] > 0): ?><tr><td>Code promo <?= e($b['promo_code']) ?></td><td class="num">− <?= money2((float)$b['promo_ht']) ?></td><td class="num">− <?= money2(ttc((float)$b['promo_ht'])) ?></td></tr><?php endif; ?>
        <?php if ((float)$b['gift_amount'] > 0): ?><tr><td>Réglé par bon cadeau <?= e($b['gift_code']) ?><?= (int)$b['gift_restored'] ? ' <span class="pill">recrédité</span>' : '' ?></td><td></td><td class="num">− <?= money2((float)$b['gift_amount']) ?></td></tr>
          <tr><td><strong><?= bk_is_acompte($b) ? 'Reste à payer après le bon' : 'Payé en ligne' ?></strong></td><td></td><td class="num"><strong><?= money2(bk_due($b)) ?></strong></td></tr><?php endif; ?>
        <?php if (bk_is_acompte($b)): ?>
          <tr><td>Acompte<?= $paid ? ' — payé le ' . e(bk_dt($b['paid_at'])->format('d/m/Y')) . ' (' . e(provider_label($b['provider'])) . ')' : '' ?></td><td></td><td class="num"><?= money2((float)$b['acompte_amount']) ?></td></tr>
          <tr><td><strong>Solde</strong> — <?= !empty($b['balance_paid_at']) ? 'payé le ' . e(bk_dt($b['balance_paid_at'])->format('d/m/Y')) . ' (' . e(balance_method_label($b['balance_method'])) . ')' : '<span style="color:#b42318">à régler avant le ' . e(bk_dt($b['balance_due_at'])->format('d/m/Y')) . '</span>' . ($b['status'] === 'confirmed' && ($dl = bk_balance_deadline($b)) ? '<br><span class="muted" style="font-size:.85rem">Annulation automatique (' . (acompte_kept_on_unpaid() ? 'acompte conservé' : 'acompte remboursé') . ') le ' . e($dl->modify('+1 day')->format('d/m/Y')) . ' si non réglé</span>' : '') ?></td><td></td><td class="num"><strong><?= money2(bk_balance_amount($b)) ?></strong></td></tr>
        <?php endif; ?>
        <?php if ((float)$b['refunded_amount'] > 0): ?><tr><td>Remboursé</td><td></td><td class="num">− <?= money2((float)$b['refunded_amount']) ?></td></tr><?php endif; ?>
      </table>
      <?php if ($b['status'] === 'confirmed' && bk_balance($b) > 0): ?>
      <div id="solde" style="background:#fff8e8;border:1px solid #f0dca8;border-radius:10px;padding:12px 14px;margin-top:12px">
        <strong>Solde de <?= money2(bk_balance($b)) ?> à encaisser</strong><br>
        <span class="muted" style="font-size:.9rem"><?= $b['balance_requested_at'] ? 'Lien envoyé le ' . e(bk_dt($b['balance_requested_at'])->format('d/m/Y')) . ($b['balance_reminded_at'] ? ', relancé le ' . e(bk_dt($b['balance_reminded_at'])->format('d/m/Y')) : '') . '.' : 'Le lien de paiement partira automatiquement le ' . e(bk_dt($b['balance_due_at'])->modify('-7 days')->format('d/m/Y')) . '.' ?></span>
        <form method="post" style="margin:10px 0 0;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
          <?= csrf_field() ?>
          <button class="btn light sm" name="do" value="balance_send">✉ Envoyer le lien de paiement maintenant</button>
        </form>
        <?php if (admin_can('remboursements')): ?>
        <form method="post" style="margin:8px 0 0;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
          <?= csrf_field() ?>
          <select name="method" aria-label="Moyen de paiement" style="width:auto"><option value="">Reçu par…</option><?php foreach (BALANCE_OFFLINE as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select>
          <input type="text" name="note" placeholder="Référence (facultatif)" maxlength="120" style="width:180px">
          <button class="btn sm" name="do" value="balance_offline" onclick="return confirm('Noter le solde comme reçu ?')">✓ Solde reçu hors ligne</button>
        </form>
        <?php endif; ?>
      </div>
      <?php endif; ?>
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

    <?php if ($b['status'] === 'confirmed' || $b['insurance_file'] !== '' || edl_get($id, 'entree')): ?>
    <div class="card" id="assurance">
      <h2>Assurance et état des lieux</h2>
      <p style="margin:0 0 10px"><strong>Attestation d’assurance :</strong>
        <?php if ($b['insurance_file'] !== ''): ?><span style="color:#1d7a46">✓ déposée le <?= e(bk_dt($b['insurance_at'])->format('d/m/Y')) ?></span> — <a href="attestation.php?id=<?= $id ?>" target="_blank">ouvrir</a>
        <?php elseif ((int)$b['insurance_paper']): ?><span style="color:#1d7a46">✓ reçue sur papier</span>
        <?php else: ?><span style="color:#b42318">non reçue</span><?= $b['insurance_reminded_at'] ? ' <span class="muted">(rappel envoyé le ' . e(bk_dt($b['insurance_reminded_at'])->format('d/m/Y')) . ')</span>' : '' ?><?php endif; ?></p>
      <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;margin:0 0 4px"><?= csrf_field() ?>
        <?php if (!(int)$b['insurance_paper'] && $b['insurance_file'] === ''): ?><input type="hidden" name="on" value="1"><button class="btn light sm" name="do" value="ins_paper">Noter reçue sur papier</button>
        <?php elseif ((int)$b['insurance_paper']): ?><button class="btn light sm" name="do" value="ins_paper">Retirer « reçue »</button><?php endif; ?>
        <?php if ($b['insurance_file'] !== ''): ?><button class="btn light sm" name="do" value="ins_delete" onclick="return confirm('Supprimer cette attestation (illisible ou non conforme) ? Le client pourra en déposer une autre.')">Supprimer le fichier</button><?php endif; ?>
      </form>
      <?php if (edl_enabled()): ?>
      <div id="edl" style="border-top:1px solid var(--line);margin-top:14px;padding-top:12px">
        <?php foreach (EDL_KINDS as $k => $kl): $ed = edl_get($id, $k); ?>
        <p style="margin:0 0 8px;display:flex;gap:8px;align-items:center;flex-wrap:wrap"><strong>État des lieux <?= e($kl) ?> :</strong>
          <?php if ($ed): ?><span style="color:#1d7a46">✓ signé le <?= e(bk_dt($ed['created_at'])->format('d/m/Y à H\hi')) ?></span>
            <a class="btn light sm" href="../etat-des-lieux.php?id=<?= $id ?>&amp;k=<?= $k ?>" target="_blank">Voir</a>
            <form method="post" style="margin:0;display:inline-flex;gap:6px"><?= csrf_field() ?><input type="hidden" name="k" value="<?= $k ?>"><?= reauth_needed() ? '<input type="password" name="reauth" placeholder="Mot de passe" style="width:120px">' : '' ?><button class="btn light sm" name="do" value="edl_delete" onclick="return confirm('Supprimer cet état des lieux signé (photos comprises) pour le refaire ?')">Supprimer</button></form>
          <?php elseif ($b['status'] === 'confirmed' && ($k === 'entree' || edl_get($id, 'entree') || bk_dt($b['start_at']) < bk_now())): ?>
            <a class="btn gold sm" href="etat-des-lieux.php?id=<?= $id ?>&amp;k=<?= $k ?>">📋 Faire l’état des lieux <?= e($kl) ?></a>
          <?php else: ?><span class="muted">à faire au départ</span><?php endif; ?>
        </p>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <form method="post" class="card">
      <?= csrf_field() ?>
      <h2>Suivi</h2>
      <?php if (deposit_cheque_allowed() && $b['deposit_pi'] === ''): ?>
      <?= field('deposit_status', 'Caution (chèque à l’état des lieux)', $b['deposit_status'], 'select', '', ['' => '— Non reçue', 'recue' => 'Reçue', 'restituee' => 'Restituée', 'retenue' => 'Retenue (en partie ou totalité)']) ?>
      <?php endif; ?>
      <?= field('admin_notes', 'Notes internes', $b['admin_notes'], 'textarea') ?>
      <?php if (empty($b['review_sent_at'])): ?>
      <label style="display:block;margin:0 0 12px"><input type="checkbox" name="no_review" value="1"<?= (int)$b['no_review'] ? ' checked' : '' ?>> Litige : ne pas demander d’avis à ce client</label>
      <?php endif; ?>
      <button class="btn" name="do" value="notes">Enregistrer</button>
    </form>
  </div>

  <div>
    <?php if (admin_can('remboursements') && ($b['status'] === 'confirmed' || ($b['status'] === 'cancelled' && $paid && $remaining > 0))): ?>
    <form method="post" class="card">
      <?= csrf_field() ?>
      <h2><?= $b['status'] === 'confirmed' ? 'Annuler / rembourser' : 'Rembourser à nouveau' ?></h2>
      <p class="muted" style="margin-top:-6px">Selon vos conditions, à <?= max(0, (int)floor((bk_dt($b['start_at'])->getTimestamp() - time()) / 86400)) ?> jour(s) de l’événement<?= bk_in_grace($b) ? ' (délai de réflexion : réservée il y a moins de ' . (int)bk_num('bk_cancel_grace_days', 7) . ' jours)' : '' ?> : <?= $keptPart > 0 ? 'vous conservez <strong>' . money2(min($keptPart, bk_paid_total($b))) . '</strong> (' . (100 - $pct) . ' % du prix), ' : '' ?>remboursement proposé <strong><?= money2($suggest) ?></strong>.
        Le remboursement est effectué directement sur le moyen de paiement du client (<?= e(provider_label($b['provider'])) ?>).<?= bk_is_acompte($b) ? ' Calcul sur le prix total ; seuls les montants payés en ligne sont remboursables ici' . (!empty($b['balance_paid_at']) && isset(BALANCE_OFFLINE[$b['balance_method']]) ? ' (le solde reçu par ' . e(strtolower(BALANCE_OFFLINE[$b['balance_method']])) . ' se rembourse à la main)' : '') . '.' : '' ?></p>
      <?= field('amount', 'Montant à rembourser TTC (€) — maximum ' . money2($remaining), rating_label((string)$suggest), 'text') ?>
      <?php if ((float)$b['gift_amount'] > 0 && !(int)$b['gift_restored']): ?>
        <label style="display:block;margin-bottom:10px"><input type="checkbox" name="restore_gift" value="1"> Recréditer le bon cadeau <?= e($b['gift_code']) ?> de <?= money2((float)$b['gift_amount']) ?></label>
      <?php endif; ?>
      <label style="display:block;margin-bottom:14px"><input type="checkbox" name="notify" value="1"<?= notif_on('cancelled', 'client') ? ' checked' : '' ?>> Prévenir le client par e-mail</label>
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

    <?php if (($b['deposit_pi'] !== '' || (deposit_by_card() && in_array($b['deposit_status'], ['', 'expiree'], true))) && ($b['status'] === 'confirmed' || $b['deposit_status'] === 'empreinte')):
        [$dl, $dc] = DEPOSIT_STATUSES[$b['deposit_status']] ?? ['?', 'gray']; $exp = deposit_expires_at($b); ?>
    <div class="card" id="caution">
      <h2>Caution <span class="pill" style="vertical-align:middle;<?= $dc === 'green' ? 'background:#e7f6ec;color:#1d7a46' : ($dc === 'red' ? 'background:#fdecea;color:#b42318' : '') ?>"><?= e($dl) ?></span></h2>
      <?php if ($b['status'] === 'confirmed' && in_array($b['deposit_status'], ['', 'expiree'], true) && bk_deposit_amount($b) > 0
          && bk_dt($b['start_at']) <= bk_now()->modify('+1 day') && bk_dt($b['end_at']) > bk_now()): ?>
        <p style="background:#fdecea;color:#8a1c12;border-radius:8px;padding:10px 12px;font-weight:600">⚠ Caution non déposée : ne remettez pas les clés sans <?= deposit_cheque_allowed() ? 'empreinte bancaire ou chèque' : 'empreinte bancaire' ?>.</p>
      <?php endif; ?>
      <?php if ($b['deposit_status'] === 'empreinte'): ?>
        <p><strong><?= money2((float)$b['deposit_held']) ?></strong> bloqués sur la carte du client depuis le <?= e(bk_dt($b['deposit_at'])->format('d/m/Y à H\hi')) ?>.
          Valable jusqu’au <strong><?= e($exp->format('d/m/Y à H\hi')) ?></strong><?php if ((int)bk_num('bk_deposit_release_days', 2) > 0): ?> ; libération automatique le <?= e(bk_dt($b['end_at'])->modify('+' . (int)bk_num('bk_deposit_release_days', 2) . ' days')->format('d/m/Y')) ?> si vous ne faites rien<?php endif; ?>.</p>
        <?php if (admin_can('remboursements')): ?>
        <form method="post"><?= csrf_field() ?>
          <div style="display:flex;gap:10px;flex-wrap:wrap">
            <label class="field" style="flex:1 1 130px"><span>Montant à retenir (€)</span><input type="text" name="capture_amount" inputmode="decimal" placeholder="max. <?= e(rating_label((string)$b['deposit_held'])) ?>"></label>
            <label class="field" style="flex:3 1 240px"><span>Motif (envoyé au client)</span><input type="text" name="capture_reason" maxlength="500" placeholder="Ex. : chaise cassée, ménage non fait…"></label>
          </div>
          <label style="display:block;margin:4px 0 14px"><input type="checkbox" name="notify" value="1"<?= notif_on('deposit_released', 'client') || notif_on('deposit_retained', 'client') ? ' checked' : '' ?>> Prévenir le client par e-mail</label>
          <div style="display:flex;gap:10px;flex-wrap:wrap">
            <button class="btn" name="do" value="deposit_release" onclick="return confirm('Libérer l’empreinte ? Rien ne sera débité au client.')">✓ Tout s’est bien passé : libérer</button>
            <button class="btn danger" name="do" value="deposit_capture" onclick="return confirm('Encaisser ce montant sur la carte du client ? Le reste sera libéré. Action définitive.')">Retenir ce montant</button>
          </div>
        </form>
        <?php endif; ?>
      <?php elseif ($b['deposit_status'] === 'retenue'): ?>
        <p><strong><?= money2((float)$b['deposit_captured']) ?></strong> retenus sur <?= money2((float)$b['deposit_held']) ?> — motif : <?= e($b['deposit_reason']) ?></p>
      <?php elseif ($b['deposit_status'] === 'restituee'): ?>
        <p>Empreinte de <?= money2((float)$b['deposit_held']) ?> libérée, rien n’a été débité.</p>
      <?php else: ?>
        <p class="muted" style="margin-top:-6px">Montant demandé : <strong><?= money2(bk_deposit_amount($b)) ?></strong><?= ($b['deposit_amount'] ?? null) === null ? ' (calcul automatique)' : '' ?>.
          Le client peut déposer l’empreinte à partir du <strong><?= e(deposit_opens_at($b)->format('d/m/Y')) ?></strong> depuis sa page « Ma réservation »<?= $b['deposit_requested_at'] ? ' — demande envoyée le ' . e(bk_dt($b['deposit_requested_at'])->format('d/m/Y')) : ' — e-mail automatique ce jour-là' ?>.
          À l’état des lieux, il peut aussi le faire sur son téléphone (bouton « Page client » ci-contre).<?= deposit_cheque_allowed() ? ' Il peut aussi donner un <strong>chèque</strong> à l’état des lieux : indiquez-le dans « Suivi » (caution reçue).' : '' ?></p>
        <form method="post" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap"><?= csrf_field() ?>
          <label class="field" style="flex:1 1 150px;margin:0"><span>Montant propre à cette réservation (€)</span><input type="text" name="deposit_amount" inputmode="decimal" value="<?= ($b['deposit_amount'] ?? null) !== null ? e(rating_label((string)$b['deposit_amount'])) : '' ?>" placeholder="auto : <?= e(rating_label((string)bk_deposit_amount($b))) ?>"></label>
          <button class="btn light" name="do" value="deposit_amount">Enregistrer</button>
          <?php if (deposit_can_hold($b)): ?><button class="btn light" name="do" value="deposit_send">✉ Envoyer la demande au client</button><?php endif; ?>
        </form>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($b['status'] === 'confirmed'): ?>
    <form method="post" class="card" id="deplacer"><?= csrf_field() ?>
      <h2>Déplacer à une autre date</h2>
      <?php if (($rd = (int)bk_num('bk_report_days', 60)) > 0): $daysLeft = (int)floor((bk_dt($b['start_at'])->getTimestamp() - time()) / 86400); ?>
      <p style="margin:-4px 0 10px;font-size:.9rem;padding:8px 12px;border-radius:8px;background:<?= !empty($b['moved_at']) || $daysLeft < $rd ? '#fff3dc' : '#e7f6ec' ?>">
        Report gratuit (conditions) : une fois, demandé au moins <?= $rd ?> jours avant.
        <?= !empty($b['moved_at']) ? '<strong>Déjà reporté le ' . e(bk_dt($b['moved_at'])->format('d/m/Y')) . '.</strong>' : ($daysLeft < $rd ? '<strong>Délai dépassé</strong> (à ' . $daysLeft . ' jours) : report à votre appréciation.' : '<strong>Le client y a droit.</strong>') ?></p>
      <?php endif; ?>
      <p class="muted" style="margin-top:-6px">Même formule et même durée (<?= $b['mode'] === 'day' ? (int)round((float)$b['hours']) . ' h, arrivée à ' . e(bk_dt($b['start_at'])->format('H\hi')) : e(fr_hours((float)$b['hours'])) ?>). Le prix payé et la facture ne changent pas. Le site vérifie que la nouvelle date est libre (avec le temps mort) ; le délai minimum de réservation ne s’applique pas.</p>
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <label class="field" style="flex:1 1 160px"><span>Nouvelle date</span><input type="date" name="new_date" required min="<?= e(bk_now()->format('Y-m-d')) ?>" value="<?= e(bk_dt($b['start_at'])->format('Y-m-d')) ?>"></label>
        <?php if ($b['mode'] === 'hour'): ?>
          <label class="field" style="flex:1 1 120px"><span>Heure d’arrivée</span><input type="time" name="new_time" required step="<?= max(15, (int)s('bk_hour_step') ?: 60) * 60 ?>" value="<?= e(bk_dt($b['start_at'])->format('H:i')) ?>"></label>
        <?php endif; ?>
      </div>
      <label style="display:block;margin:10px 0 14px"><input type="checkbox" name="notify" value="1"<?= notif_on('moved', 'client') ? ' checked' : '' ?>> Prévenir le client par e-mail (ancienne et nouvelle date, boutons pour mettre à jour son agenda)</label>
      <button class="btn" name="do" value="move" onclick="return confirm('Déplacer la réservation à cette date ?')">Déplacer la réservation</button>
    </form>

    <form method="post" class="card"><?= csrf_field() ?>
      <h2>E-mails</h2>
      <button class="btn light" name="do" value="resend">✉ Renvoyer la confirmation au client</button>
    </form>
    <?php endif; ?>

    <?php if (admin_can('reglages') && $b['status'] !== 'confirmed'): ?>
    <form method="post" class="card" style="border-color:#f3c2bd">
      <?= csrf_field() ?>
      <h2>Supprimer définitivement</h2>
      <p class="muted" style="margin-top:-6px">Pour les <strong>réservations de test</strong> : elle disparaîtra de la liste, du planning, des statistiques et de la comptabilité<?= $b['invoice_number'] ? ', avec sa facture ' . e($b['invoice_number']) . ($b['credit_number'] ? ' et son avoir ' . e($b['credit_number']) : '') : '' ?>.
        Ne supprimez pas une vraie réservation payée : ses factures doivent être conservées 10 ans.</p>
      <?= reauth_field() ?>
      <button class="btn danger" name="do" value="purge" onclick="return confirm('Supprimer définitivement la réservation <?= e($b['ref']) ?> ? Cette action est irréversible.')">🗑 Supprimer définitivement</button>
    </form>
    <?php endif; ?>

    <?php $ml = db()->prepare('SELECT id, at, subject, ok, body IS NOT NULL AS has_body FROM mail_log WHERE to_addr = ? COLLATE NOCASE ORDER BY id DESC LIMIT 100');
          $ml->execute([$b['email']]); $mails = $ml->fetchAll(); ?>
    <div class="card" id="emails-envoyes">
      <h2>E-mails envoyés au client <span class="pill"><?= count($mails) ?></span></h2>
      <?php if (!$mails): ?><p class="muted" style="margin:0">Aucun e-mail envoyé à <?= e($b['email']) ?> pour l’instant.</p><?php endif; ?>
      <?php foreach ($mails as $i => $m): ?>
        <?php if ($i === 8): ?><details><summary class="muted" style="cursor:pointer;margin-bottom:8px">Voir les <?= count($mails) - 8 ?> plus anciens</summary><?php endif; ?>
        <p style="margin:0 0 8px;font-size:.9rem;display:flex;gap:8px;align-items:baseline"><span class="muted" style="white-space:nowrap"><?= e(date('d/m/y H\hi', strtotime($m['at']))) ?></span>
          <span style="flex:1;word-break:break-word"><?= $m['ok'] ? '' : '<strong style="color:#b42318">✕ Échec</strong> — ' ?><?= $m['has_body'] ? '<a href="mail.php?id=' . (int)$m['id'] . '">' . e($m['subject']) . '</a>' : e($m['subject']) ?></span></p>
      <?php endforeach; ?>
      <?php if (count($mails) > 8): ?></details><?php endif; ?>
    </div>

    <div class="card">
      <h2>Historique</h2>
      <?php foreach ($log as $l): ?>
        <p style="margin:0 0 8px;font-size:.9rem;<?= preg_match('/ALERTE|ATTENTION|ÉCHEC|Erreur/u', $l['message']) ? 'color:#b42318;font-weight:600' : '' ?>"><span class="muted"><?= e(bk_dt($l['at'])->format('d/m H\hi')) ?></span> — <?= e($l['message']) ?></p>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php admin_footer();
