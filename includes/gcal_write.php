<?php
/**
 * Écriture dans Google Agenda : les réservations payées et les blocages faits dans l'admin
 * sont ajoutés dans l'agenda Google de la salle, celui où le propriétaire ajoute aussi ses
 * propres blocages. Tout est donc dans un seul agenda.
 *
 * Accès par « compte de service » Google (clé JSON) : jeton signé RS256 avec openssl,
 * sans bibliothèque. Les événements créés par le site ont un identifiant commençant par
 * « rn6 » : la lecture de l'agenda (ical.php) les ignore pour ne pas les compter deux fois.
 */

function gcal_api_base(): string
{
    return rtrim(getenv('RN6_GOOGLE_API') ?: 'https://www.googleapis.com/calendar/v3', '/'); // variable réservée aux tests
}

function gcal_token_url(): string
{
    return getenv('RN6_GOOGLE_TOKEN_URL') ?: 'https://oauth2.googleapis.com/token';
}

/** Clé du compte de service (tableau) ou null si absente / invalide. */
function gcal_service_account(): ?array
{
    static $cache = [];
    $raw = s('bk_gcal_sa');
    if (!array_key_exists($raw, $cache)) {
        $j = json_decode($raw, true);
        $cache[$raw] = is_array($j) && ($j['type'] ?? '') === 'service_account' && !empty($j['client_email'])
            && !empty($j['private_key']) && openssl_pkey_get_private($j['private_key']) ? $j : null;
    }
    return $cache[$raw];
}

function gcal_write_enabled(): bool
{
    return gcal_service_account() !== null && trim(s('bk_gcal_calendar_id')) !== '';
}

function b64url(string $s): string
{
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}

