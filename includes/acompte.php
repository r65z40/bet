<?php
/**
 * Acompte : selon le réglage, le client paie la totalité à la réservation, ou un acompte (x %)
 * puis le solde avant l'événement (lien envoyé par e-mail, ou depuis son espace client).
 * L'administrateur peut aussi noter un solde reçu hors ligne (virement, chèque, espèces).
 */

const BALANCE_OFFLINE = ['virement' => 'Virement', 'cheque' => 'Chèque', 'especes' => 'Espèces'];

function acompte_mode(): string
{
    $m = s('bk_pay_mode');
    return in_array($m, ['acompte', 'choix'], true) ? $m : 'total';
}

function acompte_percent(): float
{
    return max(10, min(90, bk_num('bk_acompte_percent', 30)));
}

function acompte_days(): int
{
    return max(1, (int)bk_num('bk_solde_days', 30));
}

/**
 * Acompte proposé pour un événement débutant le $start, sur un montant à payer de $due € :
 * ['amount', 'balance', 'due_at', 'percent', 'required'] ou null (totalité exigée).
 * La totalité est exigée si l'échéance du solde serait déjà (presque) passée.
 */
function acompte_offer(DateTimeImmutable $start, float $due): ?array
{
    if (acompte_mode() === 'total' || $due <= 0) {
        return null;
    }
    $dueAt = $start->modify('-' . acompte_days() . ' days')->setTime(23, 59);
    if ($dueAt < bk_now()->modify('+3 days')) {
        return null;
    }
    $amount = round($due * acompte_percent() / 100, 2);
    if ($amount <= 0 || $amount >= $due) {
        return null;
    }
    return ['amount' => $amount, 'balance' => round($due - $amount, 2), 'due_at' => $dueAt, 'percent' => acompte_percent(), 'required' => acompte_mode() === 'acompte'];
}

function bk_is_acompte(array $b): bool
{
    return ($b['pay_plan'] ?? 'total') === 'acompte';
}

/** Montant à payer en ligne à la réservation (acompte, ou totalité). */
function bk_pay_now(array $b): float
{
    return bk_is_acompte($b) ? (float)$b['acompte_amount'] : bk_due($b);
}

/** Montant du solde (qu'il soit payé ou non). */
function bk_balance_amount(array $b): float
{
    return bk_is_acompte($b) ? round(bk_due($b) - (float)$b['acompte_amount'], 2) : 0.0;
}

/** Solde restant à payer (0 si payé ou pas d'acompte). */
function bk_balance(array $b): float
{
    return bk_is_acompte($b) && empty($b['balance_paid_at']) ? bk_balance_amount($b) : 0.0;
}

/** Total encaissé (hors bon cadeau). */
function bk_paid_total(array $b): float
{
    if (empty($b['paid_at'])) {
        return 0.0;
    }
    return round(bk_pay_now($b) + (!empty($b['balance_paid_at']) ? bk_balance_amount($b) : 0), 2);
}

/** Part encaissée en ligne (remboursable automatiquement). */
function bk_paid_online(array $b): float
{
    if (empty($b['paid_at'])) {
        return 0.0;
    }
    $first = $b['provider'] === 'giftcard' ? 0.0 : bk_pay_now($b);
    return round($first + (!empty($b['balance_paid_at']) && in_array($b['balance_method'], ['stripe', 'paypal'], true) ? bk_balance_amount($b) : 0), 2);
}

function balance_method_label(string $m): string
{
    return BALANCE_OFFLINE[$m] ?? provider_label($m);
}

/** Phrase des conditions de location sur le paiement ({paiement}), selon le réglage. */
function acompte_terms(): string
{
    $pct = rating_label((string)acompte_percent()) . ' %';
    $days = acompte_days();
    return match (acompte_mode()) {
        'acompte' => 'un acompte de ' . $pct . ' du prix TTC est réglé à la réservation, et le solde au plus tard ' . $days . ' jours avant l’événement (lien de paiement envoyé par e-mail et disponible dans l’espace client). Pour une réservation faite moins de ' . $days . ' jours avant l’événement, la totalité est réglée à la réservation. À défaut de paiement du solde dans les délais, la réservation peut être annulée selon les conditions d’annulation ci-dessous.',
        'choix' => 'le client choisit de régler la totalité du prix TTC à la réservation, ou un acompte de ' . $pct . ' puis le solde au plus tard ' . $days . ' jours avant l’événement (lien de paiement envoyé par e-mail et disponible dans l’espace client). Pour une réservation faite moins de ' . $days . ' jours avant l’événement, la totalité est réglée à la réservation. À défaut de paiement du solde dans les délais, la réservation peut être annulée selon les conditions d’annulation ci-dessous.',
        default => 'le prix TTC est réglé en totalité à la réservation.',
    };
}

