<?php
/**
 * Caution par empreinte bancaire (Stripe, paiement « à capturer plus tard ») :
 * la somme est seulement bloquée sur la carte du client, puis libérée après le départ,
 * ou encaissée en tout ou partie en cas de dégâts. Une empreinte n'est valable que 7 jours.
 */

const DEPOSIT_STATUSES = [
    ''          => ['Non reçue', 'gray'],
    'recue'     => ['Reçue (chèque)', 'green'],
    'empreinte' => ['Empreinte bancaire en place', 'green'],
    'restituee' => ['Restituée / libérée', 'gray'],
    'retenue'   => ['Retenue (en tout ou partie)', 'red'],
    'expiree'   => ['Empreinte expirée', 'red'],
];

const DEPOSIT_VALIDITY_DAYS = 7;

function deposit_by_card(): bool
{
    return in_array(s('bk_deposit_mode'), ['stripe', 'choix'], true) && isset(payment_providers()['stripe']);
}

/** Le client peut-il aussi donner un chèque (caution au choix, ou chèque seul) ? */
function deposit_cheque_allowed(): bool
{
    return s('bk_deposit_mode') !== 'stripe' || !isset(payment_providers()['stripe']);
}

/** Prix HT de la location seule (sans options), base du calcul de la caution. */
function bk_rental_ht(array $b): float
{
    $sum = 0.0;
    foreach (json_decode($b['lines_json'], true) ?: [] as $l) {
        if (($l['kind'] ?? '') === 'rental') {
            $sum += (float)$l['total_ht'];
        }
    }
    return $sum;
}

/** Montant de la caution d'une réservation (montant propre à la fiche, sinon réglage). */
function bk_deposit_amount(array $b): float
{
    if ($b['deposit_amount'] !== null && $b['deposit_amount'] !== '' && (float)$b['deposit_amount'] > 0) {
        return round((float)$b['deposit_amount'], 2);
    }
    $fixed = (float)bk_num('bk_deposit_fixed', 0);
    return $fixed > 0 ? round($fixed, 2) : round(bk_rental_ht($b) * (float)bk_num('bk_deposit_factor', 2), 2);
}

/** Date à partir de laquelle l'empreinte peut être déposée. */
function deposit_opens_at(array $b): DateTimeImmutable
{
    return bk_dt($b['start_at'])->modify('-' . max(0, (int)bk_num('bk_deposit_days_before', 2)) . ' days')->setTime(0, 0);
}

/** L'empreinte peut-elle être déposée maintenant ? */
function deposit_can_hold(array $b): bool
{
    $now = bk_now();
    return deposit_by_card() && $b['status'] === 'confirmed' && in_array($b['deposit_status'], ['', 'expiree'], true)
        && $now >= deposit_opens_at($b) && $now < bk_dt($b['end_at']) && bk_deposit_amount($b) > 0;
}

/** Date limite de validité de l'empreinte en place. */
function deposit_expires_at(array $b): ?DateTimeImmutable
{
    return $b['deposit_at'] ? bk_dt($b['deposit_at'])->modify('+' . DEPOSIT_VALIDITY_DAYS . ' days') : null;
}

/** Page de paiement Stripe pour l'empreinte (carte uniquement, capture manuelle). */
function deposit_checkout(array $b): string
{
    if (!deposit_can_hold($b)) {
        throw new DomainException('La caution ne peut pas être déposée pour le moment.');
    }
    $amount = bk_deposit_amount($b);
    $return = bk_url('caution.php?ref=' . rawurlencode($b['ref']) . '&t=' . $b['token']);
    $session = stripe_request('POST', '/v1/checkout/sessions', [
        'mode' => 'payment',
        'locale' => 'fr',
        'payment_method_types' => ['card'],
        'customer_email' => $b['email'],
        'client_reference_id' => (string)$b['id'],
        'metadata' => ['booking_id' => (string)$b['id'], 'ref' => $b['ref'], 'kind' => 'deposit'],
        'payment_intent_data' => [
            'capture_method' => 'manual',
            'description' => 'Caution ' . s('site_name') . ' — ' . $b['ref'],
            'metadata' => ['booking_id' => (string)$b['id'], 'ref' => $b['ref'], 'kind' => 'deposit'],
        ],
        'line_items' => [[
            'quantity' => 1,
            'price_data' => [
                'currency' => 'eur',
                'unit_amount' => (int)round($amount * 100),
                'product_data' => ['name' => 'Caution (empreinte bancaire, non débitée) — ' . s('site_name'), 'description' => bk_slot_label($b) . ' · réf. ' . $b['ref']],
            ],
        ]],
        'expires_at' => time() + 31 * 60,
        'success_url' => $return . '&session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => bk_client_url($b) . '#caution',
    ], 'deposit-' . $b['id'] . '-' . (int)round($amount * 100) . '-' . bk_now()->format('YmdH') . '-' . substr(md5((string)$b['deposit_at']), 0, 6));
    return $session['url'];
}

