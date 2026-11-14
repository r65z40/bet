<?php
/** Nouvelle page d'accueil : photos par numéro, prochaines dates libres, formulaire de contact. */

/** Photo n° $n telle que numérotée dans l'admin (page Photos, « #n »), si elle est visible. */
function home_photo(int $n): ?array
{
    static $all = null;
    $all ??= photos(true);
    $p = $all[$n - 1] ?? null;
    return $p && (int)$p['visible'] ? $p : null;
}

/**
 * Photo désignée dans les réglages : « p12 » = photo d'identifiant 12 (choisie avec le sélecteur de l'admin,
 * ne bouge pas si l'ordre de la galerie change), ou ancien format « 12 » = 12e photo de la galerie.
 */
function home_photo_ref(string $ref): ?array
{
    static $byId = null;
    $ref = trim($ref);
    if (preg_match('/^p(\d+)$/', $ref, $m)) {
        $byId ??= array_column(photos(true), null, 'id');
        $p = $byId[(int)$m[1]] ?? null;
        return $p && (int)$p['visible'] ? $p : null;
    }
    return ctype_digit($ref) ? home_photo((int)$ref) : null;
}

/** Liste de photos (« p12,p15 ») sans doublon. */
function home_photo_refs(string $csv): array
{
    $out = [];
    foreach (explode(',', $csv) as $r) {
        if (($p = home_photo_ref($r)) && !isset($out[$p['id']])) {
            $out[$p['id']] = $p;
        }
    }
    return array_values($out);
}

/**
 * Emplacements photo de la page d'accueil, réglables avec le sélecteur de l'admin (page Photos) :
 * clé => [libellé, plusieurs photos ?, maximum, valeur actuelle, aide].
 */
function home_photo_slots(): array
{
    $slots = [
        'carousel' => ['Carrousel « La salle » (à côté du titre)', true, 12, s('salle_carousel_photos'), 'Si aucune n’est choisie : les 8 premières photos de la galerie.'],
        'band1' => ['Bandeau photo 1', false, 1, s('band1_photo'), s('band1_text')],
        'band2' => ['Bandeau photo 2', false, 1, s('band2_photo'), s('band2_text')],
    ];
    foreach (items('evenements_cartes', true) as $it) {
        $slots['ev' . $it['id']] = ['Carte « ' . $it['title'] . ' »', false, 1, (string)$it['value'], 'Section « Pour quel événement ? »'];
    }
    foreach (['salle' => 'salle', 'cuisine' => 'cuisine', 'bar' => 'bar', 'audio' => 'audiovisuel'] as $k => $cat) {
        $slots['tab_' . $k] = ['Onglet « ' . s('eqtab_' . $k . '_title') . ' »', true, 6, s('eqtab_' . $k . '_photos'),
            'Si aucune n’est choisie : les photos rangées dans l’espace « ' . (PHOTO_CATEGORIES[$cat] ?? $cat) . ' ».'];
    }
    return $slots;
}

/** Enregistre un emplacement (valeur « p12,p15 », déjà vérifiée). */
function home_photo_slot_save(string $key, string $value): void
{
    if ($key === 'carousel') {
        save_setting('salle_carousel_photos', $value);
    } elseif ($key === 'band1' || $key === 'band2') {
        save_setting($key . '_photo', $value);
    } elseif (preg_match('/^ev(\d+)$/', $key, $m)) {
        db()->prepare("UPDATE items SET value = ? WHERE id = ? AND grp = 'evenements_cartes'")->execute([$value, (int)$m[1]]);
    } elseif (preg_match('/^tab_(salle|cuisine|bar|audio)$/', $key, $m)) {
        save_setting('eqtab_' . $m[1] . '_photos', $value);
    }
}

/** Balise <picture> d'une photo (WebP si disponible). $size : 'large' (1920 px) ou 'thumb' (800 px). */
function home_picture(array $p, string $size = 'large', string $attrs = 'loading="lazy" decoding="async"'): string
{
    $webp = variant_src($p['filename'], $size);
    return '<picture>' . ($webp ? '<source type="image/webp" srcset="' . e($webp) . '">' : '')
        . '<img src="' . e(photo_src($p['filename'])) . '" alt="' . e($p['alt']) . '" width="' . (int)$p['width'] . '" height="' . (int)$p['height'] . '" ' . $attrs . '></picture>';
}

/** URL de la photo (WebP 1920 px si disponible) pour un fond CSS. */
function home_photo_url(array $p): string
{
    return variant_src($p['filename'], 'large') ?: photo_src($p['filename']);
}

/**
 * Prochaines dates libres pour une location à la journée (24 h), parmi les jours choisis dans
 * l'admin (samedis par défaut), sur les 6 prochains mois. Lecture seule : même règle que le calendrier.
 */