/* ---------- Paiement du solde en ligne ---------- */

function stripe_checkout_balance(array $b): string
{
    $return = bk_url('retour-paiement.php?provider=stripe&solde=1&ref=' . rawurlencode($b['ref']) . '&t=' . $b['token']);
    $session = stripe_request('POST', '/v1/checkout/sessions', [
        'mode' => 'payment',
        'locale' => 'fr',
        'customer_email' => $b['email'],
        'client_reference_id' => (string)$b['id'],
        'metadata' => ['booking_id' => (string)$b['id'], 'ref' => $b['ref'], 'kind' => 'balance'],
        'payment_intent_data' => ['description' => s('site_name') . ' — solde ' . $b['ref'], 'metadata' => ['booking_id' => (string)$b['id'], 'ref' => $b['ref'], 'kind' => 'balance']],
        'line_items' => [[
            'quantity' => 1,
            'price_data' => [
                'currency' => 'eur',
                'unit_amount' => (int)round(bk_balance($b) * 100),
                'product_data' => ['name' => 'Solde — location ' . s('site_name') . ' — ' . bk_slot_label($b), 'description' => 'Réf. ' . $b['ref'] . ' · facture ' . $b['invoice_number']],
            ],
        ]],
        'expires_at' => time() + 31 * 60,
        'success_url' => $return . '&session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => bk_client_url($b) . '#paiement',
    ], 'balance-' . $b['id'] . '-' . intdiv(time(), 1800));
    db()->prepare('UPDATE bookings SET balance_order = ? WHERE id = ?')->execute([$session['id'], $b['id']]);
    return $session['url'];
}

function stripe_check_balance_session(array $b, string $sessionId): bool
{
    if (!preg_match('/^cs_[A-Za-z0-9_]+$/', $sessionId)) {
        return false;
    }
    $s = stripe_request('GET', '/v1/checkout/sessions/' . $sessionId);
    if ((string)($s['client_reference_id'] ?? '') !== (string)$b['id'] || ($s['metadata']['kind'] ?? '') !== 'balance' || ($s['payment_status'] ?? '') !== 'paid') {
        return false;
    }
    return bk_balance_paid((int)$b['id'], 'stripe', (string)($s['payment_intent'] ?? $s['id']), (int)$s['amount_total'], (string)$s['currency']);
}

function paypal_checkout_balance(array $b): string
{
    $return = bk_url('retour-paiement.php?provider=paypal&solde=1&ref=' . rawurlencode($b['ref']) . '&t=' . $b['token']);
    $order = paypal_request('POST', '/v2/checkout/orders', [
        'intent' => 'CAPTURE',
        'purchase_units' => [[
            'reference_id' => $b['ref'] . '-S',
            'custom_id' => (string)$b['id'],
            'invoice_id' => $b['ref'] . '-SOLDE',
            'description' => mb_substr('Solde — location ' . s('site_name') . ' — ' . bk_slot_label($b), 0, 127),
            'amount' => ['currency_code' => 'EUR', 'value' => number_format(bk_balance($b), 2, '.', '')],
        ]],
        'payment_source' => ['paypal' => ['experience_context' => [
            'brand_name' => mb_substr(s('site_name'), 0, 127), 'locale' => 'fr-FR', 'shipping_preference' => 'NO_SHIPPING', 'user_action' => 'PAY_NOW',
            'return_url' => $return, 'cancel_url' => bk_client_url($b) . '#paiement',
        ]]],
    ], 'balance-' . $b['id'] . '-' . intdiv(time(), 1800));
    db()->prepare('UPDATE bookings SET balance_order = ? WHERE id = ?')->execute([$order['id'], $b['id']]);
    foreach ($order['links'] ?? [] as $l) {
        if (in_array($l['rel'], ['payer-action', 'approve'], true)) {
            return $l['href'];
        }
    }
    throw new RuntimeException('PayPal : lien de paiement absent de la réponse.');
}

