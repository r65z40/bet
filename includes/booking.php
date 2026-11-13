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
            $sum += bk_day_price_for($start->modify("+$i day")); // dates spéciales comprises
        }
        return round($sum * (1 - bk_multiday_discount($days) / 100), 2);
    }
    $base = bk_day_price((int)$start->format('N'));
    $day = bk_day_price_for($start);
    $price = round(bk_hour_price_ttc($hours) / bk_vat_factor() * ($base > 0 ? $day / $base : 1), 2); // même proportion qu'une date spéciale
    if (s('bk_hour_cap') !== '0') {
        $price = min($price, $day);
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
        [$start, $end] = bk_day_slot(bk_dt($date), $days);
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
    if ($sp = special_for($start)) {
        $lines[0]['label'] .= ' — tarif « ' . $sp['label'] . ' »';
    }
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
    // Code promo (remise) ou bon cadeau (moyen de paiement)
    $promo = null;
    $promoHt = 0.0;
    $gift = null;
    $giftAmount = 0.0;
    $codeError = null;
    $raw = promo_normalize((string)($req['promo'] ?? ''));
    if ($raw !== '') {
        [$p, $codeError] = promo_check($raw, ttc($subtotal - $discount), $ignoreBooking);
        if ($p && $p['kind'] === 'gift') {
            $gift = $p;
        } elseif ($p) {
            $promo = $p;
            $base = round($subtotal - $discount, 2);
            $promoHt = $p['kind'] === 'percent'
                ? round($base * min(100, (float)$p['value']) / 100, 2)
                : min($base, round((float)$p['value'] / bk_vat_factor(), 2));
        }
        if ($codeError) {
            $errors[] = $codeError;
        }
    }
    $totalHt = round($subtotal - $discount - $promoHt, 2);
    $totalTtc = ttc($totalHt);
    if ($gift) {
        $giftAmount = min($totalTtc, gift_available($gift, $ignoreBooking));
    }

    return [
        'ok' => !$errors, 'errors' => $errors, 'code_error' => $codeError,
        'promo_code' => $promo['code'] ?? '', 'promo_ht' => $promoHt,
        'promo_label' => $promo ? 'Code ' . $promo['code'] . ($promo['kind'] === 'percent' ? ' (−' . rating_label((string)(float)$promo['value']) . ' %)' : '') : '',
        'gift_code' => $gift['code'] ?? '', 'gift_amount' => $giftAmount, 'amount_due' => round($totalTtc - $giftAmount, 2),
        'mode' => $mode, 'days' => $days, 'start' => $start, 'end' => $end, 'hours' => $hours, 'guests' => $guests,
        'lines' => $lines, 'subtotal_ht' => $subtotal, 'discount_ht' => $discount, 'asso' => $asso,
        'total_ht' => $totalHt, 'vat' => round($totalTtc - $totalHt, 2), 'total_ttc' => $totalTtc,
    ];
}

/* ---------- Disponibilités ---------- */

/** Intervalles occupés [début, fin] (timestamps) entre deux dates, toutes sources confondues. */
/**
 * Horaires d'une location à la journée commençant le jour $date : départ à l'heure réglée (10 h,
 * état des lieux de sortie), arrivée du client suivant après un court battement (10 h 30).
 * Deux journées peuvent ainsi s'enchaîner. Retourne [arrivée, départ].
 */
function bk_day_slot(DateTimeImmutable $date, int $days = 1): array
{
    [$h, $m] = bk_time('bk_day_start', '10:00');
    $out = $date->setTime($h, $m);
    return [$out->modify('+' . max(0, (int)bk_num('bk_day_gap', 30)) . ' minutes'), $out->modify('+' . max(1, $days) . ' day')];
}

