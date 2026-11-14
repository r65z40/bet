<?php
/**
 * Devis établi par le client lui-même depuis la page de réservation : numéroté, imprimable (PDF),
 * envoyé par e-mail, visible dans l'admin, et « réservable » en un clic (formulaire prérempli).
 * Un devis ne bloque pas la date : elle n'est réservée qu'au paiement.
 */

function devis_days(): int
{
    return max(1, min(90, (int)bk_num('bk_devis_days', 15)));
}

/** Crée le devis à partir d'un calcul de prix valide ($q = bk_quote) et des coordonnées du client. */
function devis_create(array $q, array $c, array $req): array
{
    $pdo = db();
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $year = bk_now()->format('Y');
        $seq = (int)(setting_raw($pdo, '_devis_seq_' . $year) ?? 0) + 1;
        $pdo->prepare('INSERT OR REPLACE INTO settings(key, value) VALUES(?, ?)')->execute(['_devis_seq_' . $year, (string)$seq]);
        $number = 'D' . $year . '-' . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
        $pdo->prepare('INSERT INTO quotes(number, token, created_at, valid_until, first_name, last_name, email, phone, company, asso_name, address, zip, city, message,
                event_type, guests, mode, start_at, end_at, hours, req_json, lines_json, discount_ht, promo_code, promo_ht, total_ht, vat, total_ttc, visitor)
                VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$number, bin2hex(random_bytes(16)), bk_now()->format(BK_FMT), bk_now()->modify('+' . devis_days() . ' days')->setTime(23, 59)->format(BK_FMT),
                $c['first_name'], $c['last_name'], $c['email'], $c['phone'], $c['company'], $c['asso_name'], $c['address'], $c['zip'], $c['city'], $c['message'],
                $c['event_type'], $q['guests'], $q['mode'], $q['start']->format(BK_FMT), $q['end']->format(BK_FMT), $q['hours'],
                json_encode($req, JSON_UNESCAPED_UNICODE), json_encode($q['lines'], JSON_UNESCAPED_UNICODE), $q['discount_ht'], $q['promo_code'] ?? '', $q['promo_ht'] ?? 0,
                $q['total_ht'], $q['vat'], $q['total_ttc'], ip_fingerprint()]);
        $id = (int)$pdo->lastInsertId();
        $pdo->exec('COMMIT');
    } catch (Throwable $e) {
        try { $pdo->exec('ROLLBACK'); } catch (Throwable) {}
        throw $e;
    }
    return devis_get_id($id);
}

