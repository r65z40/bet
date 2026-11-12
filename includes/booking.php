<?php
/**
 * Système de réservation : tarifs, disponibilités, création et cycle de vie.
 * Dates stockées en heure locale (Europe/Paris), format « Y-m-d H:i:s ».
 */

const BK_FMT = 'Y-m-d H:i:s';

function bk_tz(): DateTimeZone
{
    static $tz = null;
    return $tz ??= new DateTimeZone('Europe/Paris');
}

function bk_now(): DateTimeImmutable
{
    return new DateTimeImmutable('now', bk_tz());
}

function bk_dt(string $s): DateTimeImmutable
{
    return new DateTimeImmutable($s, bk_tz());
}

/** Réglage numérique (accepte la virgule). */
function bk_num(string $key, float $default = 0): float
{
    $v = trim(str_replace([',', ' '], ['.', ''], s($key)));
    return is_numeric($v) ? (float)$v : $default;
}

function bk_time(string $key, string $default): array
{
    return preg_match('/^(\d{1,2}):(\d{2})$/', s($key), $m) ? [(int)$m[1], (int)$m[2]] : array_map('intval', explode(':', $default));
}

function money(float $v): string
{
    return number_format($v, floor($v) == $v ? 0 : 2, ',', ' ') . ' €';
}

function money2(float $v): string
{
    return number_format($v, 2, ',', ' ') . ' €';
}

function ttc(float $ht): float
{
    return round($ht * (1 + bk_num('bk_vat_rate', 20) / 100), 2);
}

/* ---------- Options ---------- */

function bk_options(bool $all = false): array
{
    return db()->query('SELECT * FROM booking_options' . ($all ? '' : ' WHERE visible = 1') . ' ORDER BY position, id')->fetchAll();
}

function bk_event_types(): array
{
    return lines(s('bk_event_types'));
}

function lines(string $text): array
{
    return array_values(array_filter(array_map('trim', preg_split('/\R/', $text)), fn($l) => $l !== ''));
}

/* ---------- Tarifs ---------- */

/** Prix 24 h HT du jour de la semaine (1 = lundi … 7 = dimanche). */
function bk_day_price(int $isoDay): float
{
    return bk_num('bk_price_' . $isoDay, 0);
}

function bk_vat_factor(): float
{
    return 1 + bk_num('bk_vat_rate', 20) / 100;
}

/**
 * Prix TTC de la location à l'heure, avant plafond :
 * prix de la durée minimum, puis chaque heure supplémentaire un peu moins chère
 * (« dégressivité » en % par heure). Arrondi à l'euro.
 */
function bk_hour_price_ttc(float $hours): float
{
    $minH = bk_num('bk_min_hours', 2);
    $price = bk_num('bk_hour_first_price', 150);
    $extra = bk_num('bk_hour_extra_price', 65);
    $keep = 1 - min(90, max(0, bk_num('bk_hour_degress', 5))) / 100;
    $rest = max(0, $hours - $minH);
    for ($k = 0; $rest > 1e-9; $k++) {
        $part = min(1, $rest);          // une demi-heure compte pour moitié
        $price += $extra * ($keep ** $k) * $part;
        $rest -= $part;
    }
    return round($price);
}

/** Nombre maximum de jours consécutifs réservables (24 h, 48 h, 72 h…). */
function bk_max_days(): int
{
    return max(1, min(7, (int)bk_num('bk_max_days', 3)));
}

/** Remise (%) appliquée à une location de plusieurs jours. */
function bk_multiday_discount(int $days): float
{
    return $days >= 2 ? min(90, max(0, bk_num('bk_discount_' . min($days, 3) . 'd', 0))) : 0.0;
}

/** Prix HT de la location seule. $days : nombre de journées (mode « day »). */
function bk_rental_price(string $mode, DateTimeImmutable $start, float $hours, int $days = 1): float
{
    if ($mode === 'day') {
        $sum = 0.0;
        for ($i = 0; $i < $days; $i++) {
            $sum += bk_day_price((int)$start->modify("+$i day")->format('N'));
        }
        return round($sum * (1 - bk_multiday_discount($days) / 100), 2);
    }
    $price = round(bk_hour_price_ttc($hours) / bk_vat_factor(), 2);
    if (s('bk_hour_cap') !== '0') {
        $price = min($price, bk_day_price((int)$start->format('N')));
    }
    return $price;
}