/** Jeton d'accès Google (mis en cache ~55 min). */
function gcal_access_token(): string
{
    if (s('_gcal_token') !== '' && (int)s('_gcal_token_exp') > time() + 60 && s('_gcal_token_for') === md5(s('bk_gcal_sa'))) {
        return s('_gcal_token');
    }
    $sa = gcal_service_account();
    if (!$sa) {
        throw new RuntimeException('clé du compte de service absente ou invalide');
    }
    $now = time();
    $head = b64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $claims = b64url(json_encode([
        'iss' => $sa['client_email'], 'scope' => 'https://www.googleapis.com/auth/calendar.events',
        'aud' => gcal_token_url(), 'iat' => $now, 'exp' => $now + 3600,
    ]));
    $key = openssl_pkey_get_private($sa['private_key']);
    if (!$key || !openssl_sign($head . '.' . $claims, $sig, $key, OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('clé privée illisible (copiez le fichier JSON en entier)');
    }
    [$code, $data] = http_api('POST', gcal_token_url(), ['Accept: application/json'],
        ['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $head . '.' . $claims . '.' . b64url($sig)]);
    if ($code >= 400 || empty($data['access_token'])) {
        throw new RuntimeException('Google a refusé la clé (' . ($data['error_description'] ?? $data['error'] ?? 'erreur ' . $code) . ')');
    }
    save_setting('_gcal_token', $data['access_token']);
    save_setting('_gcal_token_exp', (string)($now + (int)($data['expires_in'] ?? 3600)));
    save_setting('_gcal_token_for', md5(s('bk_gcal_sa')));
    settings(true);
    return $data['access_token'];
}

function gcal_api(string $method, string $path, ?array $body = null): array
{
    $url = gcal_api_base() . '/calendars/' . rawurlencode(trim(s('bk_gcal_calendar_id'))) . $path;
    [$code, $data] = http_api($method, $url, ['Authorization: Bearer ' . gcal_access_token(), 'Content-Type: application/json', 'Accept: application/json'],
        $body === null ? null : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    return [$code, $data];
}

/** Identifiant Google stable d'un événement du site (caractères autorisés : 0-9 a-v). */
function gcal_event_id(string $kind, string $key, int $segment): string
{
    return 'rn6' . substr(md5($kind . '|' . $key . '|' . $segment), 0, 26);
}

/**
 * Découpe un créneau à minuit : une location 10 h → 10 h le lendemain devient
 * 10 h → 23 h 59 + minuit → 10 h. Les agendas l'affichent alors en bloc dans la colonne
 * de chaque jour, au lieu d'une simple ligne « journée entière » en haut.
 */
function cal_segments(string $start, string $end): array
{
    if (s('bk_cal_split') === '0') {
        return [[$start, $end]];
    }
    $out = [];
    $s = bk_dt($start);
    $e = bk_dt($end);
    while ($s < $e) {
        $midnight = $s->setTime(0, 0)->modify('+1 day');
        $segEnd = $midnight < $e ? $midnight : $e;
        // Un bloc qui finit à minuit s'arrête à 23 h 59 : un événement « minuit → minuit »
        // serait sinon affiché comme « journée entière » (bande du haut) par l'iPhone et Google.
        $shownEnd = $segEnd == $midnight ? $midnight->modify('-1 minute') : $segEnd;
        $out[] = [$s->format(BK_FMT), $shownEnd->format(BK_FMT)];
        $s = $segEnd;
    }
    return $out ?: [[$start, $end]];
}

function gcal_datetime(string $local): array
{
    return ['dateTime' => bk_dt($local)->format('Y-m-d\TH:i:s'), 'timeZone' => 'Europe/Paris'];
}

/** Crée ou met à jour un événement (idempotent). */
function gcal_put_event(string $id, array $event): void
{
    $event += ['status' => 'confirmed', 'transparency' => 'opaque', 'reminders' => ['useDefault' => false]];
    [$code, $data] = gcal_api('PUT', '/events/' . $id, $event);
    if ($code === 404) {
        [$code, $data] = gcal_api('POST', '/events', $event + ['id' => $id]);
    }
    if ($code >= 400) {
        throw new RuntimeException('Google Agenda : ' . ($data['error']['message'] ?? 'erreur ' . $code)
            . ($code === 403 || $code === 404 ? ' — l’agenda est-il bien partagé avec le compte de service (« Apporter des modifications aux événements ») ?' : ''));
    }
}

function gcal_delete_event(string $id): void
{
    [$code, $data] = gcal_api('DELETE', '/events/' . $id);
    if ($code >= 400 && !in_array($code, [404, 410], true)) {
        throw new RuntimeException('Google Agenda : ' . ($data['error']['message'] ?? 'erreur ' . $code));
    }
}

function gcal_push_error(?string $msg): void
{
    save_setting('_gcal_push_error', $msg === null ? '' : date('d/m/Y H:i') . ' — ' . $msg);
    settings(true);
}

/** Texte de l'événement d'agenda d'une réservation, selon le niveau de détail choisi. */
function cal_booking_description(array $b, bool $withSlot = false): string
{
    $head = ($withSlot ? bk_slot_label($b) . "\n" : '') . $b['event_type'] . ' — ' . (int)$b['guests'] . ' pers.';
    if (s('bk_gcal_details') === 'minimal') {
        return $head . "\n" . $b['first_name'] . ' ' . $b['last_name'] . ' — réf. ' . $b['ref'];
    }
    return $head . "\n" . $b['first_name'] . ' ' . $b['last_name'] . ' — ' . $b['phone'] . ' — ' . $b['email']
        . "\nTotal : " . money2((float)$b['total_ttc']) . ' TTC — réf. ' . $b['ref'] . "\n" . bk_url('admin/reservation.php?id=' . (int)$b['id']);
}

/** Ajoute (ou retire, si annulée) une réservation dans Google Agenda. Ne bloque jamais le site. */
function gcal_sync_booking(array $b): bool
{
    if (!gcal_write_enabled()) {
        return false;
    }
    try {
        $segments = cal_segments($b['start_at'], $b['end_at']);
        $n = count($segments);
        foreach ($segments as $i => [$st, $en]) {
            $id = gcal_event_id('booking', $b['ref'], $i);
            if ($b['status'] !== 'confirmed') {
                gcal_delete_event($id); // réservation annulée : retirée de l'agenda
                continue;
            }
            gcal_put_event($id, [
                'summary' => '📅 ' . $b['last_name'] . ' — ' . $b['event_type'] . ' (' . (int)$b['guests'] . ' pers.)' . ($n > 1 ? ' ' . ($i + 1) . '/' . $n : ''),
                'description' => cal_booking_description($b, true),
                'location' => s('address'),
                'start' => gcal_datetime($st), 'end' => gcal_datetime($en),
                'colorId' => '10',
            ]);
        }
        gcal_push_error(null);
        return true;
    } catch (Throwable $e) {
        gcal_push_error($e->getMessage());
        if (!empty($b['id'])) {
            bk_log((int)$b['id'], 'Google Agenda : échec de mise à jour (' . $e->getMessage() . ')');
        }
        return false;
    }
}

function gcal_sync_block(array $bl, bool $delete = false): bool
{
    if (!gcal_write_enabled()) {
        return false;
    }
    try {
        foreach (cal_segments($bl['start_at'], $bl['end_at']) as $i => [$st, $en]) {
            $id = gcal_event_id('block', (string)$bl['id'], $i);
            if ($delete) {
                gcal_delete_event($id);
                continue;
            }
            gcal_put_event($id, [
                'summary' => '⛔ ' . ($bl['label'] ?: 'Indisponible') . ' (blocage du site)',
                'start' => gcal_datetime($st), 'end' => gcal_datetime($en), 'colorId' => '8',
            ]);
        }
        gcal_push_error(null);
        return true;
    } catch (Throwable $e) {
        gcal_push_error($e->getMessage());
        return false;
    }
}

/** Réécrit toutes les réservations à venir et les blocages dans Google Agenda. */
function gcal_full_sync(): array
{
    $ok = $fail = 0;
    $now = bk_now()->modify('-1 day')->format(BK_FMT);
    $st = db()->prepare("SELECT * FROM bookings WHERE end_at > ? AND (status = 'confirmed' OR (status = 'cancelled' AND paid_at IS NOT NULL))");
    $st->execute([$now]);
    foreach ($st->fetchAll() as $b) {
        gcal_sync_booking($b) ? $ok++ : $fail++;
        if ($fail > 3) break; // inutile d'insister si l'accès est refusé
    }
    $st = db()->prepare('SELECT * FROM booking_blocks WHERE end_at > ?');
    $st->execute([$now]);
    foreach ($st->fetchAll() as $bl) {
        if ($fail > 3) break;
        gcal_sync_block($bl) ? $ok++ : $fail++;
    }
    return [$ok, $fail];
}
