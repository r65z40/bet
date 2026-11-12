<?php
/**
 * Expéditions d'e-mails depuis l'administration : un client, une adresse, ou des groupes
 * de clients. Envoi par lots (file d'attente) pour ne pas dépasser les limites du serveur SMTP,
 * lien de désinscription obligatoire pour les envois groupés (RGPD).
 */

const MAILING_AUDIENCES = [
    'un'     => 'Une adresse précise',
    'avenir' => 'Clients ayant une réservation à venir',
    'passes' => 'Clients dont la réservation est passée',
    'tous'   => 'Tous les clients ayant payé une réservation',
];

const MAILING_BATCH = 8;            // e-mails par lot
const MAILING_MAX_RECIPIENTS = 2000;

/**
 * Destinataires d'une audience : [email(minuscule) => ['email', 'first_name', 'last_name', 'booking' => ligne]].
 * Une seule entrée par adresse (la réservation la plus récente sert aux variables).
 */
function mailing_recipients(string $audience, string $single = ''): array
{
    $now = bk_now()->format(BK_FMT);
    if ($audience === 'un') {
        $single = mail_clean($single);
        if (!filter_var($single, FILTER_VALIDATE_EMAIL)) {
            return [];
        }
        $st = db()->prepare("SELECT * FROM bookings WHERE lower(email) = lower(?) AND paid_at IS NOT NULL ORDER BY start_at DESC LIMIT 1");
        $st->execute([$single]);
        $b = $st->fetch() ?: null;
        return [strtolower($single) => ['email' => $single, 'first_name' => $b['first_name'] ?? '', 'last_name' => $b['last_name'] ?? '', 'booking' => $b]];
    }
    [$where, $params] = match ($audience) {
        'avenir' => ["status = 'confirmed' AND end_at > ?", [$now]],
        'passes' => ["status = 'confirmed' AND end_at <= ?", [$now]],
        default  => ['paid_at IS NOT NULL', []],
    };
    $st = db()->prepare("SELECT * FROM bookings WHERE $where ORDER BY start_at DESC");
    $st->execute($params);
    $optout = array_flip(db()->query('SELECT email FROM mail_optout')->fetchAll(PDO::FETCH_COLUMN));
    $out = [];
    foreach ($st as $b) {
        $key = strtolower(trim($b['email']));
        if ($key === '' || isset($out[$key]) || isset($optout[$key]) || !filter_var($key, FILTER_VALIDATE_EMAIL)) {
            continue;
        }
        $out[$key] = ['email' => $b['email'], 'first_name' => $b['first_name'], 'last_name' => $b['last_name'], 'booking' => $b];
        if (count($out) >= MAILING_MAX_RECIPIENTS) {
            break;
        }
    }
    return $out;
}

/** Destinataire fictif pour les tests et aperçus (aucun client encore, ou adresse non saisie). */
function mailing_sample_recipient(): array
{
    return ['email' => 'client@exemple.fr', 'first_name' => 'Camille', 'last_name' => 'Martin', 'booking' => mail_sample_booking()];
}

/** Variables disponibles dans le sujet et le message. */
function mailing_vars(array $r): array
{
    $b = $r['booking'] ?? null;
    return [
        '{prenom}' => $r['first_name'] ?: '',
        '{nom}' => $r['last_name'] ?: '',
        '{reservation}' => $b ? bk_slot_label($b) : '',
        '{reference}' => $b['ref'] ?? '',
        '{lien}' => $b ? bk_client_url($b) : bk_url(''),
    ];
}

/** Message texte (saisi dans l'admin) → HTML sûr : échappement, **gras**, liens cliquables, retours à la ligne. */
function mailing_html(string $text, array $vars): string
{
    $html = rich(strtr($text, $vars));
    return preg_replace_callback('#https?://[^\s<]+#', fn($m) => '<a href="' . $m[0] . '">' . $m[0] . '</a>', $html);
}

function optout_key(): string
{
    $k = s('_optout_key');
    if ($k === '') {
        $k = bin2hex(random_bytes(16));
        save_setting('_optout_key', $k);
        settings(true);
    }
    return $k;
}

function optout_token(string $email): string
{
    return substr(hash_hmac('sha256', strtolower(trim($email)), optout_key()), 0, 32);
}

function optout_url(string $email): string
{
    return bk_url('desinscription.php?e=' . rawurlencode(strtolower(trim($email))) . '&k=' . optout_token($email));
}

function is_opted_out(string $email): bool
{
    $st = db()->prepare('SELECT 1 FROM mail_optout WHERE email = ?');
    $st->execute([strtolower(trim($email))]);
    return (bool)$st->fetchColumn();
}

