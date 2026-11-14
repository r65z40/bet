<?php
/**
 * API du tunnel de réservation (JSON).
 *   GET  api.php?a=month&m=2026-12   disponibilités d'un mois
 *   POST api.php?a=quote             devis (prix calculé côté serveur)
 *   POST api.php?a=book              création de la réservation + lien de paiement
 *   POST api.php?a=devis             devis établi par le client (envoyé par e-mail)
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
// Les appels doivent venir de la page de réservation du site (protection contre les envois
// depuis un autre site : JSON obligatoire, donc pré-vérification CORS par le navigateur).
if (!str_starts_with(strtolower((string)($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json')) {
    out(['error' => 'Requête invalide.'], 415);
}
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)) {
    out(['error' => 'Origine refusée.'], 403);
}
$in = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($in)) {
    out(['error' => 'Requête invalide.'], 400);
}

$req = [
    'mode' => (string)($in['mode'] ?? ''),
    'date' => (string)($in['date'] ?? ''),
    'days' => (int)($in['days'] ?? 1),
    'start' => (string)($in['start'] ?? ''),
    'hours' => (float)($in['hours'] ?? 0),
    'guests' => (int)($in['guests'] ?? 0),
    'options' => array_slice(array_map('intval', (array)($in['options'] ?? [])), 0, 50),
    'asso' => !empty($in['asso']),
    'promo' => mb_substr((string)($in['promo'] ?? ''), 0, 40),
];

if ($a === 'quote') {
    $q = bk_quote($req);
    out([
        'ok' => $q['ok'],
        'errors' => $q['errors'],
        'lines' => array_map(fn($l) => ['label' => $l['label'], 'ttc' => ttc((float)$l['total_ht'])], $q['lines'] ?? []),
        'discount_ttc' => isset($q['discount_ht']) ? ttc($q['discount_ht']) : 0,
        'promo_label' => $q['promo_label'] ?? '',
        'promo_ttc' => !empty($q['promo_ht']) ? ttc($q['promo_ht']) : 0,
        'gift_code' => $q['gift_code'] ?? '',
        'gift_amount' => $q['gift_amount'] ?? 0,
        'amount_due' => $q['amount_due'] ?? 0,
        'code_error' => $q['code_error'] ?? null,
        'total_ht' => $q['total_ht'] ?? 0,
        'vat' => $q['vat'] ?? 0,
        'total_ttc' => $q['total_ttc'] ?? 0,
        // Acompte proposé (ou exigé) pour cette date : montant, solde et échéance
        'acompte' => !empty($q['acompte']) ? [
            'amount' => $q['acompte']['amount'], 'balance' => $q['acompte']['balance'], 'percent' => $q['acompte']['percent'],
            'required' => $q['acompte']['required'], 'due' => fr_date($q['acompte']['due_at'], false),
        ] : null,
    ]);
}

if ($a === 'devis') {
    if (!empty($in['website'])) {
        out(['error' => 'Requête refusée.'], 400);
    }
    $t = fn($k, $max = 120) => mb_substr(trim(str_replace(["\r", "\0"], '', (string)($in[$k] ?? ''))), 0, $max);
    $c = [
        'first_name' => $t('first_name', 60), 'last_name' => $t('last_name', 60), 'email' => $t('email', 120), 'phone' => $t('phone', 30),
        'company' => $t('company', 120), 'asso_name' => $t('asso_name', 120), 'address' => $t('address', 200), 'zip' => $t('zip', 12),
        'city' => $t('city', 80), 'message' => $t('message', 2000), 'event_type' => $t('event_type', 80),
    ];
    $errors = [];
    if ($c['first_name'] === '' || $c['last_name'] === '') $errors[] = 'Indiquez votre prénom et votre nom.';
    if (!filter_var($c['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Indiquez votre adresse e-mail : le devis vous y est envoyé.';
    if ($c['phone'] !== '' && !preg_match('/^[0-9 +().-]{6,30}$/', $c['phone'])) $errors[] = 'Numéro de téléphone invalide.';
    if (!in_array($c['event_type'], bk_event_types(), true)) $errors[] = 'Choisissez le type d’événement.';
    // Anti-abus : 5 devis par visiteur et par heure
    $st = db()->prepare('SELECT COUNT(*) FROM quotes WHERE visitor = ? AND created_at > ?');
    $st->execute([ip_fingerprint(), bk_now()->modify('-1 hour')->format(BK_FMT)]);
    if ((int)$st->fetchColumn() >= 5) {
        out(['errors' => ['Vous avez déjà demandé plusieurs devis : réessayez dans une heure ou contactez-nous.']], 429);
    }
    $q = bk_quote($req);
    $errors = array_merge($q['errors'] ?? [], $errors);
    if ($errors) {
        out(['errors' => array_values(array_unique($errors))], 422);
    }
    $d = devis_create($q, $c, $req);
    after_response(fn() => devis_notify($d), 'devis');
    out(['redirect' => devis_url($d)]);
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

    // Anti-abus : pas plus de 3 réservations non payées par visiteur et par heure
    $st = db()->prepare("SELECT COUNT(*) FROM bookings WHERE visitor = ? AND status IN ('pending','expired') AND created_at > ?");
    $st->execute([ip_fingerprint(), bk_now()->modify('-1 hour')->format(BK_FMT)]);
    if ((int)$st->fetchColumn() >= 3) {
        out(['errors' => ['Trop de tentatives de réservation. Réessayez dans une heure ou contactez-nous.']], 429);
    }
    // Plafond global : empêche de bloquer tout le calendrier avec de fausses réservations non payées
    $st = db()->prepare("SELECT COUNT(*) FROM bookings WHERE status = 'pending' AND hold_until > ?");
    $st->execute([bk_now()->format(BK_FMT)]);
    if ((int)$st->fetchColumn() >= 15) {
        out(['errors' => ['Beaucoup de réservations sont en cours de paiement. Réessayez dans quelques minutes.']], 429);
    }

    gcal_sync_if_needed(true); // agenda Google toujours relu juste avant de bloquer le créneau (un événement ajouté à l'instant compte)
    $q = bk_quote($req);
    $giftOnly = ($q['amount_due'] ?? 1) <= 0 && ($q['gift_code'] ?? '') !== '';
    if ($giftOnly) {
        $provider = 'giftcard'; // entièrement réglé par bon cadeau : pas de paiement en ligne
    } elseif (!isset(payment_providers()[$provider])) {
        $errors[] = 'Moyen de paiement indisponible.';
    }
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
    // Acompte : exigé par le réglage, ou choisi par le client (« au choix »)
    $ac = $q['acompte'] ?? null;
    if ($ac && !$giftOnly && ($ac['required'] || ($in['pay'] ?? '') === 'acompte')) {
        db()->prepare("UPDATE bookings SET pay_plan = 'acompte', acompte_amount = ?, balance_due_at = ? WHERE id = ?")
            ->execute([$ac['amount'], $ac['due_at']->format(BK_FMT), $b['id']]);
        $b = bk_get((int)$b['id']);
    }
    $dv = devis_find((string)($in['devis'] ?? ''), (string)($in['devis_t'] ?? ''));
    if ($dv && (empty($dv['booking_id']) || (bk_get((int)$dv['booking_id'])['status'] ?? '') !== 'confirmed')) {
        db()->prepare('UPDATE quotes SET booking_id = ? WHERE id = ?')->execute([$b['id'], $dv['id']]);
        bk_log((int)$b['id'], 'Réservation faite à partir du devis ' . $dv['number']);
    }
    bk_log((int)$b['id'], 'Réservation créée (en attente de paiement ' . $provider . (bk_is_acompte($b) ? ', acompte de ' . money2((float)$b['acompte_amount']) . ', solde avant le ' . bk_dt($b['balance_due_at'])->format('d/m/Y') : '') . ')');
    if ($giftOnly) {
        bk_confirm_paid((int)$b['id'], 'giftcard', 'bon ' . $b['gift_code'], 0, 'eur');
        out(['redirect' => bk_client_url(bk_get((int)$b['id'])) . '&merci=1']);
    }
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