function paypal_capture_balance(array $b, string $orderId): bool
{
    if ($orderId === '' || !hash_equals((string)$b['balance_order'], $orderId)) {
        return false;
    }
    try {
        $order = paypal_request('POST', '/v2/checkout/orders/' . rawurlencode($orderId) . '/capture', null, 'capture-balance-' . $b['id'] . '-' . $orderId);
    } catch (RuntimeException $e) {
        if (!str_contains($e->getMessage(), 'ORDER_ALREADY_CAPTURED')) {
            throw $e;
        }
        $order = paypal_request('GET', '/v2/checkout/orders/' . rawurlencode($orderId));
    }
    $cap = $order['purchase_units'][0]['payments']['captures'][0] ?? null;
    if (($order['status'] ?? '') !== 'COMPLETED' || !$cap || ($cap['status'] ?? '') !== 'COMPLETED') {
        return false;
    }
    return bk_balance_paid((int)$b['id'], 'paypal', (string)$cap['id'], (int)round((float)$cap['amount']['value'] * 100), (string)$cap['amount']['currency_code']);
}

/**
 * Enregistre le paiement du solde (idempotent : retour client + webhook). $method : stripe, paypal,
 * ou virement / chèque / espèces (noté par l'administrateur, $cents = montant attendu).
 */
function bk_balance_paid(int $id, string $method, string $ref, int $cents, string $currency = 'eur'): bool
{
    $b = bk_get($id);
    if (!$b || $b['status'] !== 'confirmed' || !bk_is_acompte($b)) {
        return false;
    }
    if (!empty($b['balance_paid_at'])) {
        return true; // déjà enregistré
    }
    if (strtolower($currency) !== 'eur' || $cents !== (int)round(bk_balance($b) * 100)) {
        bk_log($id, 'ALERTE : montant du solde reçu (' . money2($cents / 100) . ') différent du solde attendu (' . money2(bk_balance($b)) . ').');
        return false;
    }
    $st = db()->prepare('UPDATE bookings SET balance_paid_at = ?, balance_method = ?, balance_ref = ? WHERE id = ? AND balance_paid_at IS NULL');
    $st->execute([bk_now()->format(BK_FMT), $method, $ref, $id]);
    if (!$st->rowCount()) {
        return true;
    }
    bk_log($id, 'Solde reçu (' . balance_method_label($method) . ', ' . money2($cents / 100) . ')' . ($ref !== '' ? ' — ' . $ref : ''));
    $nb = bk_get($id);
    if (notif_on('balance_paid', 'client')) {
        after_response(fn() => bk_mail_balance($nb, 'solde_recu'), 'e-mail solde reçu');
    }
    admin_notify('balance_paid', 'Solde reçu : ' . $b['first_name'] . ' ' . $b['last_name'], [money2($cents / 100) . ' — ' . balance_method_label($method), notif_booking_line($b)], 'reservation.php?id=' . $id);
    return true;
}

/* ---------- E-mails et tâche planifiée ---------- */

/** E-mails du solde : demande (lien), relance, confirmation de réception. */
function bk_mail_balance(array $b, string $tpl): bool
{
    [$vars, $blocks] = mail_booking_data($b);
    $vars['montant_solde'] = money2(bk_balance_amount($b));
    $vars['montant_acompte'] = money2((float)$b['acompte_amount']);
    $vars['date_limite'] = $b['balance_due_at'] ? fr_date(bk_dt($b['balance_due_at']), false) : '';
    $blocks['bouton'] = '<p style="margin:18px 0">' . mail_button(bk_client_url($b) . '#paiement', $tpl === 'solde_recu' ? 'Ma réservation et ma facture' : '🔒 Régler le solde') . '</p>';
    [$subject, $html] = mail_from_template($tpl, $vars, $blocks);
    $ok = send_mail($b['email'], $subject, $html, mail_reply_to());
    $labels = ['solde_demande' => ['Demande de solde', 'envoyée'], 'solde_relance' => ['Relance du solde', 'envoyée'], 'solde_recu' => ['E-mail « solde reçu »', 'envoyé']];
    [$label, $sent] = $labels[$tpl] ?? [$tpl, 'envoyé'];
    bk_log((int)$b['id'], $label . ($ok ? ' ' . $sent . ' au client' : ' : ÉCHEC de l’envoi (' . mail_last_error() . ')'));
    if ($ok && $tpl === 'solde_demande') {
        db()->prepare('UPDATE bookings SET balance_requested_at = ? WHERE id = ?')->execute([bk_now()->format(BK_FMT), $b['id']]);
    } elseif ($ok && $tpl === 'solde_relance') {
        db()->prepare('UPDATE bookings SET balance_reminded_at = ? WHERE id = ?')->execute([bk_now()->format(BK_FMT), $b['id']]);
    }
    return $ok;
}