/**
 * Devis complet à partir d'une demande. Retourne ['ok' => bool, 'errors' => [], ...].
 * $req : mode (day|hour), date (Y-m-d), days (journées, mode day), start (H:i), hours, guests, options[] (ids), asso (bool)
 */
function bk_quote(array $req, ?int $ignoreBooking = null): array
{
    $errors = [];
    $mode = ($req['mode'] ?? '') === 'hour' ? 'hour' : 'day';
    $date = (string)($req['date'] ?? '');
    $guests = (int)($req['guests'] ?? 0);
    $maxGuests = (int)bk_num('bk_max_guests', 120);

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !checkdate((int)substr($date, 5, 2), (int)substr($date, 8, 2), (int)substr($date, 0, 4))) {
        return ['ok' => false, 'errors' => ['Choisissez une date.']];
    }
    $days = 1;
    if ($mode === 'day') {
        $days = max(1, (int)($req['days'] ?? 1));
        if ($days > bk_max_days()) {
            return ['ok' => false, 'errors' => ['Durée non proposée.']];
        }
        [$h, $mi] = bk_time('bk_day_start', '10:00');
        $start = bk_dt($date)->setTime($h, $mi);
        $end = $start->modify("+$days day");
        $hours = 24.0 * $days;
    } else {
        if (!preg_match('/^(\d{2}):(\d{2})$/', (string)($req['start'] ?? ''), $m)) {
            return ['ok' => false, 'errors' => ['Choisissez une heure de début.']];
        }
        $hours = (float)($req['hours'] ?? 0);
        if (!is_finite($hours) || $hours <= 0 || $hours > 24) {
            return ['ok' => false, 'errors' => ['Durée non valide.']];
        }
        $start = bk_dt($date)->setTime((int)$m[1], (int)$m[2]);
        $end = $start->modify('+' . (int)round($hours * 60) . ' minutes');
    }

    $slotError = bk_slot_error($mode, $start, $end, $ignoreBooking);
    if ($slotError) {
        $errors[] = $slotError;
    }
    if ($guests < 1 || $guests > $maxGuests) {
        $errors[] = 'Le nombre de personnes doit être compris entre 1 et ' . $maxGuests . '.';
    }

    $rental = bk_rental_price($mode, $start, $hours, $days);
    $lines = [];
    $lines[] = [
        'label' => $mode === 'day'
            ? 'Location ' . (24 * $days) . ' h — ' . fr_date($start) . ' ' . $start->format('H\hi') . ' → ' . fr_date($end) . ' ' . $end->format('H\hi')
                . (bk_multiday_discount($days) > 0 ? ' (remise ' . rating_label((string)bk_multiday_discount($days)) . ' % incluse)' : '')
            : 'Location ' . fr_hours($hours) . ' — ' . fr_date($start) . ' de ' . $start->format('H\hi') . ' à ' . $end->format('H\hi'),
        'qty' => 1, 'unit_ht' => $rental, 'total_ht' => $rental, 'kind' => 'rental',
    ];
    $chosen = array_map('intval', (array)($req['options'] ?? []));
    foreach (bk_options() as $o) {
        if (!in_array((int)$o['id'], $chosen, true)) {
            continue;
        }
        $qty = $o['unit'] === 'personne' ? max(1, $guests) : 1;
        $lines[] = [
            'label' => $o['label'] . ($o['unit'] === 'personne' ? ' (' . $qty . ' pers. × ' . money2((float)$o['price']) . ' HT)' : ''),
            'qty' => $qty, 'unit_ht' => (float)$o['price'], 'total_ht' => round($qty * (float)$o['price'], 2), 'kind' => 'option', 'option_id' => (int)$o['id'],
        ];
    }
    $subtotal = round(array_sum(array_column($lines, 'total_ht')), 2);
    $discount = 0.0;
    $asso = !empty($req['asso']);
    if ($asso && bk_num('bk_asso_discount') > 0) {
        $discount = round($subtotal * bk_num('bk_asso_discount') / 100, 2);
    }
    $totalHt = round($subtotal - $discount, 2);
    $totalTtc = ttc($totalHt);

    return [
        'ok' => !$errors, 'errors' => $errors,
        'mode' => $mode, 'days' => $days, 'start' => $start, 'end' => $end, 'hours' => $hours, 'guests' => $guests,
        'lines' => $lines, 'subtotal_ht' => $subtotal, 'discount_ht' => $discount, 'asso' => $asso,
        'total_ht' => $totalHt, 'vat' => round($totalTtc - $totalHt, 2), 'total_ttc' => $totalTtc,
    ];
}