/** Vérifie chez Stripe qu'une empreinte est bien en place (retour client ou webhook) et l'enregistre. */
function deposit_check_session(array $b, string $sessionId): bool
{
    if (!preg_match('/^cs_[A-Za-z0-9_]+$/', $sessionId)) {
        return false;
    }
    $s = stripe_request('GET', '/v1/checkout/sessions/' . $sessionId . '?expand%5B%5D=payment_intent');
    $pi = $s['payment_intent'] ?? null;
    if (is_string($pi)) {
        $pi = stripe_request('GET', '/v1/payment_intents/' . $pi);
    }
    if ((string)($s['client_reference_id'] ?? '') !== (string)$b['id'] || ($s['metadata']['kind'] ?? '') !== 'deposit'
        || !is_array($pi) || ($pi['status'] ?? '') !== 'requires_capture' || strtolower((string)($pi['currency'] ?? '')) !== 'eur') {
        return false;
    }
    $held = round((int)($pi['amount_capturable'] ?? $pi['amount'] ?? 0) / 100, 2);
    // Une seule prise en compte (retour client + webhook en parallèle)
    $st = db()->prepare("UPDATE bookings SET deposit_status = 'empreinte', deposit_pi = ?, deposit_held = ?, deposit_at = ?, deposit_captured = 0, deposit_reason = ''
        WHERE id = ? AND (deposit_pi != ? OR deposit_status != 'empreinte')");
    $st->execute([(string)$pi['id'], $held, bk_now()->format(BK_FMT), $b['id'], (string)$pi['id']]);
    if ($st->rowCount() > 0) {
        bk_log((int)$b['id'], 'Empreinte bancaire de caution en place : ' . money2($held) . ' (Stripe ' . $pi['id'] . ')');
        admin_notify('deposit_held', 'Caution déposée : ' . $b['first_name'] . ' ' . $b['last_name'], [money2($held) . ' bloqués par empreinte bancaire', notif_booking_line($b)], 'reservation.php?id=' . (int)$b['id'], 'caution-' . $b['id']);
    }
    return true;
}

/** Libère l'empreinte (rien n'est débité). */
function deposit_release(array $b, bool $notify, string $by = ''): void
{
    if ($b['deposit_status'] !== 'empreinte' || $b['deposit_pi'] === '') {
        throw new DomainException('Aucune empreinte en place.');
    }
    try {
        stripe_request('POST', '/v1/payment_intents/' . rawurlencode($b['deposit_pi']) . '/cancel', ['cancellation_reason' => 'requested_by_customer'], 'deposit-cancel-' . $b['deposit_pi']);
    } catch (RuntimeException $e) {
        // Déjà annulée ou expirée chez Stripe : rien n'est bloqué sur la carte du client
        if (!preg_match('/cancel|expired|status/i', $e->getMessage())) {
            throw $e;
        }
    }
    db()->prepare("UPDATE bookings SET deposit_status = 'restituee' WHERE id = ?")->execute([$b['id']]);
    bk_log((int)$b['id'], 'Empreinte de caution libérée' . ($by !== '' ? ' (' . $by . ')' : ''));
    if ($notify) {
        $nb = bk_get((int)$b['id']);
        after_response(fn() => deposit_mail($nb, 'caution_liberee'));
    }
    admin_notify('deposit_released', 'Caution libérée : ' . $b['first_name'] . ' ' . $b['last_name'], [money2((float)$b['deposit_held']) . ($by !== '' ? ' — ' . $by : ''), notif_booking_line($b)], 'reservation.php?id=' . (int)$b['id']);
}

/** Encaisse tout ou partie de l'empreinte (le reste est libéré automatiquement par Stripe). */
function deposit_capture(array $b, float $amount, string $reason, bool $notify): float
{
    if ($b['deposit_status'] !== 'empreinte' || $b['deposit_pi'] === '') {
        throw new DomainException('Aucune empreinte en place.');
    }
    $amount = round(min(max(0, $amount), (float)$b['deposit_held']), 2);
    if ($amount <= 0) {
        throw new DomainException('Indiquez le montant à retenir.');
    }
    $reason = trim($reason);
    if ($reason === '') {
        throw new DomainException('Indiquez le motif de la retenue : il est communiqué au client.');
    }
    $pi = stripe_request('POST', '/v1/payment_intents/' . rawurlencode($b['deposit_pi']) . '/capture',
        ['amount_to_capture' => (int)round($amount * 100)], 'deposit-capture-' . $b['deposit_pi']);
    $got = round((int)($pi['amount_received'] ?? round($amount * 100)) / 100, 2);
    db()->prepare("UPDATE bookings SET deposit_status = 'retenue', deposit_captured = ?, deposit_reason = ?, deposit_captured_at = ? WHERE id = ?")
        ->execute([$got, mb_substr($reason, 0, 1000), bk_now()->format(BK_FMT), $b['id']]);
    bk_log((int)$b['id'], 'Caution : ' . money2($got) . ' retenus sur ' . money2((float)$b['deposit_held']) . ' — ' . $reason);
    if ($notify) {
        $nb = bk_get((int)$b['id']);
        after_response(fn() => deposit_mail($nb, 'caution_retenue'));
    }
    admin_notify('deposit_retained', 'Retenue sur caution : ' . $b['first_name'] . ' ' . $b['last_name'], [money2($got) . ' retenus sur ' . money2((float)$b['deposit_held']) . ' — ' . $reason, notif_booking_line($b)], 'reservation.php?id=' . (int)$b['id']);
    return $got;
}

/** E-mails de caution : demande (lien), libération, retenue. */
function deposit_mail(array $b, string $tpl): bool
{
    [$vars, $blocks] = mail_booking_data($b);
    $vars['montant_caution'] = money2($tpl === 'caution_demande' ? bk_deposit_amount($b) : (float)$b['deposit_held']);
    $vars['montant_retenu'] = money2((float)$b['deposit_captured']);
    $vars['motif'] = (string)$b['deposit_reason'];
    $vars['date_limite'] = fr_date(bk_dt($b['end_at']), false);
    $blocks['option_cheque'] = $tpl === 'caution_demande' && deposit_cheque_allowed()
        ? '<p style="margin:0 0 14px">Vous préférez le chèque ? Remettez-le simplement lors de l’état des lieux d’entrée : il ne sera pas encaissé et vous sera rendu après l’état des lieux de sortie.</p>' : '';
    $blocks['bouton'] = '<p style="margin:18px 0">' . mail_button(bk_client_url($b) . '#caution', $tpl === 'caution_demande' ? '🔒 Déposer la caution' : 'Ma réservation') . '</p>';
    [$subject, $html] = mail_from_template($tpl, $vars, $blocks);
    $ok = send_mail($b['email'], $subject, $html, mail_reply_to());
    $labels = ['caution_demande' => 'Demande de caution', 'caution_liberee' => 'E-mail « caution libérée »', 'caution_retenue' => 'E-mail « retenue sur caution »'];
    bk_log((int)$b['id'], ($labels[$tpl] ?? $tpl) . ($ok ? ($tpl === 'caution_demande' ? ' envoyée au client' : ' envoyé au client') : ' : ÉCHEC de l’envoi (' . mail_last_error() . ')'));
    if ($ok && $tpl === 'caution_demande') {
        db()->prepare('UPDATE bookings SET deposit_requested_at = ? WHERE id = ?')->execute([bk_now()->format(BK_FMT), $b['id']]);
    }
    return $ok;
}

/** Tâche planifiée : demandes à J-x, libérations automatiques, empreintes expirées. Retourne [demandes, libérations]. */
function deposit_cron(array &$errors): array
{
    if (!in_array(s('bk_deposit_mode'), ['stripe', 'choix'], true)) {
        return [0, 0];
    }
    $now = bk_now();
    $asked = $released = 0;
    // 1. Demande envoyée au client le jour où l'empreinte devient possible
    if (deposit_by_card()) {
        $st = db()->prepare("SELECT * FROM bookings WHERE status = 'confirmed' AND deposit_status IN ('', 'expiree') AND deposit_requested_at IS NULL
            AND start_at > ? AND start_at <= ?");
        $st->execute([$now->format(BK_FMT), $now->modify('+' . (max(0, (int)bk_num('bk_deposit_days_before', 2)) + 1) . ' days')->format(BK_FMT)]);
        foreach ($st->fetchAll() as $b) {
            if (deposit_can_hold($b) && notif_on('deposit_request', 'client')) {
                if (deposit_mail($b, 'caution_demande')) {
                    $asked++;
                } else {
                    $errors[] = 'caution ' . $b['ref'] . ' : ' . mail_last_error();
                }
                db()->prepare('UPDATE bookings SET deposit_requested_at = ? WHERE id = ?')->execute([$now->format(BK_FMT), $b['id']]);
            }
        }
    }
    // 2. Libération automatique après le départ (ou juste avant l'expiration de l'empreinte)
    $days = (int)bk_num('bk_deposit_release_days', 2);
    foreach (db()->query("SELECT * FROM bookings WHERE deposit_status = 'empreinte'")->fetchAll() as $b) {
        $due = $days > 0 && $now >= bk_dt($b['end_at'])->modify("+$days days");
        $exp = deposit_expires_at($b);
        if ($exp && $now >= $exp) {
            db()->prepare("UPDATE bookings SET deposit_status = 'expiree' WHERE id = ?")->execute([$b['id']]);
            bk_log((int)$b['id'], 'Empreinte de caution expirée (7 jours) : plus rien n’est bloqué sur la carte du client');
            continue;
        }
        if ($due || ($b['status'] === 'cancelled')) {
            try {
                deposit_release($b, $b['status'] !== 'cancelled' && notif_on('deposit_released', 'client'), 'automatique');
                $released++;
            } catch (Throwable $e) {
                $errors[] = 'caution ' . $b['ref'] . ' : ' . $e->getMessage();
            }
        }
    }
    return [$asked, $released];
}
