<?php
/**
 * API du tunnel de réservation (JSON).
 *   GET  api.php?a=month&m=2026-12   disponibilités d'un mois
 *   POST api.php?a=quote             devis (prix calculé côté serveur)
 *   POST api.php?a=book              création de la réservation + lien de paiement
 */
require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out(array $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (s('resa_maintenance') === '1') {
    out(['error' => 'La réservation en ligne est momentanément indisponible.'], 503);
}

$a = (string)($_GET['a'] ?? '');

if ($a === 'month') {
    if (!preg_match('/^(\d{4})-(\d{2})$/', (string)($_GET['m'] ?? ''), $m) || (int)$m[2] < 1 || (int)$m[2] > 12) {
        out(['error' => 'Mois invalide.'], 400);
    }
    $first = bk_dt($m[1] . '-' . $m[2] . '-01');
    $now = bk_now();
    if ($first < $now->modify('first day of this month')->setTime(0, 0) || $first > $now->modify('+' . ((int)bk_num('bk_max_months', 18) + 1) . ' months')) {
        out(['days' => (object)[]]);
    }
    bk_expire_pending();
    out(['days' => bk_month_availability((int)$m[1], (int)$m[2])]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    out(['error' => 'Méthode non autorisée.'], 405);
}
$in = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($in)) {
    out(['error' => 'Requête invalide.'], 400);
}

$req = [
    'mode' => (string)($in['mode'] ?? ''),
    'date' => (string)($in['date'] ?? ''),
    'start' => (string)($in['start'] ?? ''),
    'hours' => (float)($in['hours'] ?? 0),
    'guests' => (int)($in['guests'] ?? 0),
    'options' => array_slice(array_map('intval', (array)($in['options'] ?? [])), 0, 50),
    'asso' => !empty($in['asso']),
];

if ($a === 'quote') {
    $q = bk_quote($req);
    out([
        'ok' => $q['ok'],
        'errors' => $q['errors'],
        'lines' => array_map(fn($l) => ['label' => $l['label'], 'ttc' => ttc((float)$l['total_ht'])], $q['lines'] ?? []),
        'discount_ttc' => isset($q['discount_ht']) ? ttc($q['discount_ht']) : 0,
        'total_ht' => $q['total_ht'] ?? 0,
        'vat' => $q['vat'] ?? 0,
        'total_ttc' => $q['total_ttc'] ?? 0,
    ]);
}

if ($a === 'book') {
    if (!empty($in['website'])) {
        out(['error' => 'Requête refusée.'], 400); // piège à robots
    }
    $t = fn($k, $max = 120) => mb_substr(trim(str_replace(["\r", "\0"], '', (string)($in[$k] ?? ''))), 0, $max);
    $c = [
        'first_name' => $t('first_name', 60), 'last_name' => $t('last_name', 60), 'email' => $t('email', 120), 'phone' => $t('phone', 30),
        'company' => $t('company', 120), 'asso_name' => $t('asso_name', 120), 'address' => $t('address', 200), 'zip' => $t('zip', 12),
        'city' => $t('city', 80), 'message' => $t('message', 2000), 'event_type' => $t('event_type', 80),
    ];
    $errors = [];
    if ($c['first_name'] === '' || $c['last_name'] === '') $errors[] = 'Indiquez votre prénom et votre nom.';
    if (!filter_var($c['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Adresse e-mail invalide.';
    if (!preg_match('/^[0-9 +().-]{6,30}$/', $c['phone'])) $errors[] = 'Numéro de téléphone invalide.';
    if (!in_array($c['event_type'], bk_event_types(), true)) $errors[] = 'Choisissez le type d’événement.';
    if ($req['asso'] && $c['asso_name'] === '') $errors[] = 'Indiquez le nom de l’association.';
    if (empty($in['cgv'])) $errors[] = 'Vous devez accepter les conditions de location.';
    $provider = (string)($in['provider'] ?? '');
    if (!isset(payment_providers()[$provider])) $errors[] = 'Moyen de paiement indisponible.';

    // Anti-abus : pas plus de 3 réservations non payées par visiteur et par heure
    $st = db()->prepare("SELECT COUNT(*) FROM bookings WHERE visitor = ? AND status IN ('pending','expired') AND created_at > ?");
    $st->execute([visitor_id(), bk_now()->modify('-1 hour')->format(BK_FMT)]);
    if ((int)$st->fetchColumn() >= 3) {
        out(['errors' => ['Trop de tentatives de réservation. Réessayez dans une heure ou contactez-nous.']], 429);
    }

    gcal_sync_if_needed(time() - (int)s('_gcal_last_try') > 120); // agenda Google relu juste avant de bloquer le créneau
    $q = bk_quote($req);
    $errors = array_merge($q['errors'] ?? [], $errors);
    if ($errors) {
        out(['errors' => array_values(array_unique($errors))], 422);
    }
    if (isset($in['expected_total']) && abs((float)$in['expected_total'] - $q['total_ttc']) > 0.009) {
        out(['errors' => ['Le prix a été mis à jour : vérifiez le nouveau total (' . money2($q['total_ttc']) . ') puis validez à nouveau.'], 'total_ttc' => $q['total_ttc']], 409);
    }
    try {
        $b = bk_create($q, $c, $provider);
    } catch (DomainException $e) {
        out(['errors' => [$e->getMessage()]], 409);
    }
    bk_log((int)$b['id'], 'Réservation créée (en attente de paiement ' . $provider . ')');
    try {
        $url = $provider === 'paypal' ? paypal_checkout($b) : stripe_checkout($b);
    } catch (Throwable $e) {
        db()->prepare("UPDATE bookings SET status = 'expired', hold_until = NULL WHERE id = ?")->execute([$b['id']]);
        bk_log((int)$b['id'], 'Échec de création du paiement : ' . $e->getMessage());
        out(['errors' => ['Le paiement n’a pas pu être initialisé. Réessayez ou choisissez un autre moyen de paiement.']], 502);
    }
    out(['redirect' => $url]);
}

out(['error' => 'Action inconnue.'], 404);
