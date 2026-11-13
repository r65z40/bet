<?php
/**
 * Agenda : import de l'agenda Google (adresse secrète iCal) pour bloquer les créneaux,
 * export des réservations au format iCalendar (abonnement iPhone / Google Agenda)
 * et liens « Ajouter à mon agenda ».
 */

const GCAL_SYNC_EVERY = 180; // secondes entre deux lectures de l'agenda Google

/* ---------- Import ---------- */

function gcal_sync_if_needed(bool $force = false): void
{
    static $done = false;
    if ($done && !$force) {
        return;
    }
    $done = true;
    $url = trim(s('bk_gcal_url'));
    if ($url === '') {
        if (s('_gcal_count') !== '') {
            db()->exec('DELETE FROM ext_events');
            save_setting('_gcal_count', '');
        }
        return;
    }
    $last = (int)s('_gcal_last_try');
    if (!$force && time() - $last < GCAL_SYNC_EVERY) {
        return;
    }
    if (!$force && PHP_SAPI !== 'cli' && empty($GLOBALS['_after_response_running']) && !can_finish_early()) {
        return; // sans PHP-FPM : jamais d'attente pour le visiteur ; relecture par la tâche planifiée et avant chaque réservation
    }
    if (!$force && PHP_SAPI !== 'cli' && empty($GLOBALS['_after_response_running'])) {
        // Données un peu anciennes : on affiche celles qu'on a, et on relit l'agenda après l'envoi de la page.
        // (Avant chaque réservation, la relecture est forcée : un créneau bloqué depuis l'iPhone n'est jamais vendu.)
        save_setting('_gcal_last_try', (string)time());
        after_response(fn() => gcal_sync_if_needed(true), 'relecture agenda Google');
        return;
    }
    // Une seule lecture à la fois sur tout le site : si une autre est en cours, on attend qu'elle finisse
    // (ses données sont alors à jour) au lieu de lancer une seconde connexion à Google.
    $lock = @fopen(DATA_DIR . '/.gcal.lock', 'c');
    if ($lock && !flock($lock, LOCK_EX | LOCK_NB)) {
        $t0 = microtime(true);
        while (!flock($lock, LOCK_EX | LOCK_NB) && microtime(true) - $t0 < 10) {
            usleep(200_000);
        }
        flock($lock, LOCK_UN);
        fclose($lock);
        return;
    }
    save_setting('_gcal_last_try', (string)time()); // évite que plusieurs visiteurs déclenchent la lecture en même temps
    try {
        $ics = http_get_text(preg_replace('#^webcal://#i', 'https://', $url));
        $now = bk_now();
        $events = ical_parse($ics, $now->modify('-3 days'), $now->modify('+' . ((int)bk_num('bk_max_months', 18) + 1) . ' months'));
        $pdo = db();
        $pdo->beginTransaction();
        $pdo->exec('DELETE FROM ext_events');
        $ins = $pdo->prepare('INSERT INTO ext_events(uid, start_at, end_at, summary) VALUES(?,?,?,?)');
        $count = 0;
        foreach ($events as $ev) {
            if (str_starts_with(strtolower($ev['uid']), 'rn6')) {
                continue; // événements écrits par le site lui-même (réservations, blocages) : déjà connus
            }
            $count++;
            $ins->execute([$ev['uid'], $ev['start']->format(BK_FMT), $ev['end']->format(BK_FMT), mb_substr($ev['summary'], 0, 200)]);
        }
        $pdo->commit();
        save_setting('_gcal_count', (string)$count);
        save_setting('_gcal_last_ok', (string)time());
        save_setting('_gcal_error', '');
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        // On garde les événements de la dernière lecture réussie : pas de « trou » dans les blocages
        save_setting('_gcal_error', date('d/m/Y H:i') . ' — ' . $e->getMessage());
    } finally {
        if ($lock) {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
    settings(true);
}

function http_get_text(string $url): string
{
    $allowHttp = getenv('RN6_ALLOW_HTTP_ICS') === '1'; // réservé aux tests locaux
    if (!preg_match($allowHttp ? '#^https?://#i' : '#^https://#i', $url)) {
        throw new RuntimeException('l’adresse doit commencer par https://');
    }
    $host = (string)parse_url($url, PHP_URL_HOST);
    if (!$allowHttp && (filter_var($host, FILTER_VALIDATE_IP) || preg_match('/^(localhost|.*\.local|.*\.internal)$/i', $host))) {
        throw new RuntimeException('adresse non autorisée');
    }
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_MAXFILESIZE => 5_000_000]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        if ($body === false || $code >= 400) {
            throw new RuntimeException('lecture impossible (' . ($err ?: 'HTTP ' . $code) . ')');
        }
    } else {
        $body = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 10]]));
        if ($body === false) {
            throw new RuntimeException('lecture impossible');
        }
    }
    if (!str_contains($body, 'BEGIN:VCALENDAR')) {
        throw new RuntimeException('ce lien ne renvoie pas un agenda iCal (vérifiez l’« adresse secrète au format iCal »)');
    }
    return $body;
}