function devis_get_id(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM quotes WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function devis_find(string $number, string $token): ?array
{
    $st = db()->prepare('SELECT * FROM quotes WHERE number = ?');
    $st->execute([$number]);
    $d = $st->fetch();
    return $d && hash_equals($d['token'], $token) ? $d : null;
}

function devis_url(array $d): string
{
    return bk_url('devis.php?n=' . rawurlencode($d['number']) . '&t=' . $d['token']);
}

function devis_expired(array $d): bool
{
    return $d['valid_until'] < bk_now()->format(BK_FMT);
}

/** Créneau du devis, au même format que les réservations. */
function devis_slot_label(array $d): string
{
    return bk_slot_label(['mode' => $d['mode'], 'start_at' => $d['start_at'], 'end_at' => $d['end_at'], 'hours' => $d['hours']]);
}

/** Document du devis (div.doc, même présentation que les factures). */
function devis_doc(array $d): string
{
    $rate = (float)bk_num('bk_vat_rate', 20);
    $req = json_decode($d['req_json'], true) ?: [];
    $ac = acompte_offer(bk_dt($d['start_at']), (float)$d['total_ttc']);
    ob_start();
    ?>
<div class="doc">
  <div class="head">
    <div>
      <h1><?= e(s('bk_company_name')) ?></h1>
      <div class="muted"><?= nl2br(e(s('bk_company_address'))) ?><br>
      <?= s('bk_company_siren') ? 'SIREN : ' . e(s('bk_company_siren')) . '<br>' : '' ?>
      <?= s('bk_company_vat') ? 'TVA intracom. : ' . e(s('bk_company_vat')) . '<br>' : '' ?>
      <?= e(s('contact_email')) ?></div>
    </div>
    <div style="text-align:right">
      <h1>DEVIS</h1>
      <strong>N° <?= e($d['number']) ?></strong><br>
      Date : <?= e(bk_dt($d['created_at'])->format('d/m/Y')) ?><br>
      Valable jusqu’au <?= e(bk_dt($d['valid_until'])->format('d/m/Y')) ?>
    </div>
  </div>
  <div style="background:#f4f6f8;padding:14px 16px;border-radius:8px;max-width:360px">
    <strong>Client</strong><br>
    <?= $d['company'] !== '' ? e($d['company']) . '<br>' : '' ?>
    <?= $d['asso_name'] !== '' ? e($d['asso_name']) . '<br>' : '' ?>
    <?= e($d['first_name'] . ' ' . $d['last_name']) ?><br>
    <?= $d['address'] !== '' ? e($d['address']) . '<br>' : '' ?>
    <?= e(trim($d['zip'] . ' ' . $d['city'])) ?><?= trim($d['zip'] . $d['city']) !== '' ? '<br>' : '' ?>
    <?= e($d['email']) ?><?= $d['phone'] !== '' ? '<br>' . e($d['phone']) : '' ?>
  </div>
  <p style="margin:22px 0 0"><strong>Location de la salle</strong> — <?= e(devis_slot_label($d)) ?><br>
    <span class="muted"><?= e($d['event_type']) ?>, <?= (int)$d['guests'] ?> personne(s) · <?= e(s('address')) ?></span></p>
  <table>
    <tr><th>Désignation</th><th class="n">Qté</th><th class="n">P.U. HT</th><th class="n">Total HT</th></tr>
<?php foreach (json_decode($d['lines_json'], true) ?: [] as $l): ?>
    <tr><td><?= e($l['label']) ?></td><td class="n"><?= (int)$l['qty'] ?></td><td class="n"><?= money2((float)$l['unit_ht']) ?></td><td class="n"><?= money2((float)$l['total_ht']) ?></td></tr>
<?php endforeach; ?>
<?php if ((float)$d['promo_ht'] > 0): ?>
    <tr><td>Code promo <?= e($d['promo_code']) ?></td><td class="n">1</td><td class="n">− <?= money2((float)$d['promo_ht']) ?></td><td class="n">− <?= money2((float)$d['promo_ht']) ?></td></tr>
<?php endif; ?>
<?php if ((float)$d['discount_ht'] > 0): ?>
    <tr><td>Remise association (<?= e(rating_label((string)bk_num('bk_asso_discount'))) ?> %)</td><td class="n">1</td><td class="n">− <?= money2((float)$d['discount_ht']) ?></td><td class="n">− <?= money2((float)$d['discount_ht']) ?></td></tr>
<?php endif; ?>
  </table>
  <table class="tot">
    <tr><td>Total HT</td><td class="n"><?= money2((float)$d['total_ht']) ?></td></tr>
    <tr><td>TVA <?= e(rating_label((string)$rate)) ?> %</td><td class="n"><?= money2((float)$d['vat']) ?></td></tr>
    <tr class="big"><td>Total TTC</td><td class="n"><?= money2((float)$d['total_ttc']) ?></td></tr>
  </table>
  <div style="font-size:13px;line-height:1.55;margin-top:18px">
    <p style="margin:0 0 8px"><strong>Paiement :</strong> <?= e(ucfirst(acompte_terms())) ?><?= $ac ? ' Pour cette date : acompte de ' . money2($ac['amount']) . ', puis solde de ' . money2($ac['balance']) . ' avant le ' . e($ac['due_at']->format('d/m/Y')) . '.' : '' ?></p>
    <p style="margin:0 0 8px"><strong>Caution :</strong> <?= e(s('bk_deposit_text')) ?></p>
    <p style="margin:0" class="muted">Ce devis ne bloque pas la date : elle est réservée au moment du paiement en ligne, sous réserve de disponibilité. Tarifs en vigueur à la date du devis ; conditions de location : <?= e(bk_url('conditions.php')) ?></p>
  </div>
  <div class="foot">Devis établi en ligne par le client le <?= e(bk_dt($d['created_at'])->format('d/m/Y à H\hi')) ?>. Bon pour accord : la réservation en ligne vaut acceptation du devis et des conditions de location.</div>
</div>
<?php
    return (string)ob_get_clean();
}

/** E-mail au client avec le lien du devis ; alerte à l'administration. */
function devis_notify(array $d): void
{
    [$vars, $blocks] = [['prenom' => $d['first_name'], 'nom' => $d['last_name'], 'numero' => $d['number'], 'reservation' => devis_slot_label($d),
        'total' => money2((float)$d['total_ttc']), 'validite' => bk_dt($d['valid_until'])->format('d/m/Y'), 'lien' => devis_url($d)], []];
    $blocks['bouton'] = '<p style="margin:18px 0">' . mail_button(devis_url($d), '📄 Voir et imprimer le devis') . mail_button(bk_url('reservation.php?devis=' . rawurlencode($d['number']) . '&t=' . $d['token']), 'Réserver ce devis', false) . '</p>';
    [$subject, $html] = mail_from_template('devis', $vars, $blocks);
    send_mail($d['email'], $subject, $html, mail_reply_to());
    admin_notify('quote', 'Devis demandé : ' . $d['first_name'] . ' ' . $d['last_name'], [
        $d['number'] . ' — ' . money2((float)$d['total_ttc']), $d['event_type'] . ', ' . (int)$d['guests'] . ' pers. · ' . devis_slot_label($d),
        $d['email'] . ($d['phone'] !== '' ? ' · ' . $d['phone'] : ''), $d['message'] !== '' ? 'Message : ' . $d['message'] : ''], 'devis.php');
}

/** Données pour préremplir le formulaire de réservation à partir d'un devis valide. */
function devis_prefill(array $d): array
{
    $r = json_decode($d['req_json'], true) ?: [];
    return [
        'number' => $d['number'], 'token' => $d['token'],
        'mode' => $d['mode'], 'date' => bk_dt($d['start_at'])->format('Y-m-d'), 'days' => (int)($r['days'] ?? 1),
        'start' => $d['mode'] === 'hour' ? bk_dt($d['start_at'])->format('H:i') : null, 'hours' => $d['mode'] === 'hour' ? (float)$d['hours'] : null,
        'guests' => (int)$d['guests'], 'options' => array_map('intval', (array)($r['options'] ?? [])), 'asso' => !empty($r['asso']), 'promo' => (string)($r['promo'] ?? ''),
        'fields' => ['event_type' => $d['event_type'], 'first_name' => $d['first_name'], 'last_name' => $d['last_name'], 'email' => $d['email'], 'phone' => $d['phone'],
            'company' => $d['company'], 'asso_name' => $d['asso_name'], 'address' => $d['address'], 'zip' => $d['zip'], 'city' => $d['city'], 'message' => $d['message']],
    ];
}