/**
 * Tâche planifiée : demande du solde 7 jours avant l'échéance, relance le jour de l'échéance,
 * alerte à l'administration 2 jours après si le solde n'est toujours pas réglé.
 */
function acompte_cron(array &$errors): int
{
    $now = bk_now();
    $n = 0;
    $st = db()->prepare("SELECT * FROM bookings WHERE status = 'confirmed' AND pay_plan = 'acompte' AND balance_paid_at IS NULL AND balance_due_at IS NOT NULL AND start_at > ?");
    $st->execute([$now->modify('-1 day')->format(BK_FMT)]);
    foreach ($st->fetchAll() as $b) {
        $due = bk_dt($b['balance_due_at']);
        try {
            if (empty($b['balance_requested_at']) && $now >= $due->modify('-7 days')) {
                if (notif_on('balance_request', 'client')) {
                    if (bk_mail_balance($b, 'solde_demande')) {
                        $n++;
                    } else {
                        $errors[] = 'solde ' . $b['ref'] . ' : ' . mail_last_error();
                    }
                } else {
                    db()->prepare('UPDATE bookings SET balance_requested_at = ? WHERE id = ?')->execute([$now->format(BK_FMT), $b['id']]);
                }
            } elseif (empty($b['balance_reminded_at']) && $now >= $due->setTime(9, 0)) {
                if (notif_on('balance_request', 'client')) {
                    if (bk_mail_balance($b, 'solde_relance')) {
                        $n++;
                    } else {
                        $errors[] = 'relance solde ' . $b['ref'] . ' : ' . mail_last_error();
                    }
                } else {
                    db()->prepare('UPDATE bookings SET balance_reminded_at = ? WHERE id = ?')->execute([$now->format(BK_FMT), $b['id']]);
                }
            } elseif (empty($b['balance_late_at']) && $now >= $due->modify('+2 days')) {
                db()->prepare('UPDATE bookings SET balance_late_at = ? WHERE id = ?')->execute([$now->format(BK_FMT), $b['id']]);
                bk_log((int)$b['id'], 'Solde non réglé à l’échéance');
                admin_notify('balance_late', '⚠ Solde non réglé : ' . $b['first_name'] . ' ' . $b['last_name'], [
                    money2(bk_balance($b)) . ' attendus avant le ' . fr_date($due, false), notif_booking_line($b), 'Relancez le client ou notez un paiement reçu hors ligne depuis la fiche.'], 'reservation.php?id=' . (int)$b['id']);
            }
        } catch (Throwable $e) {
            $errors[] = 'solde ' . $b['ref'] . ' : ' . $e->getMessage();
        }
    }
    return $n;
}

/** Lignes « acompte / solde » pour les e-mails (tableau du récapitulatif). */
function acompte_mail_rows(array $b): string
{
    if (!bk_is_acompte($b)) {
        return '';
    }
    $row = fn($l, $v, $bold = false) => '<tr><td style="padding:6px 0 0' . ($bold ? ';font-weight:bold' : '') . '">' . $l . '</td><td align="right" style="padding:6px 0 0;white-space:nowrap' . ($bold ? ';font-weight:bold' : '') . '">' . $v . '</td></tr>';
    $h = $row('Acompte (' . e(rating_label((string)round(100 * (float)$b['acompte_amount'] / max(0.01, bk_due($b))))) . ' %)' . (!empty($b['paid_at']) ? ' — réglé' : ''), money2((float)$b['acompte_amount']));
    if (!empty($b['balance_paid_at'])) {
        $h .= $row('Solde — réglé le ' . e(bk_dt($b['balance_paid_at'])->format('d/m/Y')), money2(bk_balance_amount($b)));
    } else {
        $h .= $row('Solde à régler avant le ' . e(fr_date(bk_dt($b['balance_due_at']), false)), money2(bk_balance_amount($b)), true);
    }
    return $h;
}
