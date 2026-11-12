<?php
/**
 * Tarifs des dates spéciales (fêtes, haute saison), codes promo et bons cadeaux.
 *
 * - Date spéciale : majoration (ou remise) en % sur le prix du jour, ou prix de journée fixe.
 *   La location à l'heure suit la même proportion.
 * - Code promo : remise en % ou en € TTC, avec dates de validité, nombre d'utilisations
 *   et montant minimum.
 * - Bon cadeau : montant prépayé (avec solde). C'est un moyen de paiement, pas une remise :
 *   la facture garde le prix TTC complet et indique la part réglée par le bon.
 */

/* ---------- Dates spéciales ---------- */

function special_for(DateTimeImmutable $d): ?array
{
    static $all = null;
    $all ??= db()->query('SELECT * FROM special_prices WHERE active = 1 ORDER BY id DESC')->fetchAll();
    $day = $d->format('Y-m-d');
    foreach ($all as $sp) {
        if ($day >= $sp['date_from'] && $day <= $sp['date_to']) {
            return $sp;
        }
    }
    return null;
}

/** Prix HT de la journée (24 h) commençant à cette date, dates spéciales comprises. */
function bk_day_price_for(DateTimeImmutable $d): float
{
    $base = bk_day_price((int)$d->format('N'));
    $sp = special_for($d);
    if (!$sp) {
        return $base;
    }
    return $sp['kind'] === 'fixed'
        ? round((float)$sp['value'], 2)
        : max(0, round($base * (1 + (float)$sp['value'] / 100), 2));
}

function special_label(array $sp): string
{
    return $sp['kind'] === 'fixed'
        ? money(ttc((float)$sp['value'])) . ' la journée'
        : ((float)$sp['value'] >= 0 ? '+' : '−') . rating_label((string)abs((float)$sp['value'])) . ' %';
}

/** Dates spéciales à venir (pour l'accueil et le calendrier). */
function specials_upcoming(int $limit = 5): array
{
    $st = db()->prepare('SELECT * FROM special_prices WHERE active = 1 AND date_to >= ? ORDER BY date_from LIMIT ' . (int)$limit);
    $st->execute([bk_now()->format('Y-m-d')]);
    return $st->fetchAll();
}

function provider_label(string $p): string
{
    return ['paypal' => 'PayPal', 'stripe' => 'carte bancaire', 'giftcard' => 'bon cadeau'][$p] ?? $p;
}

/* ---------- Codes promo et bons cadeaux ---------- */

const PROMO_KINDS = ['percent' => 'Remise en %', 'amount' => 'Remise en € TTC', 'gift' => 'Bon cadeau (montant prépayé)'];

function promo_normalize(string $code): string
{
    return strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', $code));
}

function promo_find(string $code): ?array
{
    $code = promo_normalize($code);
    if ($code === '') {
        return null;
    }
    $st = db()->prepare('SELECT * FROM promo_codes WHERE code = ?');
    $st->execute([$code]);
    return $st->fetch() ?: null;
}

/** Code aléatoire lisible (sans 0/O/1/I), ex. RN6-K7QX-M2PA (≈ 40 bits). */
function promo_generate(string $prefix = 'RN6'): string
{
    $alpha = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $c = $prefix . '-';
        for ($i = 0; $i < 8; $i++) {
            $c .= $alpha[random_int(0, strlen($alpha) - 1)] . ($i === 3 ? '-' : '');
        }
    } while (promo_find($c));
    return $c;
}

