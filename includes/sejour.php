<?php
/**
 * Autour du séjour : liste d'attente sur les dates prises, attestation d'assurance déposée en ligne,
 * état des lieux sur tablette (photos + signatures).
 * Les fichiers sont rangés dans data/ (inaccessible depuis le web) et servis par des pages qui vérifient l'accès.
 */

/* ---------- Liste d'attente ---------- */

/** La date est-elle de nouveau réservable pour une location à la journée ? */
function waitlist_day_free(string $day): bool
{
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $day, bk_tz());
    if (!$d) {
        return false;
    }
    [$start, $end] = bk_day_slot($d, 1);
    return bk_slot_error('day', $start, $end) === null;
}

/**
 * Inscription sur une date déjà prise. Renvoie [code HTTP, réponse].
 * Anti-abus : 10 inscriptions par visiteur et par heure, 5 dates par adresse.
 */
function waitlist_add(string $email, string $firstName, string $day): array
{
    $email = strtolower(trim($email));
    $firstName = mb_substr(trim(preg_replace('/\s+/', ' ', $firstName)), 0, 60);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [422, ['error' => 'Indiquez une adresse e-mail valide.']];
    }
    if (preg_match('~https?:|www\.|@|<|>~i', $firstName)) {
        return [422, ['error' => 'Le prénom ne doit contenir ni lien ni adresse.']];
    }
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $day, bk_tz());
    if (!$d || $d->format('Y-m-d') !== $day || $d < bk_now()->setTime(0, 0) || $d > bk_now()->modify('+' . (int)bk_num('bk_max_months', 18) . ' months')) {
        return [422, ['error' => 'Choisissez une date à venir.']];
    }
    if (waitlist_day_free($day)) {
        return [200, ['free' => true, 'message' => 'Bonne nouvelle : cette date est libre, vous pouvez la réserver dès maintenant.']];
    }
    $st = db()->prepare('SELECT COUNT(*) FROM waitlist WHERE visitor = ? AND created_at > ?');
    $st->execute([ip_fingerprint(), bk_now()->modify('-1 hour')->format(BK_FMT)]);
    $per = db()->prepare('SELECT COUNT(*) FROM waitlist WHERE email = ? AND notified_at IS NULL AND day >= ?');
    $per->execute([$email, bk_now()->format('Y-m-d')]);
    if ((int)$st->fetchColumn() >= 10 || (int)$per->fetchColumn() >= 5) {
        return [429, ['error' => 'Vous suivez déjà plusieurs dates : réessayez plus tard ou contactez-nous.']];
    }
    db()->prepare('INSERT OR IGNORE INTO waitlist(email, first_name, day, visitor, created_at) VALUES(?,?,?,?,?)')
        ->execute([$email, $firstName, $day, ip_fingerprint(), bk_now()->format(BK_FMT)]);
    marketing_subscribe($email, $firstName, 'liste d’attente', false);
    return [200, ['ok' => true, 'message' => 'C’est noté : nous vous écrirons à ' . $email . ' si le ' . fr_date($d, false) . ' se libère.']];
}

