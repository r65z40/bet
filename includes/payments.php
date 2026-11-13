<?php
/**
 * Paiements : Stripe Checkout et PayPal (Orders v2), via leurs API REST (sans bibliothèque).
 * Le client paie sur la page sécurisée du prestataire : aucune donnée de carte ne transite par le site.
 */

function stripe_api_base(): string
{
    return rtrim(getenv('RN6_STRIPE_API') ?: 'https://api.stripe.com', '/'); // variable d'environnement réservée aux tests
}

function paypal_api_base(): string
{
    if ($test = getenv('RN6_PAYPAL_API')) {
        return rtrim($test, '/');
    }
    return s('bk_paypal_mode') === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
}

function payment_providers(): array
{
    $p = [];
    if (s('bk_stripe_enabled') === '1' && s('bk_stripe_secret') !== '') {
        $p['stripe'] = 'Carte bancaire';
    }
    if (s('bk_paypal_enabled') === '1' && s('bk_paypal_client') !== '' && s('bk_paypal_secret') !== '') {
        $p['paypal'] = 'PayPal';
    }
    return $p;
}

/** Requête HTTP JSON. $body : tableau (form-urlencoded) ou chaîne JSON. */
function http_api(string $method, string $url, array $headers = [], array|string|null $body = null, ?string $basicAuth = null): array
{
    $ch = curl_init($url);
    $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_TIMEOUT => 25, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_HTTPHEADER => $headers];
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = is_array($body) ? http_build_query($body, '', '&') : $body;
    }
    if ($basicAuth !== null) {
        $opts[CURLOPT_USERPWD] = $basicAuth;
    }
    curl_setopt_array($ch, $opts);
    $res = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($res === false) {
        throw new RuntimeException('Service de paiement injoignable (' . $err . ').');
    }
    return [$code, json_decode((string)$res, true) ?: []];
}

/* ---------- Stripe ---------- */

function stripe_request(string $method, string $path, ?array $form = null, ?string $idempotency = null): array
{
    $headers = ['Authorization: Bearer ' . s('bk_stripe_secret'), 'Stripe-Version: 2024-06-20'];
    if ($idempotency) {
        $headers[] = 'Idempotency-Key: ' . $idempotency;
    }
    [$code, $data] = http_api($method, stripe_api_base() . $path, $headers, $form);
    if ($code >= 400) {
        throw new RuntimeException('Stripe : ' . ($data['error']['message'] ?? 'erreur ' . $code));
    }
    return $data;
}

/** Crée la session de paiement Stripe et renvoie l'adresse de la page de paiement. */
function stripe_checkout(array $b): string
{
    $return = bk_url('retour-paiement.php?provider=stripe&ref=' . rawurlencode($b['ref']) . '&t=' . $b['token']);
    $session = stripe_request('POST', '/v1/checkout/sessions', [
        'mode' => 'payment',
        'locale' => 'fr',
        'customer_email' => $b['email'],
        'client_reference_id' => (string)$b['id'],
        'metadata' => ['booking_id' => (string)$b['id'], 'ref' => $b['ref']],
        'payment_intent_data' => ['description' => s('site_name') . ' — ' . $b['ref'], 'metadata' => ['booking_id' => (string)$b['id'], 'ref' => $b['ref']]],
        'line_items' => [[
            'quantity' => 1,
            'price_data' => [
                'currency' => 'eur',
                'unit_amount' => (int)round(bk_due($b) * 100),
                'product_data' => ['name' => 'Location ' . s('site_name') . ' — ' . bk_slot_label($b), 'description' => $b['event_type'] . ' · ' . $b['guests'] . ' pers. · réf. ' . $b['ref']],
            ],
        ]],
        'expires_at' => time() + 31 * 60,
        'success_url' => $return . '&session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => bk_client_url($b) . '&annule=1',
    ], 'checkout-' . $b['id'] . '-' . substr(md5($b['hold_until'] ?? ''), 0, 8));
    db()->prepare('UPDATE bookings SET provider = ?, provider_order = ? WHERE id = ?')->execute(['stripe', $session['id'], $b['id']]);
    return $session['url'];
}

/** Vérifie une session Stripe (retour client) et confirme la réservation si elle est payée. */
function stripe_check_session(array $b, string $sessionId): bool
{
    if (!preg_match('/^cs_[A-Za-z0-9_]+$/', $sessionId)) {
        return false;
    }
    $s = stripe_request('GET', '/v1/checkout/sessions/' . $sessionId);
    if ((string)($s['client_reference_id'] ?? '') !== (string)$b['id'] || ($s['payment_status'] ?? '') !== 'paid') {
        return false;
    }
    return bk_confirm_paid((int)$b['id'], 'stripe', (string)($s['payment_intent'] ?? $s['id']), (int)$s['amount_total'], (string)$s['currency']);
}