/** Utilisations d'un code : réservations payées + paiements en cours. */
function promo_uses(string $code, ?int $ignoreBooking = null): int
{
    $st = db()->prepare("SELECT COUNT(*) FROM bookings WHERE (promo_code = ? OR gift_code = ?) AND id != ?
        AND (status = 'confirmed' OR (status = 'pending' AND hold_until > ?))");
    $st->execute([$code, $code, (int)$ignoreBooking, bk_now()->format(BK_FMT)]);
    return (int)$st->fetchColumn();
}

/** Solde disponible d'un bon cadeau (moins les montants réservés par les paiements en cours). */
function gift_available(array $p, ?int $ignoreBooking = null): float
{
    $st = db()->prepare("SELECT COALESCE(SUM(gift_amount), 0) FROM bookings WHERE gift_code = ? AND status = 'pending' AND hold_until > ? AND id != ?");
    $st->execute([$p['code'], bk_now()->format(BK_FMT), (int)$ignoreBooking]);
    return max(0, round((float)$p['balance'] - (float)$st->fetchColumn(), 2));
}

/** Limite anti-devinette des codes : 10 codes invalides par IP sur 15 minutes. */
function promo_guess_blocked(): bool
{
    return account_blocked('promo:' . ip_fingerprint());
}

function promo_guess_failed(): void
{
    db()->prepare('INSERT INTO login_attempts(ip, at) VALUES(?, ?)')->execute(['promo:' . ip_fingerprint(), time()]);
}

/**
 * Vérifie un code pour un devis. Retourne [code (ligne) ou null, message d'erreur ou null].
 * $totalTtc : montant TTC avant ce code (pour le minimum de commande).
 */
function promo_check(string $raw, float $totalTtc, ?int $ignoreBooking = null): array
{
    if (promo_guess_blocked()) {
        return [null, 'Trop de codes essayés. Réessayez dans 15 minutes.'];
    }
    $p = promo_find($raw);
    $today = bk_now()->format('Y-m-d');
    $err = null;
    if (!$p || !(int)$p['active']) {
        $err = 'Ce code n’existe pas ou n’est plus actif.';
    } elseif ($p['valid_from'] !== '' && $today < $p['valid_from']) {
        $err = 'Ce code n’est valable qu’à partir du ' . bk_dt($p['valid_from'])->format('d/m/Y') . '.';
    } elseif ($p['valid_to'] !== '' && $today > $p['valid_to']) {
        $err = 'Ce code a expiré le ' . bk_dt($p['valid_to'])->format('d/m/Y') . '.';
    } elseif ($p['kind'] !== 'gift' && (int)$p['max_uses'] > 0 && promo_uses($p['code'], $ignoreBooking) >= (int)$p['max_uses']) {
        $err = 'Ce code a déjà été utilisé le nombre de fois prévu.';
    } elseif ($p['kind'] !== 'gift' && (float)$p['min_total'] > 0 && $totalTtc < (float)$p['min_total']) {
        $err = 'Ce code est valable à partir de ' . money((float)$p['min_total']) . ' TTC de réservation.';
    } elseif ($p['kind'] === 'gift' && gift_available($p, $ignoreBooking) <= 0) {
        $err = 'Ce bon cadeau a déjà été entièrement utilisé.';
    }
    if ($err && (!$p || !(int)$p['active'])) {
        promo_guess_failed();
    }
    return [$err ? null : $p, $err];
}

/** Carte « bon cadeau » (HTML avec styles en ligne : page imprimable et e-mail). */
function gift_card_html(array $g, bool $forEmail = false): string
{
    $accent = mail_style('mail_color_accent');
    return '<div style="border:2px dashed ' . e($accent) . ';border-radius:16px;padding:26px;text-align:center;background:#fffdf6;font-family:Arial,Helvetica,sans-serif">'
        . '<div style="font-size:13px;letter-spacing:3px;text-transform:uppercase;color:#8f6818">Bon cadeau</div>'
        . '<div style="font-family:Georgia,serif;font-size:26px;color:#071c35;margin:6px 0">' . e(s('site_name')) . '</div>'
        . '<div style="font-size:44px;font-weight:bold;color:#071c35;margin:10px 0">' . money((float)$g['value']) . '</div>'
        . ($g['label'] !== '' ? '<div style="color:#425166;margin-bottom:10px">' . e($g['label']) . '</div>' : '')
        . '<div style="font-size:13px;color:#617083">Code à saisir lors de la réservation en ligne</div>'
        . '<div style="font-family:monospace;font-size:24px;font-weight:bold;letter-spacing:2px;background:#fff;border:1px solid #e3e7ec;border-radius:10px;display:inline-block;padding:10px 18px;margin:8px 0">' . e($g['code']) . '</div>'
        . ($g['valid_to'] !== '' ? '<div style="font-size:13px;color:#617083">Valable jusqu’au ' . e(bk_dt($g['valid_to'])->format('d/m/Y')) . ', utilisable en une ou plusieurs fois.</div>' : '')
        . '<div style="font-size:13px;color:#617083;margin-top:10px">Réservez sur ' . e(bk_url('reservation.php')) . '</div>'
        . '</div>'
        . ($forEmail ? '<p style="text-align:center;margin-top:18px">' . mail_button(bk_url('reservation.php'), 'Réserver la salle') . '</p>' : '');
}