/** Dates suivies qui se sont libérées : chaque inscrit est prévenu une fois. Dates passées effacées. */
function waitlist_cron(array &$errors): int
{
    db()->prepare('DELETE FROM waitlist WHERE day < ?')->execute([bk_now()->format('Y-m-d')]);
    $days = db()->query('SELECT DISTINCT day FROM waitlist WHERE notified_at IS NULL ORDER BY day')->fetchAll(PDO::FETCH_COLUMN);
    $n = 0;
    foreach ($days as $day) {
        if (!waitlist_day_free($day)) {
            continue;
        }
        $st = db()->prepare('SELECT * FROM waitlist WHERE day = ? AND notified_at IS NULL');
        $st->execute([$day]);
        $rows = $st->fetchAll();
        $label = fr_date(DateTimeImmutable::createFromFormat('!Y-m-d', $day, bk_tz()), false);
        $sent = 0;
        foreach ($rows as $w) {
            db()->prepare('UPDATE waitlist SET notified_at = ? WHERE id = ?')->execute([bk_now()->format(BK_FMT), $w['id']]);
            if (!notif_on('waitlist', 'client')) {
                continue;
            }
            $url = mk_link(bk_url('reservation.php?date=' . $day), 'liste-attente');
            [$subject, $html] = mail_from_template('liste_attente', ['prenom' => $w['first_name'] !== '' ? $w['first_name'] : 'à vous', 'date' => $label, 'lien' => $url],
                ['bouton' => '<p style="margin:18px 0">' . mail_button($url, 'Réserver le ' . $label) . '</p>']);
            if (send_mail($w['email'], $subject, $html, mail_reply_to())) {
                $sent++;
            } else {
                $errors[] = 'liste d’attente ' . $w['email'] . ' : ' . mail_last_error();
            }
        }
        if ($sent > 0) {
            $n += $sent;
            admin_notify('waitlist', 'Date libérée : ' . $label, [$sent . ' personne(s) en liste d’attente prévenue(s) par e-mail.'], 'marketing.php#attente');
        }
    }
    return $n;
}

/* ---------- Attestation d'assurance ---------- */

const INSURANCE_TYPES = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/heic' => 'heic', 'image/heif' => 'heic'];

function insurance_dir(): string
{
    $d = DATA_DIR . '/assurances';
    if (!is_dir($d)) {
        @mkdir($d, 0750, true);
        @file_put_contents($d . '/index.html', '');
    }
    return $d;
}

/** Dépôt en ligne proposé (réglage) ? */
function insurance_online(): bool
{
    return s('bk_insurance_online') !== '0';
}

/** Attestation reçue (déposée en ligne ou notée reçue sur papier) ? */
function insurance_ok(array $b): bool
{
    return $b['insurance_file'] !== '' || (int)$b['insurance_paper'] === 1;
}

/** Chemin du fichier déposé, ou null. */
function insurance_path(array $b): ?string
{
    $f = basename((string)$b['insurance_file']);
    return $f !== '' && is_file(insurance_dir() . '/' . $f) ? insurance_dir() . '/' . $f : null;
}

/**
 * Enregistre le fichier envoyé (PDF ou photo, 10 Mo au plus). Les photos sont réenregistrées
 * (métadonnées et localisation retirées). Renvoie null ou un message d'erreur.
 */
function insurance_store(array $b, array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        return ($file['error'] ?? 0) === UPLOAD_ERR_INI_SIZE ? 'Fichier trop lourd (10 Mo au plus).' : 'Choisissez le fichier de votre attestation.';
    }
    if ($file['size'] > 10 * 1024 * 1024) {
        return 'Fichier trop lourd (10 Mo au plus).';
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset(INSURANCE_TYPES[$mime])) {
        return 'Format non accepté : envoyez un PDF ou une photo (JPEG, PNG).';
    }
    if ($mime === 'application/pdf' && file_get_contents($file['tmp_name'], false, null, 0, 5) !== '%PDF-') {
        return 'Ce PDF semble endommagé.';
    }
    $name = (int)$b['id'] . '-' . bin2hex(random_bytes(8));
    $dest = insurance_dir() . '/' . $name;
    if (in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        if (!image_reencode($file['tmp_name'], $dest . '.jpg', 2000)) {
            return 'Cette image n’a pas pu être lue : essayez une autre photo ou un PDF.';
        }
        $name .= '.jpg';
    } else {
        $name .= '.' . INSURANCE_TYPES[$mime];
        if (!move_uploaded_file($file['tmp_name'], insurance_dir() . '/' . $name)) {
            return 'Le fichier n’a pas pu être enregistré. Réessayez.';
        }
    }
    if ($old = insurance_path($b)) {
        @unlink($old);
    }
    db()->prepare('UPDATE bookings SET insurance_file = ?, insurance_at = ? WHERE id = ?')->execute([$name, bk_now()->format(BK_FMT), $b['id']]);
    bk_log((int)$b['id'], 'Attestation d’assurance déposée en ligne par le client');
    admin_notify('insurance', 'Attestation d’assurance déposée : ' . $b['first_name'] . ' ' . $b['last_name'], [notif_booking_line($b)], 'reservation.php?id=' . (int)$b['id'] . '#assurance');
    return null;
}