/** Analyse un fichier iCalendar et renvoie les occurrences [uid, start, end, summary] dans la fenêtre. */
function ical_parse(string $ics, DateTimeImmutable $from, DateTimeImmutable $to): array
{
    $text = preg_replace("/\r?\n[ \t]/", '', str_replace("\r\n", "\n", $ics)); // dépliage des lignes
    preg_match_all('/BEGIN:VEVENT\n(.*?)END:VEVENT/s', $text, $blocks);
    $raw = [];
    $overrides = []; // uid => [timestamps des occurrences remplacées]
    foreach ($blocks[1] as $block) {
        $p = [];
        foreach (explode("\n", trim($block)) as $line) {
            if (!preg_match('/^([A-Z-]+)((?:;[^:]*)?):(.*)$/', $line, $m)) {
                continue;
            }
            $params = [];
            foreach (array_filter(explode(';', $m[2])) as $kv) {
                [$k, $v] = array_pad(explode('=', $kv, 2), 2, '');
                $params[strtoupper($k)] = trim($v, '"');
            }
            $p[$m[1]][] = ['v' => $m[3], 'p' => $params];
        }
        if (empty($p['DTSTART']) || strtoupper($p['STATUS'][0]['v'] ?? '') === 'CANCELLED') {
            if (!empty($p['RECURRENCE-ID']) && !empty($p['UID'])) {
                $overrides[$p['UID'][0]['v']][] = ical_date($p['RECURRENCE-ID'][0])[0]->getTimestamp();
            }
            continue;
        }
        $uid = $p['UID'][0]['v'] ?? bin2hex(random_bytes(6));
        if (!empty($p['RECURRENCE-ID'])) {
            $overrides[$uid][] = ical_date($p['RECURRENCE-ID'][0])[0]->getTimestamp();
        }
        $raw[] = [$uid, $p];
    }

    $out = [];
    foreach ($raw as [$uid, $p]) {
        [$start, $allDay] = ical_date($p['DTSTART'][0]);
        if (!empty($p['DTEND'])) {
            [$end] = ical_date($p['DTEND'][0]);
        } elseif (!empty($p['DURATION'])) {
            $end = $start->add(new DateInterval(ltrim($p['DURATION'][0]['v'], '+')));
        } else {
            $end = $allDay ? $start->modify('+1 day') : $start;
        }
        $duration = $end->getTimestamp() - $start->getTimestamp();
        $summary = str_replace(['\\,', '\\;', '\\n', '\\\\'], [',', ';', ' ', '\\'], $p['SUMMARY'][0]['v'] ?? 'Occupé');

        $exclude = $overrides[$uid] ?? [];
        if (!empty($p['RECURRENCE-ID'])) {
            $exclude = []; // c'est lui-même l'occurrence modifiée
        }
        foreach ($p['EXDATE'] ?? [] as $ex) {
            foreach (explode(',', $ex['v']) as $one) {
                $exclude[] = ical_date(['v' => $one, 'p' => $ex['p']])[0]->getTimestamp();
            }
        }
        $starts = !empty($p['RRULE']) && empty($p['RECURRENCE-ID'])
            ? ical_expand($start, $p['RRULE'][0]['v'], $to)
            : [$start];
        foreach ($starts as $s) {
            if (in_array($s->getTimestamp(), $exclude, true)) {
                continue;
            }
            $e = $s->modify('+' . $duration . ' seconds');
            if ($e > $from && $s < $to) {
                $out[] = ['uid' => $uid, 'start' => $s, 'end' => $e, 'summary' => $summary];
            }
        }
    }
    return $out;
}