function home_next_free(int $n = 4): array
{
    $days = array_map('intval', array_filter(explode(',', s('dates_weekdays') ?: '6')));
    $now = bk_now();
    $minStart = $now->modify('+' . (int)bk_num('bk_min_notice_hours', 48) . ' hours');
    $until = $now->modify('+' . min(6, max(1, (int)bk_num('bk_max_months', 18))) . ' months');
    try {
        $dayBusy = bk_busy_for_days(bk_busy($now->setTime(0, 0), $until->modify('+3 days')));
    } catch (Throwable) {
        return [];
    }
    $out = [];
    for ($d = $now->setTime(0, 0); $d <= $until && count($out) < $n; $d = $d->modify('+1 day')) {
        if (!in_array((int)$d->format('N'), $days, true)) {
            continue;
        }
        [$s, $e] = bk_day_slot($d);
        if ($s < $minStart || bk_conflict_day($s->getTimestamp(), $e->getTimestamp(), $dayBusy)) {
            continue;
        }
        $out[] = ['date' => $d, 'price' => ttc(bk_day_price_for($d)), 'special' => special_for($d)['label'] ?? null];
    }
    return $out;
}

/** « samedi 12 décembre » */
function fr_date_long(DateTimeImmutable $d, bool $withDay = true): string
{
    static $j = ['', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
    static $m = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    return ($withDay ? $j[(int)$d->format('N')] . ' ' : '') . (int)$d->format('j') . ' ' . $m[(int)$d->format('n')];
}

/** Numéro de téléphone pour un lien tel: / WhatsApp (format international pour WhatsApp). */
function home_phone_digits(string $phone, bool $intl = false): string
{
    $d = preg_replace('/[^\d+]/', '', $phone);
    if ($intl) {
        $d = ltrim($d, '+');
        if (str_starts_with($d, '00')) {
            $d = substr($d, 2);
        } elseif (str_starts_with($d, '0')) {
            $d = '33' . substr($d, 1);
        }
    }
    return $d;
}

/** Message du formulaire de contact : contrôles, anti-robot, envoi. Retourne une erreur ou null. */
function contact_send(array $in): ?string
{
    $f = [];
    foreach (['name' => 80, 'email' => 160, 'phone' => 30, 'date' => 40, 'guests' => 10, 'message' => 3000] as $k => $max) {
        $f[$k] = trim(mb_substr(str_replace(["\r", "\0"], '', (string)($in[$k] ?? '')), 0, $max));
    }
    // Robots : champ caché rempli, ou formulaire envoyé moins de 3 s après l'affichage de la page
    if (!empty($in['website']) || (int)($in['t'] ?? 0) > time() - 3) {
        return null; // on fait comme si tout s'était bien passé
    }
    if ($f['name'] === '' || $f['message'] === '') {
        return 'Indiquez votre nom et votre message.';
    }
    if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) {
        return 'Adresse e-mail non valide.';
    }
    if (preg_match_all('#https?://#i', $f['message']) > 2) {
        return 'Merci de retirer les liens de votre message.';
    }
    $ip = 'contact:' . ($_SERVER['REMOTE_ADDR'] ?? '');
    $st = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND at > ?');
    $st->execute([$ip, time() - 900]);
    if ((int)$st->fetchColumn() >= 4) {
        return 'Plusieurs messages viennent d’être envoyés : réessayez dans quelques minutes, ou écrivez-nous à ' . s('contact_email') . '.';
    }
    db()->prepare('INSERT INTO login_attempts(ip, at) VALUES(?, ?)')->execute([$ip, time()]);

    $rows = [['Nom', $f['name']], ['E-mail', $f['email']], ['Téléphone', $f['phone']], ['Date souhaitée', $f['date']], ['Nombre d’invités', $f['guests']]];
    $html = '<table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:0 0 16px">';
    foreach ($rows as [$l, $v]) {
        if ($v !== '') {
            $html .= '<tr><td style="padding:4px 14px 4px 0;color:#617083">' . e($l) . '</td><td style="padding:4px 0"><strong>' . e($v) . '</strong></td></tr>';
        }
    }
    $html .= '</table><div style="background:#f4f6f8;border-radius:8px;padding:14px 16px;line-height:1.55">' . nl2br(e($f['message']), false) . '</div>'
        . '<p style="margin:16px 0 0;color:#617083;font-size:13px">Répondez simplement à cet e-mail : la réponse partira à ' . e($f['email']) . '.</p>';
    $to = notify_emails() ?: (filter_var(s('contact_email'), FILTER_VALIDATE_EMAIL) ? [s('contact_email')] : []);
    if (!$to) {
        return 'Le formulaire n’est pas encore configuré : écrivez-nous directement par e-mail.';
    }
    $ok = false;
    foreach ($to as $addr) {
        $ok = send_mail($addr, 'Message de ' . $f['name'] . ' — ' . s('site_name'), mail_layout('Nouveau message depuis le site', $html), $f['email']) || $ok;
    }
    if (!$ok) {
        return 'L’envoi a échoué : écrivez-nous directement à ' . s('contact_email') . '.';
    }
    admin_log('Message reçu par le formulaire de contact : ' . $f['name'] . ' <' . $f['email'] . '>');
    if (notif_on('contact', 'push')) {
        after_response(fn() => push_notify('reservations', 'Message de ' . $f['name'], mb_substr($f['message'], 0, 140), 'index.php', 'contact', 'contact'), 'alerte contact');
    }
    return null;
}