/** Image réenregistrée en JPEG (côté maximum $max px) : métadonnées retirées, contenu caché neutralisé. */
function image_reencode(string $src, string $dest, int $max): bool
{
    $info = @getimagesize($src);
    if (!$info || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > 60_000_000) {
        return false;
    }
    $img = @imagecreatefromstring((string)file_get_contents($src));
    if (!$img) {
        return false;
    }
    // Orientation des photos de téléphone
    if (($info[2] ?? 0) === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $o = (int)(@exif_read_data($src)['Orientation'] ?? 1);
        $img = match ($o) { 3 => imagerotate($img, 180, 0), 6 => imagerotate($img, -90, 0), 8 => imagerotate($img, 90, 0), default => $img };
    }
    $w = imagesx($img);
    $h = imagesy($img);
    $k = min(1, $max / max($w, $h));
    $out = imagecreatetruecolor(max(1, (int)round($w * $k)), max(1, (int)round($h * $k)));
    imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
    imagecopyresampled($out, $img, 0, 0, 0, 0, imagesx($out), imagesy($out), $w, $h);
    return imagejpeg($out, $dest, 82);
}

/** Rappel au client et alerte aux propriétaires quand l'attestation manque à J-N. */
function insurance_cron(array &$errors): int
{
    if (!insurance_online()) {
        return 0;
    }
    $days = max(1, (int)bk_num('bk_insurance_days', 10));
    $st = db()->prepare("SELECT * FROM bookings WHERE status = 'confirmed' AND insurance_file = '' AND insurance_paper = 0 AND insurance_reminded_at IS NULL AND start_at > ? AND start_at <= ?");
    $st->execute([bk_now()->format(BK_FMT), bk_now()->modify("+$days days")->format(BK_FMT)]);
    $n = 0;
    foreach ($st->fetchAll() as $b) {
        db()->prepare('UPDATE bookings SET insurance_reminded_at = ? WHERE id = ?')->execute([bk_now()->format(BK_FMT), $b['id']]);
        if (notif_on('insurance', 'client')) {
            [$vars, $blocks] = mail_booking_data($b);
            $blocks['bouton'] = '<p style="margin:18px 0">' . mail_button(bk_client_url($b) . '#assurance', 'Déposer mon attestation') . '</p>';
            [$subject, $html] = mail_from_template('assurance_rappel', $vars, $blocks);
            $ok = send_mail($b['email'], $subject, $html, mail_reply_to());
            bk_log((int)$b['id'], $ok ? 'Rappel « attestation d’assurance » envoyé au client' : 'Rappel « attestation d’assurance » : ÉCHEC de l’envoi (' . mail_last_error() . ')');
            if ($ok) {
                $n++;
            } else {
                $errors[] = 'assurance ' . $b['ref'] . ' : ' . mail_last_error();
            }
        }
        admin_notify('insurance', '⚠ Attestation d’assurance manquante : ' . $b['first_name'] . ' ' . $b['last_name'], [notif_booking_line($b), 'Le client vient de recevoir un rappel.'], 'reservation.php?id=' . (int)$b['id'] . '#assurance');
    }
    return $n;
}

/* ---------- État des lieux ---------- */

const EDL_KINDS = ['entree' => 'd’entrée', 'sortie' => 'de sortie'];
const EDL_STATES = ['bon' => 'Bon état', 'usure' => 'Usure normale', 'degat' => 'À signaler'];

function edl_enabled(): bool
{
    return s('bk_edl_enabled') !== '0';
}

/** Espaces à contrôler (réglage), au plus 20. */
function edl_rooms(): array
{
    return array_slice(array_values(array_filter(array_map(fn($l) => mb_substr(trim($l), 0, 60), lines(s('bk_edl_rooms'))))), 0, 20) ?: ['Salle'];
}