/** Construit l'e-mail d'un destinataire et l'envoie. */
function mailing_send_one(array $campaign, array $recipient, bool $test = false): bool
{
    $vars = mailing_vars($recipient);
    $group = $campaign['audience'] !== 'un';
    $content = mailing_html($campaign['body'], $vars);
    $headers = [];
    if ($group) {
        $url = optout_url($recipient['email']);
        $content .= '<p style="margin-top:28px;font-size:12px;color:#617083">Vous recevez cet e-mail car vous avez réservé ' . e(s('site_name'))
            . '. <a href="' . e($url) . '" style="color:#617083">Ne plus recevoir ces e-mails</a>.</p>';
        $headers[] = 'List-Unsubscribe: <' . $url . '>';
        $headers[] = 'List-Unsubscribe-Post: List-Unsubscribe=One-Click';
    }
    $subject = ($test ? '[TEST] ' : '') . strtr($campaign['subject'], $vars);
    return send_mail($recipient['email'], $subject, mail_layout(strtr($campaign['subject'], $vars), $content), s('contact_email'), $headers);
}

/** Crée une expédition et sa file d'attente. Retourne l'identifiant. */
function mailing_create(string $audience, string $subject, string $body, array $recipients): int
{
    $pdo = db();
    $pdo->beginTransaction();
    $pdo->prepare("INSERT INTO mail_campaigns(subject, body, audience, created_at, total, status) VALUES(?,?,?,?,?, 'sending')")
        ->execute([$subject, $body, $audience, bk_now()->format(BK_FMT), count($recipients)]);
    $id = (int)$pdo->lastInsertId();
    $ins = $pdo->prepare("INSERT INTO mail_queue(campaign_id, email, first_name, last_name, booking_id, status) VALUES(?,?,?,?,?, 'pending')");
    foreach ($recipients as $r) {
        $ins->execute([$id, $r['email'], $r['first_name'], $r['last_name'], $r['booking']['id'] ?? null]);
    }
    $pdo->commit();
    return $id;
}

function mailing_get(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM mail_campaigns WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/**
 * Envoie le lot suivant d'une expédition. Un verrou empêche deux onglets d'envoyer
 * la même file en parallèle (pas de doublons).
 * Retourne ['sent' => n, 'failed' => n, 'remaining' => n].
 */
function mailing_process(int $id, int $max = MAILING_BATCH): array
{
    $lock = fopen(DATA_DIR . '/.mailing.lock', 'c');
    if (!flock($lock, LOCK_EX | LOCK_NB)) {
        fclose($lock);
        return mailing_counts($id); // un autre envoi est en cours
    }
    try {
        $c = mailing_get($id);
        if (!$c || $c['status'] !== 'sending') {
            return mailing_counts($id);
        }
        $st = db()->prepare("SELECT * FROM mail_queue WHERE campaign_id = ? AND status = 'pending' ORDER BY id LIMIT " . (int)$max);
        $st->execute([$id]);
        $upd = db()->prepare('UPDATE mail_queue SET status = ?, error = ?, sent_at = ? WHERE id = ?');
        $start = microtime(true);
        foreach ($st->fetchAll() as $q) {
            if ($c['audience'] !== 'un' && is_opted_out($q['email'])) {
                $upd->execute(['skipped', 'désinscrit', bk_now()->format(BK_FMT), $q['id']]);
                continue;
            }
            $b = $q['booking_id'] ? bk_get((int)$q['booking_id']) : null;
            $ok = mailing_send_one($c, ['email' => $q['email'], 'first_name' => $q['first_name'], 'last_name' => $q['last_name'], 'booking' => $b]);
            $upd->execute([$ok ? 'sent' : 'failed', mb_substr($ok ? mail_last_info() : mail_last_error(), 0, 300), bk_now()->format(BK_FMT), $q['id']]);
            if (microtime(true) - $start > 20) {
                break; // reste sous la limite de durée d'une page PHP
            }
        }
        $counts = mailing_counts($id);
        db()->prepare('UPDATE mail_campaigns SET sent = ?, failed = ?, status = ? WHERE id = ?')
            ->execute([$counts['sent'], $counts['failed'], $counts['remaining'] > 0 ? 'sending' : 'done', $id]);
        return $counts;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function mailing_counts(int $id): array
{
    $st = db()->prepare("SELECT status, COUNT(*) FROM mail_queue WHERE campaign_id = ? GROUP BY status");
    $st->execute([$id]);
    $c = $st->fetchAll(PDO::FETCH_KEY_PAIR);
    return ['sent' => (int)($c['sent'] ?? 0), 'failed' => (int)($c['failed'] ?? 0), 'skipped' => (int)($c['skipped'] ?? 0), 'remaining' => (int)($c['pending'] ?? 0)];
}