/* ---------- Disponibilités ---------- */

/** Intervalles occupés [début, fin] (timestamps) entre deux dates, toutes sources confondues. */
function bk_busy(DateTimeImmutable $from, DateTimeImmutable $to, ?int $ignoreBooking = null): array
{
    $a = $from->format(BK_FMT);
    $b = $to->format(BK_FMT);
    $now = bk_now()->format(BK_FMT);
    $busy = [];
    $st = db()->prepare("SELECT id, start_at, end_at FROM bookings
        WHERE end_at > ? AND start_at < ?
          AND (status = 'confirmed' OR (status = 'pending' AND hold_until > ?))");
    $st->execute([$a, $b, $now]);
    foreach ($st as $r) {
        if ($ignoreBooking && (int)$r['id'] === $ignoreBooking) {
            continue;
        }
        $busy[] = [strtotime($r['start_at']), strtotime($r['end_at']), 'booking'];
    }
    $st = db()->prepare('SELECT start_at, end_at FROM booking_blocks WHERE end_at > ? AND start_at < ?');
    $st->execute([$a, $b]);
    foreach ($st as $r) {
        $busy[] = [strtotime($r['start_at']), strtotime($r['end_at']), 'block'];
    }
    gcal_sync_if_needed();
    $st = db()->prepare('SELECT start_at, end_at FROM ext_events WHERE end_at > ? AND start_at < ?');
    $st->execute([$a, $b]);
    foreach ($st as $r) {
        $busy[] = [strtotime($r['start_at']), strtotime($r['end_at']), 'agenda'];
    }
    return $busy;
}

function bk_conflict(int $s, int $e, array $busy): bool
{
    $buf = (int)round(bk_num('bk_buffer_hours', 2) * 3600);
    foreach ($busy as [$bs, $be]) {
        if ($s < $be + $buf && $e > $bs - $buf) {
            return true;
        }
    }
    return false;
}

/** Vérifie un créneau ; retourne un message d'erreur ou null. */
function bk_slot_error(string $mode, DateTimeImmutable $start, DateTimeImmutable $end, ?int $ignoreBooking = null): ?string
{
    $now = bk_now();
    if ($start < $now->modify('+' . (int)bk_num('bk_min_notice_hours', 48) . ' hours')) {
        return 'Les réservations doivent être faites au moins ' . (int)bk_num('bk_min_notice_hours', 48) . ' h à l’avance.';
    }
    if ($start > $now->modify('+' . (int)bk_num('bk_max_months', 18) . ' months')) {
        return 'Cette date est trop lointaine pour être réservée en ligne.';
    }
    if ($mode === 'hour') {
        $hours = ($end->getTimestamp() - $start->getTimestamp()) / 3600;
        [$oh, $om] = bk_time('bk_hour_open', '08:00');
        [$ch, $cm] = bk_time('bk_hour_close', '21:00');
        $open = $start->setTime($oh, $om);
        $close = $start->setTime($ch, $cm);
        $step = max(15, (int)s('bk_hour_step') ?: 60);
        if ($hours < bk_num('bk_min_hours', 2)) {
            return 'La durée minimum est de ' . fr_hours(bk_num('bk_min_hours', 2)) . '.';
        }
        if ($start < $open || $end > $close) {
            return 'À l’heure, la salle se loue entre ' . $open->format('H\hi') . ' et ' . $close->format('H\hi') . '.';
        }
        $minutes = ((int)$start->format('H') * 60 + (int)$start->format('i')) - ($oh * 60 + $om);
        if ($minutes % $step !== 0 || ((int)round($hours * 60)) % $step !== 0) {
            return 'Horaire non valide.';
        }
    }
    $busy = bk_busy($start->modify('-2 days'), $end->modify('+2 days'), $ignoreBooking);
    if (bk_conflict($start->getTimestamp(), $end->getTimestamp(), $busy)) {
        return 'Ce créneau n’est plus disponible (la salle est réservée, ou trop proche d’une autre location).';
    }
    return null;
}

/**
 * Disponibilités d'un mois, pour le calendrier public :
 * [ 'Y-m-d' => ['day' => bool, 'days' => nb de journées possibles, 'hours' => ['08:00' => durée max en h, …], 'full' => bool] ou null ]
 */
function bk_month_availability(int $year, int $month): array
{
    $first = bk_dt(sprintf('%04d-%02d-01', $year, $month));
    $last = $first->modify('last day of this month');
    $now = bk_now();
    $minStart = $now->modify('+' . (int)bk_num('bk_min_notice_hours', 48) . ' hours');
    $maxStart = $now->modify('+' . (int)bk_num('bk_max_months', 18) . ' months');
    $maxDays = bk_max_days();
    $busy = bk_busy($first->modify('-2 days'), $last->modify('+' . ($maxDays + 2) . ' days'));

    [$dh, $dm] = bk_time('bk_day_start', '10:00');
    [$oh, $om] = bk_time('bk_hour_open', '08:00');
    [$ch, $cm] = bk_time('bk_hour_close', '21:00');
    $step = max(15, (int)s('bk_hour_step') ?: 60);
    $minH = bk_num('bk_min_hours', 2);

    $out = [];
    for ($d = $first; $d <= $last; $d = $d->modify('+1 day')) {
        $key = $d->format('Y-m-d');
        $ds = $d->setTime($dh, $dm);
        $nbDays = 0; // nombre de journées consécutives possibles à partir de ce jour (24 h, 48 h…)
        if ($ds >= $minStart && $ds <= $maxStart) {
            while ($nbDays < $maxDays && !bk_conflict($ds->getTimestamp(), $ds->modify('+' . ($nbDays + 1) . ' day')->getTimestamp(), $busy)) {
                $nbDays++;
            }
        }
        $dayOk = $nbDays > 0;
        $hours = [];
        $close = $d->setTime($ch, $cm);
        for ($t = $d->setTime($oh, $om); $t->modify('+' . (int)round($minH * 60) . ' minutes') <= $close; $t = $t->modify("+$step minutes")) {
            if ($t < $minStart || $t > $maxStart) {
                continue;
            }
            $max = 0;
            for ($e = $t->modify('+' . (int)round($minH * 60) . ' minutes'); $e <= $close; $e = $e->modify("+$step minutes")) {
                if (bk_conflict($t->getTimestamp(), $e->getTimestamp(), $busy)) {
                    break;
                }
                $max = ($e->getTimestamp() - $t->getTimestamp()) / 3600;
            }
            if ($max >= $minH) {
                $hours[$t->format('H:i')] = $max;
            }
        }
        $window = ($close->getTimestamp() - $d->setTime($oh, $om)->getTimestamp()) / 3600;
        $full = isset($hours[$d->setTime($oh, $om)->format('H:i')]) && abs($hours[$d->setTime($oh, $om)->format('H:i')] - $window) < 0.01;
        $out[$key] = ($dayOk || $hours) ? ['day' => $dayOk, 'days' => $nbDays, 'hours' => $hours, 'full' => $full] : null;
    }
    return $out;
}

/* ---------- Réservations ---------- */

function bk_find(string $ref, string $token): ?array
{
    $st = db()->prepare('SELECT * FROM bookings WHERE ref = ?');
    $st->execute([$ref]);
    $b = $st->fetch();
    return ($b && hash_equals($b['token'], $token)) ? $b : null;
}

function bk_get(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM bookings WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/**
 * Crée une réservation « en attente de paiement » et bloque le créneau le temps du paiement.
 * Le contrôle de disponibilité et l'insertion se font dans une transaction exclusive :
 * deux clients ne peuvent pas obtenir le même créneau.
 */
function bk_create(array $quote, array $customer, string $provider): array
{
    $pdo = db();
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $err = bk_slot_error($quote['mode'], $quote['start'], $quote['end']);
        if ($err) {
            throw new DomainException($err);
        }
        $ref = 'RN6-' . bk_now()->format('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        $token = bin2hex(random_bytes(16));
        $hold = bk_now()->modify('+35 minutes'); // > durée de validité d'une session de paiement Stripe (30 min)
        $pdo->prepare('INSERT INTO bookings(ref, token, status, mode, start_at, end_at, hours, guests, event_type,
                first_name, last_name, email, phone, company, is_asso, asso_name, address, zip, city, message,
                lines_json, subtotal_ht, discount_ht, total_ht, vat, total_ttc, provider, hold_until, created_at, cgv_at, visitor)
            VALUES(?,?,?,?,?,?,?,?,?, ?,?,?,?,?,?,?,?,?,?,?, ?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([
                $ref, $token, 'pending', $quote['mode'], $quote['start']->format(BK_FMT), $quote['end']->format(BK_FMT), $quote['hours'], $quote['guests'], $customer['event_type'],
                $customer['first_name'], $customer['last_name'], $customer['email'], $customer['phone'], $customer['company'], $quote['asso'] ? 1 : 0, $customer['asso_name'],
                $customer['address'], $customer['zip'], $customer['city'], $customer['message'],
                json_encode($quote['lines'], JSON_UNESCAPED_UNICODE), $quote['subtotal_ht'], $quote['discount_ht'], $quote['total_ht'], $quote['vat'], $quote['total_ttc'],
                $provider, $hold->format(BK_FMT), bk_now()->format(BK_FMT), bk_now()->format(BK_FMT), ip_fingerprint(),
            ]);
        $id = (int)$pdo->lastInsertId();
        $pdo->exec('COMMIT');
    } catch (Throwable $e) {
        try { $pdo->exec('ROLLBACK'); } catch (Throwable) {}
        throw $e;
    }
    return bk_get($id);
}

/**
 * Confirme une réservation payée (appelé au retour du paiement ET par le webhook :
 * l'opération est idempotente). Le montant payé doit correspondre au total TTC.
 */
function bk_confirm_paid(int $id, string $provider, string $paymentRef, int $amountCents, string $currency): bool
{
    $pdo = db();
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $invoice = bk_confirm_tx($pdo, $id, $provider, $paymentRef, $amountCents, $currency);
        $pdo->exec('COMMIT');
    } catch (Throwable $e) {
        try { $pdo->exec('ROLLBACK'); } catch (Throwable) {}
        throw $e;
    }
    if ($invoice === null) {
        return (bool)bk_get($id);
    }
    if ($invoice === '') {
        bk_log($id, 'ALERTE : montant payé (' . money2($amountCents / 100) . ' ' . strtoupper($currency) . ') différent du total. Réservation non confirmée.');
        return false;
    }
    bk_log($id, 'Paiement reçu (' . $provider . ', ' . money2($amountCents / 100) . ') — facture ' . $invoice);
    // Créneau devenu indisponible entre-temps (ex. événement ajouté dans l'agenda) : on prévient l'admin
    $err = bk_slot_error_after_payment($id);
    if ($err) {
        bk_log($id, 'ATTENTION : ' . $err);
    }
    $b = bk_get($id);
    bk_mail_confirmation($b);
    bk_mail_admin_new($b, $err);
    return true;
}

/** Partie transactionnelle de la confirmation. Retourne le n° de facture, null (déjà confirmée / introuvable) ou '' (montant incorrect). */
function bk_confirm_tx(PDO $pdo, int $id, string $provider, string $paymentRef, int $amountCents, string $currency): ?string
{
    $b = bk_get($id);
    if (!$b || !in_array($b['status'], ['pending', 'expired'], true)) {
        return null; // déjà confirmée (webhook + retour) ou annulée
    }
    if (strtolower($currency) !== 'eur' || $amountCents !== (int)round($b['total_ttc'] * 100)) {
        return '';
    }
    // Numéro de facture séquentiel, sans trou
    $year = bk_now()->format('Y');
    $seqKey = '_invoice_seq_' . $year;
    $seq = (int)(setting_raw($pdo, $seqKey) ?? 0) + 1;
    $pdo->prepare('INSERT OR REPLACE INTO settings(key, value) VALUES(?, ?)')->execute([$seqKey, (string)$seq]);
    $invoice = 'F' . $year . '-' . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
    // Une réservation expirée peut encore être payée (session ouverte jusqu'au bout) : elle redevient confirmée.
    $pdo->prepare("UPDATE bookings SET status = 'confirmed', provider = ?, payment_ref = ?, paid_at = ?, invoice_number = ?, hold_until = NULL WHERE id = ?")
        ->execute([$provider, $paymentRef, bk_now()->format(BK_FMT), $invoice, $id]);
    return $invoice;
}

/** Vérifie a posteriori qu'aucune autre source n'occupe le créneau (sans bloquer la confirmation). */
function bk_slot_error_after_payment(int $id): ?string
{
    $b = bk_get($id);
    $s = bk_dt($b['start_at']);
    $e = bk_dt($b['end_at']);
    $busy = bk_busy($s->modify('-2 days'), $e->modify('+2 days'), $id);
    return bk_conflict($s->getTimestamp(), $e->getTimestamp(), $busy)
        ? 'ce créneau chevauche un autre événement de l’agenda ou une autre réservation : vérifiez le planning.'
        : null;
}

function bk_log(int $id, string $msg): void
{
    db()->prepare('INSERT INTO booking_log(booking_id, at, message) VALUES(?,?,?)')->execute([$id, bk_now()->format(BK_FMT), $msg]);
}

/** Libère les créneaux des paiements abandonnés. */
function bk_expire_pending(): void
{
    db()->prepare("UPDATE bookings SET status = 'expired' WHERE status = 'pending' AND hold_until < ?")
        ->execute([bk_now()->modify('-1 hour')->format(BK_FMT)]);
}

/** Pourcentage remboursable selon la politique d'annulation. */
function bk_refund_percent(array $b): int
{
    $days = (bk_dt($b['start_at'])->getTimestamp() - time()) / 86400;
    $rules = [];
    foreach (lines(s('bk_cancel_rules')) as $l) {
        if (preg_match('/^\s*(\d+)\s*[=:;]\s*(\d+)/', $l, $m)) {
            $rules[(int)$m[1]] = min(100, (int)$m[2]);
        }
    }
    krsort($rules);
    foreach ($rules as $minDays => $pct) {
        if ($days >= $minDays) {
            return $pct;
        }
    }
    return 0;
}

/* ---------- Formats ---------- */

function fr_date(DateTimeImmutable $d, bool $withDay = true): string
{
    static $days = ['', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
    static $months = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    return ($withDay ? $days[(int)$d->format('N')] . ' ' : '') . (int)$d->format('j') . ($d->format('j') === '1' ? 'er' : '') . ' ' . $months[(int)$d->format('n')] . ' ' . $d->format('Y');
}

function fr_hours(float $h): string
{
    $hh = (int)floor($h);
    $mm = (int)round(($h - $hh) * 60);
    return $hh . ' h' . ($mm ? ' ' . str_pad((string)$mm, 2, '0', STR_PAD_LEFT) : '');
}

/** Libellé court du créneau d'une réservation. */
function bk_slot_label(array $b): string
{
    $s = bk_dt($b['start_at']);
    $e = bk_dt($b['end_at']);
    return $b['mode'] === 'day'
        ? 'Du ' . fr_date($s) . ' à ' . $s->format('H\hi') . ' au ' . fr_date($e) . ' à ' . $e->format('H\hi')
        : 'Le ' . fr_date($s) . ', de ' . $s->format('H\hi') . ' à ' . $e->format('H\hi') . ' (' . fr_hours((float)$b['hours']) . ')';
}

function bk_url(string $path): string
{
    // Toujours l'adresse configurée dans l'admin : l'en-tête Host envoyé par le navigateur
    // peut être falsifié et ne doit jamais servir à fabriquer les liens des e-mails ou des paiements.
    $base = rtrim(s('site_url'), '/');
    if (!preg_match('#^https?://[^/\s]+#i', $base)) {
        // Site non configuré (installation locale) : adresse déduite de la requête
        $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        $dir = preg_replace('#/admin$#', '', $dir);
        $base = (is_https() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $dir;
    }
    return $base . '/' . ltrim($path, '/');
}

function bk_client_url(array $b, string $page = 'ma-reservation.php'): string
{
    return bk_url($page . '?ref=' . rawurlencode($b['ref']) . '&t=' . $b['token']);
}