function edl_dir(int $bookingId): string
{
    $d = DATA_DIR . '/etats-des-lieux/' . $bookingId;
    if (!is_dir($d)) {
        @mkdir($d, 0750, true);
    }
    return $d;
}

function edl_get(int $bookingId, string $kind): ?array
{
    $st = db()->prepare('SELECT * FROM inspections WHERE booking_id = ? AND kind = ?');
    $st->execute([$bookingId, $kind]);
    $r = $st->fetch();
    return $r ?: null;
}

/** Lien du document (client : jeton de la réservation). */
function edl_url(array $b, string $kind): string
{
    return bk_client_url($b, 'etat-des-lieux.php') . '&k=' . $kind;
}

/** Nom de photo sûr (fichier existant de cette réservation), ou null. */
function edl_photo_path(int $bookingId, string $name): ?string
{
    if (!preg_match('/^(entree|sortie)-[a-f0-9]{16}\.jpg$/', $name)) {
        return null;
    }
    $p = edl_dir($bookingId) . '/' . $name;
    return is_file($p) ? $p : null;
}

/** Signature dessinée (image PNG en data URL), vérifiée et réduite à sa forme sûre. */
function edl_signature(string $dataUrl): string
{
    if (!preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $m) || strlen($m[1]) > 400_000) {
        return '';
    }
    $png = base64_decode($m[1], true);
    $info = $png !== false ? @getimagesizefromstring($png) : false;
    return $info && $info[2] === IMAGETYPE_PNG && $info[0] <= 2000 && $info[1] <= 1000 ? 'data:image/png;base64,' . base64_encode($png) : '';
}

/** E-mail au client avec le lien vers l'état des lieux signé. */
function edl_mail(array $b, string $kind): bool
{
    if (!notif_on('edl', 'client')) {
        return false;
    }
    $e = edl_get((int)$b['id'], $kind);
    [$vars, $blocks] = mail_booking_data($b);
    $vars['moment'] = EDL_KINDS[$kind];
    $vars['signature'] = $e ? bk_dt($e['created_at'])->format('d/m/Y à H\hi') : '';
    $blocks['bouton'] = '<p style="margin:18px 0">' . mail_button(edl_url($b, $kind), 'Voir l’état des lieux') . '</p>';
    [$subject, $html] = mail_from_template('etat_des_lieux', $vars, $blocks);
    $ok = send_mail($b['email'], $subject, $html, mail_reply_to());
    bk_log((int)$b['id'], 'État des lieux ' . EDL_KINDS[$kind] . ($ok ? ' envoyé au client' : ' : ÉCHEC de l’envoi (' . mail_last_error() . ')'));
    return $ok;
}

/** Fichiers des séjours anciens effacés (attestations : 1 an, états des lieux : 3 ans après la fin). */
function sejour_purge(): void
{
    $old = db()->prepare("SELECT id, insurance_file FROM bookings WHERE insurance_file <> '' AND end_at < ?");
    $old->execute([bk_now()->modify('-1 year')->format(BK_FMT)]);
    foreach ($old->fetchAll() as $r) {
        @unlink(insurance_dir() . '/' . basename($r['insurance_file']));
        db()->prepare("UPDATE bookings SET insurance_file = '', insurance_paper = 1 WHERE id = ?")->execute([$r['id']]);
    }
    $old = db()->prepare('SELECT DISTINCT i.booking_id FROM inspections i JOIN bookings b ON b.id = i.booking_id WHERE b.end_at < ?');
    $old->execute([bk_now()->modify('-3 years')->format(BK_FMT)]);
    foreach ($old->fetchAll(PDO::FETCH_COLUMN) as $id) {
        foreach (glob(DATA_DIR . '/etats-des-lieux/' . (int)$id . '/*.jpg') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir(DATA_DIR . '/etats-des-lieux/' . (int)$id);
        db()->prepare('DELETE FROM inspections WHERE booking_id = ?')->execute([$id]);
    }
}