/** Vérifie la signature d'un webhook Stripe (en-tête Stripe-Signature). */
function stripe_verify_signature(string $payload, string $header, string $secret, int $tolerance = 300): bool
{
    $t = null;
    $sigs = [];
    foreach (explode(',', $header) as $part) {
        [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
        if ($k === 't') $t = (int)$v;
        if ($k === 'v1') $sigs[] = $v;
    }
    if (!$t || !$sigs || abs(time() - $t) > $tolerance || $secret === '') {
        return false;
    }
    $expected = hash_hmac('sha256', $t . '.' . $payload, $secret);
    foreach ($sigs as $sig) {
        if (hash_equals($expected, $sig)) {
            return true;
        }
    }
    return false;
}

function stripe_refund(array $b, int $cents): string
{
    $r = stripe_request('POST', '/v1/refunds', ['payment_intent' => $b['payment_ref'], 'amount' => $cents, 'reason' => 'requested_by_customer'],
        'refund-' . $b['id'] . '-' . $cents . '-' . (int)round($b['refunded_amount'] * 100));
    return (string)($r['id'] ?? '');
}

/* ---------- PayPal ---------- */

function paypal_token(): string
{
    static $token = null;
    if ($token) {
        return $token;
    }
    [$code, $data] = http_api('POST', paypal_api_base() . '/v1/oauth2/token', ['Accept: application/json'], ['grant_type' => 'client_credentials'],
        s('bk_paypal_client') . ':' . s('bk_paypal_secret'));
    if ($code >= 400 || empty($data['access_token'])) {
        throw new RuntimeException('PayPal : identifiants refusés (vérifiez le Client ID, le Secret et le mode test/production).');
    }
    return $token = $data['access_token'];
}

function paypal_request(string $method, string $path, ?array $json = null, ?string $requestId = null): array
{
    $headers = ['Authorization: Bearer ' . paypal_token(), 'Content-Type: application/json', 'Accept: application/json', 'Prefer: return=representation'];
    if ($requestId) {
        $headers[] = 'PayPal-Request-Id: ' . $requestId;
    }
    [$code, $data] = http_api($method, paypal_api_base() . $path, $headers, $json === null ? null : json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    if ($code >= 400) {
        $detail = $data['details'][0]['issue'] ?? ($data['message'] ?? 'erreur ' . $code);
        throw new RuntimeException('PayPal : ' . $detail);
    }
    return $data;
}

function paypal_checkout(array $b): string
{
    $return = bk_url('retour-paiement.php?provider=paypal&ref=' . rawurlencode($b['ref']) . '&t=' . $b['token']);
    $order = paypal_request('POST', '/v2/checkout/orders', [
        'intent' => 'CAPTURE',
        'purchase_units' => [[
            'reference_id' => $b['ref'],
            'custom_id' => (string)$b['id'],
            'invoice_id' => $b['ref'],
            'description' => mb_substr('Location ' . s('site_name') . ' — ' . bk_slot_label($b), 0, 127),
            'amount' => ['currency_code' => 'EUR', 'value' => number_format(bk_due($b), 2, '.', '')],
        ]],
        'payment_source' => ['paypal' => ['experience_context' => [
            'brand_name' => mb_substr(s('site_name'), 0, 127),
            'locale' => 'fr-FR',
            'shipping_preference' => 'NO_SHIPPING',
            'user_action' => 'PAY_NOW',
            'return_url' => $return,
            'cancel_url' => bk_client_url($b) . '&annule=1',
        ]]],
    ], 'order-' . $b['id'] . '-' . substr(md5($b['hold_until'] ?? ''), 0, 8));
    db()->prepare('UPDATE bookings SET provider = ?, provider_order = ? WHERE id = ?')->execute(['paypal', $order['id'], $b['id']]);
    foreach ($order['links'] ?? [] as $l) {
        if (in_array($l['rel'], ['payer-action', 'approve'], true)) {
            return $l['href'];
        }
    }
    throw new RuntimeException('PayPal : lien de paiement absent de la réponse.');
}

/** Encaisse la commande PayPal approuvée par le client et confirme la réservation. */
function paypal_capture(array $b, string $orderId): bool
{
    if ($orderId === '' || !hash_equals($b['provider_order'], $orderId)) {
        return false; // la commande doit être celle créée pour cette réservation
    }
    try {
        $order = paypal_request('POST', '/v2/checkout/orders/' . rawurlencode($orderId) . '/capture', null, 'capture-' . $b['id'] . '-' . $orderId);
    } catch (RuntimeException $e) {
        if (!str_contains($e->getMessage(), 'ORDER_ALREADY_CAPTURED')) {
            throw $e;
        }
        $order = paypal_request('GET', '/v2/checkout/orders/' . rawurlencode($orderId));
    }
    $cap = $order['purchase_units'][0]['payments']['captures'][0] ?? null;
    if (($order['status'] ?? '') !== 'COMPLETED' || !$cap || ($cap['status'] ?? '') !== 'COMPLETED'
        || (string)($order['purchase_units'][0]['custom_id'] ?? $cap['custom_id'] ?? '') !== (string)$b['id']) {
        return false;
    }
    return bk_confirm_paid((int)$b['id'], 'paypal', (string)$cap['id'], (int)round((float)$cap['amount']['value'] * 100), (string)$cap['amount']['currency_code']);
}

function paypal_refund(array $b, int $cents): string
{
    $r = paypal_request('POST', '/v2/payments/captures/' . rawurlencode($b['payment_ref']) . '/refund',
        ['amount' => ['currency_code' => 'EUR', 'value' => number_format($cents / 100, 2, '.', '')], 'note_to_payer' => 'Annulation ' . $b['ref']],
        'refund-' . $b['id'] . '-' . $cents . '-' . (int)round($b['refunded_amount'] * 100));
    return (string)($r['id'] ?? '');
}

/* ---------- Annulation / remboursement (admin) ---------- */

/**
 * Annule une réservation et rembourse $amount € TTC (0 = sans remboursement).
 * Le remboursement est d'abord demandé au prestataire ; la réservation n'est modifiée qu'en cas de succès.
 */
function bk_cancel(array $b, float $amount, bool $notify, bool $restoreGift = false): float
{
    // Verrou exclusif pendant toute l'opération ; la réservation est relue sous verrou.
    $lock = fopen(DATA_DIR . '/.refund.lock', 'c');
    flock($lock, LOCK_EX);
    try {
        $b = bk_get((int)$b['id']) ?? $b;
        return bk_cancel_locked($b, $amount, $notify, $restoreGift);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function bk_cancel_locked(array $b, float $amount, bool $notify, bool $restoreGift = false): float
{
    // On ne rembourse en ligne que ce qui a été payé en ligne (la part « bon cadeau » se recrédite sur le bon)
    $amount = round(max(0, min($amount, bk_due($b) - (float)$b['refunded_amount'])), 2);
    $refundId = '';
    if ($amount > 0) {
        if (empty($b['paid_at']) || $b['payment_ref'] === '') {
            throw new RuntimeException('Aucun paiement à rembourser pour cette réservation.');
        }
        $cents = (int)round($amount * 100);
        $refundId = $b['provider'] === 'paypal' ? paypal_refund($b, $cents) : stripe_refund($b, $cents);
    }
    $pdo = db();
    $credit = $b['credit_number'];
    $giftBack = $restoreGift && !(int)$b['gift_restored'] && (float)$b['gift_amount'] > 0 && !empty($b['paid_at']) ? (float)$b['gift_amount'] : 0.0;
    if ($giftBack > 0) {
        $pdo->prepare('UPDATE promo_codes SET balance = balance + ? WHERE code = ?')->execute([$giftBack, $b['gift_code']]);
        $pdo->prepare('UPDATE bookings SET gift_restored = 1 WHERE id = ?')->execute([$b['id']]);
        bk_log((int)$b['id'], 'Bon cadeau ' . $b['gift_code'] . ' recrédité de ' . money2($giftBack));
    }
    if (($amount > 0 || $giftBack > 0) && $credit === '') {
        $year = bk_now()->format('Y');
        $seq = (int)(setting_raw($pdo, '_credit_seq_' . $year) ?? 0) + 1;
        save_setting('_credit_seq_' . $year, (string)$seq);
        $credit = 'A' . $year . '-' . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
    }
    $pdo->prepare("UPDATE bookings SET status = 'cancelled', cancelled_at = ?, refunded_amount = refunded_amount + ?, credit_number = ?, hold_until = NULL WHERE id = ?")
        ->execute([bk_now()->format(BK_FMT), $amount, $credit, $b['id']]);
    bk_log((int)$b['id'], 'Réservation annulée' . ($amount > 0 ? ' — remboursement ' . money2($amount) . ' (' . $b['provider'] . ' ' . $refundId . '), avoir ' . $credit : ' sans remboursement'));
    if ($notify && $b['status'] === 'confirmed') {
        bk_mail_cancelled(bk_get((int)$b['id']), $amount);
    }
    gcal_sync_booking(bk_get((int)$b['id'])); // retirée de Google Agenda
    if ($b['deposit_status'] === 'empreinte') {
        try {
            deposit_release($b, false, 'réservation annulée');
        } catch (Throwable $e) {
            bk_log((int)$b['id'], 'Empreinte de caution non libérée : ' . $e->getMessage());
        }
    }
    return $amount;
}
