<?php
/**
 * Envoi d'e-mails : client SMTP classique (STARTTLS / SSL, AUTH LOGIN ou PLAIN) compatible
 * Gmail, OVH et tout serveur SMTP standard, ou fonction mail() de l'hébergeur.
 */

function mail_clean(string $s): string
{
    return trim(str_replace(["\r", "\n"], ' ', $s));
}

function mail_header_encode(string $s): string
{
    return preg_match('/[^\x20-\x7E]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
}

/** Dernière erreur d'envoi (pour l'envoi en cours). */
function mail_last_error(?string $set = null): string
{
    static $err = '';
    if ($set !== null) {
        $err = $set;
    }
    return $err;
}

function mail_from_address(): string
{
    $from = mail_clean(s('bk_mail_from_addr'));
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
        $from = mail_clean(s('bk_smtp_user'));
    }
    return filter_var($from, FILTER_VALIDATE_EMAIL) ? $from : mail_clean(s('contact_email'));
}

/**
 * Envoie un e-mail HTML + texte. Retourne true si accepté par le serveur.
 * $extraHeaders : en-têtes supplémentaires (ex. List-Unsubscribe), déjà nettoyés.
 */
function send_mail(string $to, string $subject, string $html, ?string $replyTo = null, array $extraHeaders = []): bool
{
    $to = mail_clean($to);
    mail_last_error('');
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        mail_last_error('adresse invalide');
        return false;
    }
    $fromAddr = mail_from_address();
    $fromName = mail_clean(s('bk_mail_from')) ?: s('site_name');
    $text = html_to_text($html);
    $boundary = 'b' . bin2hex(random_bytes(8));
    $domain = substr(strrchr($fromAddr, '@') ?: '@localhost', 1);

    $headers = [
        'Date: ' . date('r'),
        'From: ' . mail_header_encode($fromName) . ' <' . $fromAddr . '>',
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $domain . '>',
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    ];
    if ($replyTo && filter_var(mail_clean($replyTo), FILTER_VALIDATE_EMAIL)) {
        $headers[] = 'Reply-To: ' . mail_clean($replyTo);
    }
    foreach ($extraHeaders as $h) {
        $headers[] = mail_clean($h);
    }
    $body = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($text))
        . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($html))
        . "--$boundary--\r\n";
    $subjectEnc = mail_header_encode(mail_clean($subject));

    try {
        if (s('bk_mail_method') === 'php' || s('bk_smtp_host') === '') {
            $ok = mail($to, $subjectEnc, $body, implode("\r\n", $headers), '-f' . $fromAddr);
            if (!$ok) {
                throw new RuntimeException('la fonction mail() du serveur a refusé l’envoi');
            }
        } else {
            smtp_send($fromAddr, $to, array_merge($headers, ['To: ' . $to, 'Subject: ' . $subjectEnc]), $body);
        }
        if (s('_mail_last_error') !== '') {
            save_setting('_mail_last_error', '');
        }
        return true;
    } catch (Throwable $e) {
        mail_last_error($e->getMessage());
        save_setting('_mail_last_error', date('d/m/Y H:i') . ' — envoi à ' . $to . ' : ' . $e->getMessage());
        return false;
    }
}

