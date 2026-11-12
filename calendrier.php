<?php
/** Flux iCalendar privé des réservations (abonnement depuis l'iPhone ou Google Agenda). */
require __DIR__ . '/includes/bootstrap.php';

$k = (string)($_GET['k'] ?? '');
if ($k === '' || !hash_equals(s('bk_ics_token'), $k)) {
    http_response_code(404);
    exit;
}
$events = '';
$st = db()->prepare("SELECT * FROM bookings WHERE status = 'confirmed' AND end_at > ? ORDER BY start_at");
$st->execute([bk_now()->modify('-6 months')->format(BK_FMT)]);
foreach ($st as $b) {
    $desc = $b['event_type'] . ' — ' . $b['guests'] . " pers.\n" . $b['first_name'] . ' ' . $b['last_name'] . ' — ' . $b['phone'] . ' — ' . $b['email']
        . "\nTotal : " . money2((float)$b['total_ttc']) . ' TTC — réf. ' . $b['ref'] . "\n" . bk_url('admin/reservation.php?id=' . $b['id']);
    $segs = cal_segments($b['start_at'], $b['end_at']);
    foreach ($segs as $i => [$s, $e]) {
        $events .= ics_event('rn6-' . $b['ref'] . '-' . $i . '@lasallern6', $s, $e,
            '📅 ' . $b['last_name'] . ' — ' . $b['event_type'] . ' (' . $b['guests'] . ' pers.)' . (count($segs) > 1 ? ' ' . ($i + 1) . '/' . count($segs) : ''), $desc, s('address'));
    }
}
foreach (db()->query('SELECT * FROM booking_blocks') as $bl) {
    foreach (cal_segments($bl['start_at'], $bl['end_at']) as $i => [$s, $e]) {
        $events .= ics_event('rn6-bloc-' . $bl['id'] . '-' . $i . '@lasallern6', $s, $e, '⛔ ' . ($bl['label'] ?: 'Indisponible'));
    }
}
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: inline; filename="reservations-rn6.ics"');
header('Cache-Control: no-store');
echo ics_calendar(s('site_name') . ' — réservations', $events);