/** [DateTimeImmutable en heure de Paris, journée entière ?] */
function ical_date(array $prop): array
{
    $v = trim($prop['v']);
    $tz = bk_tz();
    if (($prop['p']['VALUE'] ?? '') === 'DATE' || preg_match('/^\d{8}$/', $v)) {
        return [DateTimeImmutable::createFromFormat('!Ymd', substr($v, 0, 8), $tz), true];
    }
    if (str_ends_with($v, 'Z')) {
        $d = DateTimeImmutable::createFromFormat('Ymd\THis\Z', $v, new DateTimeZone('UTC'));
    } else {
        try {
            $src = new DateTimeZone($prop['p']['TZID'] ?? 'Europe/Paris');
        } catch (Throwable) {
            $src = $tz;
        }
        $d = DateTimeImmutable::createFromFormat('Ymd\THis', substr($v, 0, 15), $src);
    }
    if (!$d) {
        throw new RuntimeException('date illisible dans l’agenda : ' . $v);
    }
    return [$d->setTimezone($tz), false];
}

/** Occurrences d'une règle de récurrence (RRULE) : DAILY, WEEKLY, MONTHLY, YEARLY. */
function ical_expand(DateTimeImmutable $start, string $rule, DateTimeImmutable $to): array
{
    $r = [];
    foreach (explode(';', $rule) as $kv) {
        [$k, $v] = array_pad(explode('=', $kv, 2), 2, '');
        $r[strtoupper($k)] = $v;
    }
    $freq = $r['FREQ'] ?? '';
    $interval = max(1, (int)($r['INTERVAL'] ?? 1));
    $count = isset($r['COUNT']) ? (int)$r['COUNT'] : PHP_INT_MAX;
    $until = isset($r['UNTIL']) ? ical_date(['v' => $r['UNTIL'], 'p' => []])[0] : $to;
    if ($until > $to) {
        $until = $to;
    }
    $days = ['MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6, 'SU' => 7];
    $byDay = array_filter(explode(',', $r['BYDAY'] ?? ''));
    $time = [(int)$start->format('H'), (int)$start->format('i'), (int)$start->format('s')];
    $out = [];
    $n = 0;
    $add = function (DateTimeImmutable $d) use (&$out, &$n, $count, $until, $start): bool {
        if ($d < $start) {
            return true;
        }
        if ($n >= $count || $d > $until || count($out) > 2000) {
            return false;
        }
        $out[] = $d;
        $n++;
        return true;
    };

    if ($freq === 'DAILY') {
        for ($d = $start; ; $d = $d->modify("+$interval days")) {
            if (!$add($d)) break;
        }
    } elseif ($freq === 'WEEKLY') {
        $wanted = $byDay ? array_map(fn($x) => $days[substr($x, -2)] ?? 1, $byDay) : [(int)$start->format('N')];
        sort($wanted);
        $weekStart = $start->modify('-' . ((int)$start->format('N') - 1) . ' days');
        for ($w = $weekStart; ; $w = $w->modify("+$interval weeks")) {
            foreach ($wanted as $wd) {
                $d = $w->modify('+' . ($wd - 1) . ' days')->setTime(...$time);
                if (!$add($d)) break 2;
            }
        }
    } elseif ($freq === 'MONTHLY' || $freq === 'YEARLY') {
        $step = $freq === 'MONTHLY' ? "+$interval months" : "+$interval years";
        $first = $start->modify('first day of this month');
        for ($m = $first; ; $m = $m->modify($step)) {
            $cands = [];
            if ($byDay) {
                foreach ($byDay as $bd) {
                    if (!preg_match('/^([+-]?\d+)?([A-Z]{2})$/', $bd, $mm) || !isset($days[$mm[2]])) continue;
                    $ord = (int)($mm[1] ?: 1);
                    $names = [1 => 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                    $nth = [1 => 'first', 'second', 'third', 'fourth', 'fifth'];
                    $label = $ord < 0 ? 'last' : ($nth[$ord] ?? 'first');
                    $d = $m->modify("$label {$names[$days[$mm[2]]]} of this month");
                    if ($d->format('m') === $m->format('m')) $cands[] = $d->setTime(...$time);
                }
            } else {
                $day = (int)($r['BYMONTHDAY'] ?? $start->format('j'));
                if ($day <= (int)$m->format('t')) {
                    $cands[] = $m->setDate((int)$m->format('Y'), (int)($freq === 'YEARLY' ? $start->format('n') : $m->format('n')), $day)->setTime(...$time);
                }
            }
            sort($cands);
            foreach ($cands as $d) {
                if (!$add($d)) break 2;
            }
            if ($m > $until) break;
        }
    } else {
        $out[] = $start;
    }
    return $out;
}

/* ---------- Export ---------- */

function ics_escape(string $s): string
{
    return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\\;', '\\,', '\\n', '\\n'], $s);
}

function ics_fold(string $line): string
{
    $out = '';
    while (strlen($line) > 74) {
        $cut = 74;
        while ($cut > 1 && (ord($line[$cut]) & 0xC0) === 0x80) {
            $cut--; // ne pas couper un caractère UTF-8
        }
        $out .= substr($line, 0, $cut) . "\r\n ";
        $line = substr($line, $cut);
    }
    return $out . $line . "\r\n";
}

function ics_utc(string $local): string
{
    return bk_dt($local)->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
}

function ics_event(string $uid, string $start, string $end, string $summary, string $desc = '', string $location = ''): string
{
    $lines = ['BEGIN:VEVENT', 'UID:' . $uid, 'DTSTAMP:' . gmdate('Ymd\THis\Z'), 'DTSTART:' . ics_utc($start), 'DTEND:' . ics_utc($end),
        'SUMMARY:' . ics_escape($summary)];
    if ($desc !== '') $lines[] = 'DESCRIPTION:' . ics_escape($desc);
    if ($location !== '') $lines[] = 'LOCATION:' . ics_escape($location);
    $lines[] = 'END:VEVENT';
    return implode('', array_map('ics_fold', $lines));
}

function ics_calendar(string $name, string $events): string
{
    return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//La Salle RN6//Reservations//FR\r\nCALSCALE:GREGORIAN\r\nMETHOD:PUBLISH\r\n"
        . ics_fold('X-WR-CALNAME:' . ics_escape($name)) . "X-WR-TIMEZONE:Europe/Paris\r\nREFRESH-INTERVAL;VALUE=DURATION:PT15M\r\nX-PUBLISHED-TTL:PT15M\r\n"
        . $events . "END:VCALENDAR\r\n";
}

/** Fichier .ics d'une réservation, pour le client. */
function bk_client_ics(array $b): string
{
    $desc = 'Réservation ' . $b['ref'] . ' — ' . $b['event_type'] . ', ' . $b['guests'] . " personne(s).\nContact : " . s('contact_email');
    return ics_calendar(s('site_name'), ics_event('rn6-' . $b['ref'] . '@lasallern6', $b['start_at'], $b['end_at'], s('site_name') . ' — ' . $b['event_type'], $desc, s('address')));
}

function gcal_template_url(array $b): string
{
    return 'https://calendar.google.com/calendar/render?action=TEMPLATE'
        . '&text=' . rawurlencode(s('site_name') . ' — ' . $b['event_type'])
        . '&dates=' . ics_utc($b['start_at']) . '/' . ics_utc($b['end_at'])
        . '&details=' . rawurlencode('Réservation ' . $b['ref'] . ' — ' . bk_client_url($b))
        . '&location=' . rawurlencode(s('address'));
}