function smtp_send(string $from, string $to, array $headers, string $body): void
{
    $host = s('bk_smtp_host');
    $port = (int)(s('bk_smtp_port') ?: 587);
    $secure = s('bk_smtp_secure') ?: 'tls';
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host]]);
    $fp = @stream_socket_client(($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        throw new RuntimeException("connexion à $host:$port impossible ($errstr)");
    }
    stream_set_timeout($fp, 20);
    $read = function () use ($fp): array {
        $data = '';
        while (($line = fgets($fp, 1024)) !== false) {
            $data .= $line;
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }
        return [(int)substr($data, 0, 3), trim($data)];
    };
    $cmd = function (string $c, array $expect) use ($fp, $read): string {
        fwrite($fp, $c . "\r\n");
        [$code, $resp] = $read();
        if (!in_array($code, $expect, true)) {
            $shown = str_starts_with($c, 'AUTH') || strlen($c) > 60 || preg_match('/^[A-Za-z0-9+\/=]{8,}$/', $c) ? strtok($c, ' ') : $c;
            if ($code === 535 || $code === 534) {
                throw new RuntimeException('identifiant ou mot de passe SMTP refusé par le serveur (' . $resp . ')');
            }
            throw new RuntimeException("le serveur a répondu « $resp » à $shown");
        }
        return $resp;
    };
    try {
        [$code, $resp] = $read();
        if ($code !== 220) {
            throw new RuntimeException("accueil inattendu : $resp");
        }
        $ehlo = 'EHLO ' . (preg_replace('/[^A-Za-z0-9.-]/', '', (string)gethostname()) ?: 'localhost');
        $caps = $cmd($ehlo, [250]);
        if ($secure === 'tls') {
            $cmd('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('échec du chiffrement TLS');
            }
            $caps = $cmd($ehlo, [250]);
        }
        $user = s('bk_smtp_user');
        if ($user !== '') {
            // Méthode d'identification annoncée par le serveur : LOGIN, sinon PLAIN
            if (preg_match('/AUTH[ =][^\n]*\bLOGIN\b/i', $caps) || !preg_match('/AUTH[ =][^\n]*\bPLAIN\b/i', $caps)) {
                $cmd('AUTH LOGIN', [334]);
                $cmd(base64_encode($user), [334]);
                $cmd(base64_encode(s('bk_smtp_pass')), [235]);
            } else {
                $cmd('AUTH PLAIN ' . base64_encode("\0" . $user . "\0" . s('bk_smtp_pass')), [235]);
            }
        }
        $cmd('MAIL FROM:<' . $from . '>', [250]);
        $cmd('RCPT TO:<' . $to . '>', [250, 251]);
        $cmd('DATA', [354]);
        $msg = implode("\r\n", $headers) . "\r\n\r\n" . $body;
        $msg = preg_replace('/^\./m', '..', str_replace("\r\n", "\n", $msg)); // dot-stuffing
        $cmd(str_replace("\n", "\r\n", $msg) . "\r\n.", [250]);
        fwrite($fp, "QUIT\r\n");
    } finally {
        fclose($fp);
    }
}

function html_to_text(string $html): string
{
    $t = preg_replace('#<a [^>]*href="([^"]+)"[^>]*>(.*?)</a>#is', '$2 ($1)', $html);
    $t = preg_replace('#<(br|/p|/tr|/h\d|/li|/div)[^>]*>#i', "\n", $t);
    $t = html_entity_decode(strip_tags($t), ENT_QUOTES, 'UTF-8');
    return trim(preg_replace("/\n\s*\n\s*\n+/", "\n\n", preg_replace('/[ \t]+/', ' ', $t)));
}

/* ---------- Modèles ---------- */

function mail_layout(string $title, string $content): string
{
    $name = e(s('site_name'));
    $head = mail_style('mail_color_header');
    $headText = mail_contrast($head);
    $logo = mail_style('mail_show_logo') === '1' && is_file(ROOT . '/logo-rn6.png')
        ? '<img src="' . e(bk_url('logo-rn6.png')) . '" width="44" height="44" alt="" style="vertical-align:middle;border-radius:50%;background:#fff;margin-right:12px">' : '';
    $footer = nl2br(e(strtr(mail_style('mail_footer'), mail_common_vars())), false);
    return '<!doctype html><html lang="fr"><head><meta name="viewport" content="width=device-width, initial-scale=1"></head><body style="margin:0;background:#f4f6f8;font-family:Arial,Helvetica,sans-serif;color:#152131">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f8;padding:24px 8px"><tr><td align="center">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#fff;border-radius:14px;overflow:hidden">'
        . '<tr><td style="background:' . $head . ';padding:20px 26px;color:' . $headText . ';font-size:20px;font-weight:bold">' . $logo . '<span style="vertical-align:middle">' . $name
        . '<span style="display:block;font-size:12px;opacity:.8;letter-spacing:2px;text-transform:uppercase;margin-top:3px;font-weight:normal">' . e(s('site_location')) . '</span></span></td></tr>'
        . '<tr><td style="padding:26px;font-size:15px">'
        . '<h1 style="font-family:Georgia,serif;color:#071c35;font-size:23px;margin:0 0 16px">' . e($title) . '</h1>'
        . $content
        . '</td></tr><tr><td style="background:#fbf8f1;padding:16px 26px;font-size:12px;color:#617083">' . $footer . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}

function mail_button(string $url, string $label, bool $primary = true): string
{
    $accent = mail_style('mail_color_accent');
    return '<a href="' . e($url) . '" style="display:inline-block;margin:6px 6px 6px 0;padding:12px 18px;border-radius:8px;text-decoration:none;font-weight:bold;'
        . ($primary ? 'background:' . $accent . ';color:' . mail_contrast($accent) : 'background:#eef2f6;color:#071c35') . '">' . e($label) . '</a>';
}