function bk_busy(DateTimeImmutable $from, DateTimeImmutable $to, ?int $ignoreBooking = null): array
{
    $a = $from->format(BK_FMT);
    $b = $to->format(BK_FMT);
    $now = bk_now()->format(BK_FMT);
    $busy = [];
    $st = db()->prepare("SELECT id, mode, start_at, end_at FROM bookings
        WHERE end_at > ? AND start_at < ?
          AND (status = 'confirmed' OR (status = 'pending' AND hold_until > ?))");
    $st->execute([$a, $b, $now]);
    foreach ($st as $r) {
        if ($ignoreBooking && (int)$r['id'] === $ignoreBooking) {
            continue;
        }
        $busy[] = [strtotime($r['start_at']), strtotime($r['end_at']), 'booking', $r['mode']];
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

/**
 * Pour les locations à la journée (24 h, 48 h, 72 h) : un événement de l'agenda Google, même court,
 * bloque la ou les journée(s) qu'il touche (de minuit à minuit). Les horaires exacts de l'événement
 * restent aussi vérifiés (avec le temps mort), par exemple pour la matinée de départ.
 */
function bk_busy_for_days(array $busy): array
{
    if (s('bk_gcal_block_day') === '0') {
        return $busy;
    }
    $tz = bk_now()->getTimezone();
    foreach ($busy as $iv) {
        if (($iv[2] ?? '') === 'agenda') {
            $s = (new DateTimeImmutable('@' . $iv[0]))->setTimezone($tz)->setTime(0, 0);
            $e = (new DateTimeImmutable('@' . max($iv[0], $iv[1] - 1)))->setTimezone($tz)->setTime(0, 0)->modify('+1 day');
            $busy[] = [$s->getTimestamp(), $e->getTimestamp(), 'agenda-jour'];
        }
    }
    return $busy;
}

/**
 * Conflit pour une location à la journée commençant à $s : ses journées (du jour d'arrivée à minuit,
 * autant de jours que de tranches de 24 h) ne doivent contenir aucun événement de l'agenda.
 */
function bk_conflict_day(int $s, int $e, array $dayBusy): bool
{
    $tz = bk_now()->getTimezone();
    $d0 = (new DateTimeImmutable('@' . $s))->setTimezone($tz)->setTime(0, 0);
    $d1 = $d0->modify('+' . max(1, (int)round(($e - $s) / 86400)) . ' days');
    foreach ($dayBusy as $iv) {
        if (($iv[2] ?? '') === 'agenda-jour' && $d0->getTimestamp() < $iv[1] && $d1->getTimestamp() > $iv[0]) {
            return true;
        }
    }
    return bk_conflict($s, $e, array_filter($dayBusy, fn($iv) => ($iv[2] ?? '') !== 'agenda-jour'), true);
}

/**
 * Chevauchement avec le temps mort. Entre deux locations à la journée, pas de temps mort :
 * le battement (départ 10 h, arrivée suivante 10 h 30) est déjà compris dans leurs horaires.
 */
function bk_conflict(int $s, int $e, array $busy, bool $isDay = false): bool
{
    $buf = (int)round(bk_num('bk_buffer_hours', 2) * 3600);
    foreach ($busy as $iv) {
        [$bs, $be] = $iv;
        $b = $isDay && ($iv[2] ?? '') === 'booking' && ($iv[3] ?? '') === 'day' ? 0 : $buf;
        if ($s < $be + $b && $e > $bs - $b) {
            return true;
        }
    }
    return false;
}

/** Vérifie un créneau ; retourne un message d'erreur ou null. */
function bk_slot_error(string $mode, DateTimeImmutable $start, DateTimeImmutable $end, ?int $ignoreBooking = null, bool $admin = false): ?string
{
    $now = bk_now();
    if ($admin) {
        // Déplacement par l'administrateur : délai minimum et date maximum non appliqués
        if ($start < $now) {
            return 'La nouvelle date est déjà passée.';
        }
    } elseif ($start < $now->modify('+' . (int)bk_num('bk_min_notice_hours', 48) . ' hours')) {
        return 'Les réservations doivent être faites au moins ' . (int)bk_num('bk_min_notice_hours', 48) . ' h à l’avance.';
    } elseif ($start > $now->modify('+' . (int)bk_num('bk_max_months', 18) . ' months')) {
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
    if ($mode === 'day' ? bk_conflict_day($start->getTimestamp(), $end->getTimestamp(), bk_busy_for_days($busy)) : bk_conflict($start->getTimestamp(), $end->getTimestamp(), $busy)) {
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
    $dayBusy = bk_busy_for_days($busy);

    [$oh, $om] = bk_time('bk_hour_open', '08:00');
    [$ch, $cm] = bk_time('bk_hour_close', '21:00');
    $step = max(15, (int)s('bk_hour_step') ?: 60);
    $minH = bk_num('bk_min_hours', 2);

    $out = [];
    for ($d = $first; $d <= $last; $d = $d->modify('+1 day')) {
        $key = $d->format('Y-m-d');
        [$ds] = bk_day_slot($d);
        $nbDays = 0; // nombre de journées consécutives possibles à partir de ce jour (24 h, 48 h…)
        if ($ds >= $minStart && $ds <= $maxStart) {
            while ($nbDays < $maxDays && !bk_conflict_day($ds->getTimestamp(), bk_day_slot($d, $nbDays + 1)[1]->getTimestamp(), $dayBusy)) {
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
        $out[$key] = ($dayOk || $hours) ? ['day' => $dayOk, 'days' => $nbDays, 'hours' => $hours, 'full' => $full, 'p' => ttc(bk_day_price_for($d)), 'sp' => special_for($d)['label'] ?? null] : null;
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
        if (!empty($quote['gift_code']) && ($g = promo_find($quote['gift_code'])) && gift_available($g) + 0.001 < (float)$quote['gift_amount']) {
            throw new DomainException('Le solde du bon cadeau vient de changer : vérifiez le montant puis validez à nouveau.');
        }
        if (!empty($quote['promo_code']) && ($p = promo_find($quote['promo_code'])) && (int)$p['max_uses'] > 0 && promo_uses($p['code']) >= (int)$p['max_uses']) {
            throw new DomainException('Ce code promo vient d’atteindre son nombre maximum d’utilisations.');
        }
        $ref = 'RN6-' . bk_now()->format('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        $token = bin2hex(random_bytes(16));
        $hold = bk_now()->modify('+35 minutes'); // > durée de validité d'une session de paiement Stripe (30 min)
        $pdo->prepare('INSERT INTO bookings(ref, token, status, mode, start_at, end_at, hours, guests, event_type,
                first_name, last_name, email, phone, company, is_asso, asso_name, address, zip, city, message,
                lines_json, subtotal_ht, discount_ht, total_ht, vat, total_ttc, provider, hold_until, created_at, cgv_at, visitor,
                promo_code, promo_ht, gift_code, gift_amount, amount_due)
            VALUES(?,?,?,?,?,?,?,?,?, ?,?,?,?,?,?,?,?,?,?,?, ?,?,?,?,?,?,?,?,?,?,?, ?,?,?,?,?)')
            ->execute([
                $ref, $token, 'pending', $quote['mode'], $quote['start']->format(BK_FMT), $quote['end']->format(BK_FMT), $quote['hours'], $quote['guests'], $customer['event_type'],
                $customer['first_name'], $customer['last_name'], $customer['email'], $customer['phone'], $customer['company'], $quote['asso'] ? 1 : 0, $customer['asso_name'],
                $customer['address'], $customer['zip'], $customer['city'], $customer['message'],
                json_encode($quote['lines'], JSON_UNESCAPED_UNICODE), $quote['subtotal_ht'], $quote['discount_ht'], $quote['total_ht'], $quote['vat'], $quote['total_ttc'],
                $provider, $hold->format(BK_FMT), bk_now()->format(BK_FMT), bk_now()->format(BK_FMT), ip_fingerprint(),
                $quote['promo_code'] ?? '', $quote['promo_ht'] ?? 0, $quote['gift_code'] ?? '', $quote['gift_amount'] ?? 0, $quote['amount_due'] ?? $quote['total_ttc'],
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
        $b = bk_get($id);
        // Paiement arrivé alors que la demande avait été annulée entre-temps (par l'administrateur) :
        // l'argent ne doit jamais rester encaissé sans réservation → remboursement automatique, une seule fois.
        if ($b && $b['status'] === 'cancelled' && empty($b['paid_at']) && $paymentRef !== '' && $amountCents > 0) {
            $lock = db()->prepare("UPDATE bookings SET payment_ref = ? WHERE id = ? AND payment_ref = ''");
            $lock->execute([$paymentRef, $id]);
            if ($lock->rowCount()) {
                try {
                    $r = ['id' => $id, 'ref' => $b['ref'], 'payment_ref' => $paymentRef, 'refunded_amount' => 0];
                    $refundId = $provider === 'paypal' ? paypal_refund($r, $amountCents) : stripe_refund($r, $amountCents);
                    bk_log($id, 'Paiement reçu après l’annulation de la demande : remboursé automatiquement (' . money2($amountCents / 100) . ', ' . $provider . ' ' . $refundId . ')');
                    $msg = 'remboursé automatiquement';
                } catch (Throwable $e) {
                    bk_log($id, 'ATTENTION : paiement reçu après l’annulation, remboursement automatique impossible (' . $e->getMessage() . ') : à rembourser depuis ' . $provider);
                    $msg = 'REMBOURSEMENT AUTOMATIQUE IMPOSSIBLE, à faire depuis ' . $provider;
                }
                admin_notify('conflict', '⚠ Paiement reçu sur une demande annulée : ' . $b['first_name'] . ' ' . $b['last_name'], [
                    money2($amountCents / 100) . ' — ' . $msg, notif_booking_line($b)], 'reservation.php?id=' . $id);
            }
        }
        return $b && $b['status'] === 'confirmed';
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
    // E-mails, Google Agenda et notifications : après l'envoi de la page (le client voit tout de suite sa confirmation)
    after_response(function () use ($b, $id, $err) {
        if (notif_on('new_booking', 'client')) {
            bk_mail_confirmation($b);
        } else {
            bk_log($id, 'E-mail de confirmation non envoyé (désactivé dans Réglages › Notifications)');
        }
        if (notif_on('new_booking', 'admin') || ($err && notif_on('conflict', 'admin'))) {
            bk_mail_admin_new($b, $err);
        }
        gcal_sync_booking($b);
        $s = bk_dt($b['start_at']);
        if (notif_on('new_booking', 'push') || ($err && notif_on('conflict', 'push'))) push_notify('reservations', ($err ? '⚠ ' : '💶 ') . 'Nouvelle réservation — ' . money2((float)$b['total_ttc']),
            $b['first_name'] . ' ' . $b['last_name'] . ' · ' . $b['event_type'] . ', ' . (int)$b['guests'] . ' pers. · ' . fr_date($s) . ' ' . $s->format('H\hi')
            . ($b['mode'] === 'day' ? ' (' . (int)$b['hours'] . ' h)' : ' (' . fr_hours((float)$b['hours']) . ')') . ($err ? ' ⚠ conflit d’agenda' : ''),
            'reservation.php?id=' . (int)$b['id'], 'resa-' . $b['id'], $err && !notif_on('new_booking', 'push') ? 'conflict' : 'new_booking');
    }, 'e-mails et agenda (nouvelle réservation)');
    return true;
}

/** Partie transactionnelle de la confirmation. Retourne le n° de facture, null (déjà confirmée / introuvable) ou '' (montant incorrect). */
function bk_confirm_tx(PDO $pdo, int $id, string $provider, string $paymentRef, int $amountCents, string $currency): ?string
{
    $b = bk_get($id);
    if (!$b || !in_array($b['status'], ['pending', 'expired'], true)) {
        return null; // déjà confirmée (webhook + retour) ou annulée
    }
    if (strtolower($currency) !== 'eur' || $amountCents !== (int)round(bk_due($b) * 100)) {
        return '';
    }
    if ($b['gift_code'] !== '' && (float)$b['gift_amount'] > 0) {
        $pdo->prepare('UPDATE promo_codes SET balance = balance - ? WHERE code = ?')->execute([(float)$b['gift_amount'], $b['gift_code']]);
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
    return ($b['mode'] === 'day' ? bk_conflict_day($s->getTimestamp(), $e->getTimestamp(), bk_busy_for_days($busy)) : bk_conflict($s->getTimestamp(), $e->getTimestamp(), $busy))
        ? 'ce créneau chevauche un autre événement de l’agenda ou une autre réservation : vérifiez le planning.'
        : null;
}

/** Montant TTC réglé (ou à régler) en ligne : total moins la part payée par bon cadeau. */
function bk_due(array $b): float
{
    return $b['amount_due'] !== null && $b['amount_due'] !== '' ? (float)$b['amount_due'] : (float)$b['total_ttc'];
}

/**
 * Déplace une réservation confirmée à une autre date (même formule, même durée).
 * Le prix payé et la facture ne changent pas. Vérifie les conflits (le créneau actuel
 * de la réservation est ignoré) et met à jour Google Agenda.
 * Retourne la réservation à jour ; lève DomainException si le créneau n'est pas possible.
 */
function bk_move(array $b, DateTimeImmutable $newStart): array
{
    if ($b['status'] !== 'confirmed') {
        throw new DomainException('Seule une réservation confirmée peut être déplacée.');
    }
    $oldStart = bk_dt($b['start_at']);
    $oldEnd = bk_dt($b['end_at']);
    $newEnd = $newStart->modify('+' . ($oldEnd->getTimestamp() - $oldStart->getTimestamp()) . ' seconds');
    if ($b['mode'] === 'day') { // horaires actuels de la journée (arrivée 10 h 30, départ 10 h)
        [$newStart, $newEnd] = bk_day_slot($newStart->setTime(0, 0), max(1, (int)round(($oldEnd->getTimestamp() - $oldStart->getTimestamp()) / 86400)));
    }
    if ($newStart == $oldStart && $newEnd == $oldEnd) {
        throw new DomainException('La nouvelle date est identique à l’actuelle.');
    }
    $pdo = db();
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $err = bk_slot_error($b['mode'], $newStart, $newEnd, (int)$b['id'], true);
        if ($err) {
            throw new DomainException($err);
        }
        $pdo->prepare('UPDATE bookings SET start_at = ?, end_at = ?, reminder_sent_at = NULL, review_sent_at = NULL, eve_notified_at = NULL, deposit_requested_at = NULL,
                moved_at = ?, moved_from = CASE WHEN moved_from IS NULL OR moved_from = \'\' THEN ? ELSE moved_from END WHERE id = ?')
            ->execute([$newStart->format(BK_FMT), $newEnd->format(BK_FMT), bk_now()->format(BK_FMT), bk_slot_label($b), $b['id']]);
        $pdo->exec('COMMIT');
    } catch (Throwable $e) {
        try { $pdo->exec('ROLLBACK'); } catch (Throwable) {}
        throw $e;
    }
    $nb = bk_get((int)$b['id']);
    bk_log((int)$b['id'], 'Déplacée : ' . bk_slot_label($b) . ' → ' . bk_slot_label($nb));
    // Une empreinte de caution ne vaut que 7 jours : si la nouvelle date est trop loin, elle est libérée
    // et sera redemandée automatiquement avant la nouvelle date.
    if ($nb['deposit_status'] === 'empreinte' && $nb['deposit_at'] && bk_dt($nb['end_at']) > bk_dt($nb['deposit_at'])->modify('+' . (DEPOSIT_VALIDITY_DAYS - 1) . ' days')) {
        try {
            deposit_release($nb, false, 'nouvelle date trop éloignée, sera redemandée');
            db()->prepare("UPDATE bookings SET deposit_status = '', deposit_pi = '', deposit_held = 0, deposit_at = NULL WHERE id = ?")->execute([$nb['id']]);
            $nb = bk_get((int)$b['id']);
        } catch (Throwable $e) {
            bk_log((int)$b['id'], 'Empreinte non libérée après déplacement : ' . $e->getMessage());
        }
    } elseif ($nb['deposit_status'] === 'expiree') {
        db()->prepare("UPDATE bookings SET deposit_status = '' WHERE id = ?")->execute([$nb['id']]);
    }
    // Google Agenda : mêmes identifiants d'événements (mis à jour) ; segments en trop supprimés
    if (gcal_write_enabled()) {
        after_response(function () use ($b, $nb) { // après l'envoi de la page : l'administrateur n'attend pas Google
            $old = count(cal_segments($b['start_at'], $b['end_at']));
            $new = count(cal_segments($nb['start_at'], $nb['end_at']));
            try {
                for ($i = $new; $i < $old; $i++) {
                    gcal_delete_event(gcal_event_id('booking', $b['ref'], $i));
                }
            } catch (Throwable $e) {
                bk_log((int)$b['id'], 'Google Agenda : ancien événement non supprimé (' . $e->getMessage() . ')');
            }
            gcal_sync_booking($nb);
        }, 'Google Agenda (déplacement)');
    }
    return $nb;
}

/**
 * Suppression définitive d'une réservation de test (non confirmée) : elle disparaît des listes,
 * statistiques et de la comptabilité. Si c'était la dernière facture / le dernier avoir de l'année,
 * la numérotation reprend au numéro précédent (pas de trou dans la suite des factures).
 */
function bk_purge(array $b): void
{
    if ($b['status'] === 'confirmed') {
        throw new DomainException('Annulez d’abord la réservation.');
    }
    if ($b['status'] === 'pending' && $b['hold_until'] && bk_dt($b['hold_until']) > bk_now()) {
        throw new DomainException('Paiement en cours : annulez d’abord la demande.');
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM booking_log WHERE booking_id = ?')->execute([$b['id']]);
        $pdo->prepare('UPDATE mail_queue SET booking_id = NULL WHERE booking_id = ?')->execute([$b['id']]);
        $pdo->prepare('DELETE FROM bookings WHERE id = ?')->execute([$b['id']]);
        foreach ([['invoice_number', 'F', '_invoice_seq_'], ['credit_number', 'A', '_credit_seq_']] as [$col, $prefix, $key]) {
            if (!preg_match('/^' . $prefix . '(\d{4})-(\d+)$/', (string)$b[$col], $m)) {
                continue;
            }
            $seqKey = $key . $m[1];
            if ((int)(setting_raw($pdo, $seqKey) ?? 0) === (int)$m[2]) {
                $max = 0;
                $st = $pdo->prepare("SELECT $col FROM bookings WHERE $col LIKE ?");
                $st->execute([$prefix . $m[1] . '-%']);
                foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $n) {
                    $max = max($max, (int)substr($n, 6));
                }
                $pdo->prepare('INSERT OR REPLACE INTO settings(key, value) VALUES(?, ?)')->execute([$seqKey, (string)$max]);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    settings(true);
}

function bk_log(int $id, string $msg): void
{
    db()->prepare('INSERT INTO booking_log(booking_id, at, message) VALUES(?,?,?)')->execute([$id, bk_now()->format(BK_FMT), $msg]);
}

/** Libère les créneaux des paiements abandonnés. */
function bk_expire_pending(): void
{
    // Lecture d'abord : aucune écriture (ni verrou) sur la base quand il n'y a rien à faire, ce qui est le cas courant
    $limit = bk_now()->modify('-1 hour')->format(BK_FMT);
    $st = db()->prepare("SELECT 1 FROM bookings WHERE (status = 'pending' AND hold_until < ?) OR (status = 'expired' AND abandon_notified = 0) LIMIT 1");
    $st->execute([$limit]);
    if (!$st->fetchColumn()) {
        return;
    }
    db()->prepare("UPDATE bookings SET status = 'expired' WHERE status = 'pending' AND hold_until < ?")->execute([$limit]);
    if (notif_on('abandoned', 'admin') || notif_on('abandoned', 'push')) {
        $st = db()->query("SELECT * FROM bookings WHERE status = 'expired' AND abandon_notified = 0 AND paid_at IS NULL");
        foreach ($st->fetchAll() as $b) {
            $upd = db()->prepare('UPDATE bookings SET abandon_notified = 1 WHERE id = ? AND abandon_notified = 0');
            $upd->execute([$b['id']]);
            if ($upd->rowCount()) {
                admin_notify('abandoned', 'Paiement abandonné : ' . $b['first_name'] . ' ' . $b['last_name'], [
                    notif_booking_line($b), 'Total : ' . money2((float)$b['total_ttc']) . ' — ' . $b['email'] . ' · ' . $b['phone'],
                    'Le créneau a été libéré. Vous pouvez recontacter le client.',
                ], 'reservation.php?id=' . (int)$b['id']);
            }
        }
    } else {
        db()->exec("UPDATE bookings SET abandon_notified = 1 WHERE status = 'expired' AND abandon_notified = 0");
    }
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

/** Pages publiques accessibles sans « .php » (adresses propres, voir .htaccess). */
const CLEAN_PAGES = 'plan|reservation|conditions|mentions-legales|confidentialite|ma-reservation|facture|desinscription';

/**
 * Les adresses sans .php sont actives si le serveur Apache réécrit les adresses (.htaccess, mod_rewrite).
 * Détecté sur une vraie requête puis mémorisé, pour que les e-mails envoyés par la tâche planifiée aient aussi les bons liens.
 */
function clean_urls(): bool
{
    static $on = null;
    if ($on === null) {
        $env = (string)($_SERVER['RN6_CLEAN_URLS'] ?? $_SERVER['REDIRECT_RN6_CLEAN_URLS'] ?? getenv('RN6_CLEAN_URLS') ?: '');
        if ($env === '1' && s('_clean_urls') !== '1' && PHP_SAPI !== 'cli') {
            save_setting('_clean_urls', '1');
            settings(true);
        }
        $on = $env === '1' || s('_clean_urls') === '1';
    }
    return $on;
}

/** « plan.php?x=1 » → « plan?x=1 » quand les adresses propres sont actives. */
function u(string $path): string
{
    return clean_urls() ? preg_replace('#^(\./)?(' . CLEAN_PAGES . ')\.php(?=$|[?\#])#', '$2', $path) : $path;
}

function bk_url(string $path): string
{
    $path = u(ltrim($path, '/'));
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