function mail_recap(array $b): string
{
    $rows = '';
    foreach (json_decode($b['lines_json'], true) ?: [] as $l) {
        $rows .= '<tr><td style="padding:8px 0;border-bottom:1px solid #eee">' . e($l['label']) . '</td><td align="right" style="padding:8px 0;border-bottom:1px solid #eee;white-space:nowrap">' . money2(ttc((float)$l['total_ht'])) . '</td></tr>';
    }
    if ((float)$b['discount_ht'] > 0) {
        $rows .= '<tr><td style="padding:8px 0;border-bottom:1px solid #eee">Remise association</td><td align="right" style="padding:8px 0;border-bottom:1px solid #eee">− ' . money2(ttc((float)$b['discount_ht'])) . '</td></tr>';
    }
    if ((float)($b['promo_ht'] ?? 0) > 0) {
        $rows .= '<tr><td style="padding:8px 0;border-bottom:1px solid #eee">Code promo ' . e($b['promo_code']) . '</td><td align="right" style="padding:8px 0;border-bottom:1px solid #eee">− ' . money2(ttc((float)$b['promo_ht'])) . '</td></tr>';
    }
    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;margin:8px 0 18px">'
        . '<tr><td colspan="2" style="padding:10px 14px;background:#f4f6f8;border-radius:8px"><strong>' . e(bk_slot_label($b)) . '</strong><br>'
        . e($b['event_type']) . ' · ' . (int)$b['guests'] . ' personne(s) · Réf. ' . e($b['ref']) . '</td></tr>'
        . $rows
        . '<tr><td style="padding:10px 0 0;font-weight:bold">Total TTC</td><td align="right" style="padding:10px 0 0;font-weight:bold;font-size:16px">' . money2((float)$b['total_ttc']) . '</td></tr>'
        . '<tr><td colspan="2" style="font-size:12px;color:#617083">dont TVA ' . money2((float)$b['vat']) . ' — total HT ' . money2((float)$b['total_ht']) . '</td></tr>'
        . ((float)($b['gift_amount'] ?? 0) > 0 ? '<tr><td style="padding:8px 0 0">Réglé par bon cadeau ' . e($b['gift_code']) . '</td><td align="right" style="padding:8px 0 0">− ' . money2((float)$b['gift_amount']) . '</td></tr>'
            . '<tr><td style="font-weight:bold">Payé en ligne</td><td align="right" style="font-weight:bold">' . money2(bk_due($b)) . '</td></tr>' : '')
        . '</table>';
}

function bk_mail_confirmation(array $b): bool
{
    [$vars, $blocks] = mail_booking_data($b);
    [$subject, $html] = mail_from_template('confirmation', $vars, $blocks);
    $ok = send_mail($b['email'], $subject, $html, s('contact_email'));
    bk_log((int)$b['id'], $ok ? 'E-mail de confirmation envoyé à ' . $b['email'] : 'ÉCHEC de l’envoi de l’e-mail de confirmation (voir Réglages › E-mails)');
    return $ok;
}

function bk_mail_admin_new(array $b, ?string $warning = null): bool
{
    $to = s('bk_notify_email');
    if ($to === '') {
        return false;
    }
    [$vars, $blocks] = mail_booking_data($b);
    $blocks['attention'] = $warning ? '<p style="background:#fdecea;color:#b42318;padding:10px 14px;border-radius:8px"><strong>Attention :</strong> ' . e($warning) . '</p>' : '';
    $blocks['bouton'] = '<p>' . mail_button(bk_url('admin/reservation.php?id=' . (int)$b['id']), 'Voir dans l’administration') . '</p>';
    [$subject, $html] = mail_from_template('alerte', $vars, $blocks);
    return send_mail($to, $subject, $html, $b['email']);
}

function bk_mail_cancelled(array $b, float $refund): bool
{
    [$vars, $blocks] = mail_booking_data($b);
    $vars['montant_rembourse'] = money2($refund);
    $blocks['remboursement'] = '<p style="margin:0 0 14px">' . ($refund > 0
        ? 'Un remboursement de <strong>' . money2($refund) . '</strong> a été effectué sur votre moyen de paiement. Il apparaît sous 5 à 10 jours selon votre banque.'
        : 'Conformément aux conditions de location, aucun remboursement n’est effectué.') . '</p>';
    $blocks['bouton'] = '<p>' . mail_button(bk_client_url($b), 'Voir ma réservation') . '</p>';
    [$subject, $html] = mail_from_template('annulation', $vars, $blocks);
    $ok = send_mail($b['email'], $subject, $html, s('contact_email'));
    bk_log((int)$b['id'], $ok ? 'E-mail d’annulation envoyé' : 'ÉCHEC de l’envoi de l’e-mail d’annulation');
    return $ok;
}
