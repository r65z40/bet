<?php
/**
 * Envoi d'e-mails : client SMTP minimal (STARTTLS / SSL, AUTH LOGIN) compatible Gmail,
 * avec repli sur mail() d'OVH si aucun mot de passe SMTP n'est configuré.
 */

function mail_clean(string $s): string
{
    return trim(str_replace(["\r", "\n"], ' ', $s));
}

function mail_header_encode(string $s): string
{
    return preg_match('/[^\x20-\x7E]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
}

/** Envoie un e-mail HTML + texte. Retourne true si accepté par le serveur. */
function send_mail(string $to, string $subject, string $html, ?string $replyTo = null): bool
{
    $to = mail_clean($to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $fromAddr = mail_clean(s('bk_smtp_user')) ?: mail_clean(s('contact_email'));
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
    $body = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($text))
        . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($html))
        . "--$boundary--\r\n";
    $subjectEnc = mail_header_encode(mail_clean($subject));

    try {
        if (s('bk_smtp_pass') === '' || s('bk_smtp_host') === '') {
            $ok = mail($to, $subjectEnc, $body, implode("\r\n", $headers), '-f' . $fromAddr);
            if (!$ok) {
                throw new RuntimeException('la fonction mail() du serveur a refusé l’envoi');
            }
        } else {
            smtp_send($fromAddr, $to, array_merge($headers, ['To: ' . $to, 'Subject: ' . $subjectEnc]), $body);
        }
        save_setting('_mail_last_error', '');
        return true;
    } catch (Throwable $e) {
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
    $cmd = function (string $c, array $expect) use ($fp, $read): void {
        fwrite($fp, $c . "\r\n");
        [$code, $resp] = $read();
        if (!in_array($code, $expect, true)) {
            $shown = str_starts_with($c, 'AUTH') || strlen($c) > 60 ? strtok($c, ' ') : $c;
            throw new RuntimeException("le serveur a répondu « $resp » à $shown");
        }
    };
    try {
        [$code, $resp] = $read();
        if ($code !== 220) {
            throw new RuntimeException("accueil inattendu : $resp");
        }
        $ehlo = 'EHLO ' . (gethostname() ?: 'localhost');
        $cmd($ehlo, [250]);
        if ($secure === 'tls') {
            $cmd('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('échec du chiffrement TLS');
            }
            $cmd($ehlo, [250]);
        }
        $cmd('AUTH LOGIN', [334]);
        $cmd(base64_encode(s('bk_smtp_user')), [334]);
        $cmd(base64_encode(s('bk_smtp_pass')), [235]);
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
    return '<!doctype html><html lang="fr"><body style="margin:0;background:#f4f6f8;font-family:Arial,Helvetica,sans-serif;color:#152131">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f8;padding:24px 0"><tr><td align="center">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#fff;border-radius:14px;overflow:hidden">'
        . '<tr><td style="background:#071c35;padding:22px 28px;color:#fff;font-size:20px;font-weight:bold">' . $name . '<div style="font-size:12px;color:#f2d47b;letter-spacing:2px;text-transform:uppercase;margin-top:4px">' . e(s('site_location')) . '</div></td></tr>'
        . '<tr><td style="padding:28px">'
        . '<h1 style="font-family:Georgia,serif;color:#071c35;font-size:24px;margin:0 0 16px">' . e($title) . '</h1>'
        . $content
        . '</td></tr><tr><td style="background:#fbf8f1;padding:18px 28px;font-size:12px;color:#617083">'
        . $name . ' — ' . e(s('address')) . '<br>' . e(s('contact_email'))
        . '</td></tr></table></td></tr></table></body></html>';
}

function mail_button(string $url, string $label, bool $primary = true): string
{
    return '<a href="' . e($url) . '" style="display:inline-block;margin:6px 6px 6px 0;padding:12px 18px;border-radius:8px;text-decoration:none;font-weight:bold;'
        . ($primary ? 'background:#d8a83f;color:#152131' : 'background:#eef2f6;color:#071c35') . '">' . e($label) . '</a>';
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
    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;margin:8px 0 18px">'
        . '<tr><td colspan="2" style="padding:10px 14px;background:#f4f6f8;border-radius:8px"><strong>' . e(bk_slot_label($b)) . '</strong><br>'
        . e($b['event_type']) . ' · ' . (int)$b['guests'] . ' personne(s) · Réf. ' . e($b['ref']) . '</td></tr>'
        . $rows
        . '<tr><td style="padding:10px 0 0;font-weight:bold">Total TTC</td><td align="right" style="padding:10px 0 0;font-weight:bold;font-size:16px">' . money2((float)$b['total_ttc']) . '</td></tr>'
        . '<tr><td colspan="2" style="font-size:12px;color:#617083">dont TVA ' . money2((float)$b['vat']) . ' — total HT ' . money2((float)$b['total_ht']) . '</td></tr></table>';
}

function bk_mail_confirmation(array $b): bool
{
    $content = '<p>Bonjour ' . e($b['first_name']) . ',</p><p>Merci pour votre réservation : votre paiement a bien été reçu et <strong>la salle vous est réservée</strong>.</p>'
        . mail_recap($b)
        . '<p style="margin:18px 0">' . mail_button(bk_client_url($b), 'Ma réservation et ma facture')
        . mail_button(gcal_template_url($b), 'Ajouter à Google Agenda', false)
        . mail_button(bk_client_url($b) . '&ics=1', 'Ajouter à mon agenda (Apple, Outlook)', false) . '</p>'
        . '<p style="background:#fff8e8;border:1px solid #f0dca8;border-radius:8px;padding:12px 14px;font-size:14px"><strong>Caution :</strong> ' . e(s('bk_deposit_text')) . '</p>'
        . '<p style="font-size:14px"><strong>Adresse :</strong> ' . e(s('address')) . (s('maps_url') ? ' — <a href="' . e(s('maps_url')) . '">itinéraire</a>' : '') . '</p>'
        . '<p style="font-size:14px">Une question ? Répondez simplement à cet e-mail.</p>'
        . '<p style="font-size:12px;color:#617083">Conditions de location : <a href="' . e(bk_url('conditions.php')) . '">' . e(bk_url('conditions.php')) . '</a></p>';
    $ok = send_mail($b['email'], 'Réservation confirmée — ' . s('site_name') . ' — ' . fr_date(bk_dt($b['start_at']), false), mail_layout('Votre réservation est confirmée', $content), s('contact_email'));
    bk_log((int)$b['id'], $ok ? 'E-mail de confirmation envoyé à ' . $b['email'] : 'ÉCHEC de l’envoi de l’e-mail de confirmation (voir Réglages › E-mails)');
    return $ok;
}

function bk_mail_admin_new(array $b, ?string $warning = null): bool
{
    $to = s('bk_notify_email');
    if ($to === '') {
        return false;
    }
    $content = ($warning ? '<p style="background:#fdecea;color:#b42318;padding:10px 14px;border-radius:8px"><strong>Attention :</strong> ' . e($warning) . '</p>' : '')
        . '<p><strong>' . e($b['first_name'] . ' ' . $b['last_name']) . '</strong>' . ($b['company'] ? ' (' . e($b['company']) . ')' : '')
        . '<br>' . e($b['email']) . ' · ' . e($b['phone']) . '</p>'
        . mail_recap($b)
        . ($b['message'] !== '' ? '<p><strong>Message :</strong><br>' . nl2br(e($b['message'])) . '</p>' : '')
        . '<p>' . mail_button(bk_url('admin/reservation.php?id=' . (int)$b['id']), 'Voir dans l’administration') . '</p>';
    return send_mail($to, 'Nouvelle réservation : ' . fr_date(bk_dt($b['start_at']), false) . ' — ' . $b['last_name'] . ' — ' . money2((float)$b['total_ttc']), mail_layout('Nouvelle réservation payée', $content), $b['email']);
}

function bk_mail_cancelled(array $b, float $refund): bool
{
    $content = '<p>Bonjour ' . e($b['first_name']) . ',</p><p>Votre réservation <strong>' . e($b['ref']) . '</strong> (' . e(bk_slot_label($b)) . ') a été <strong>annulée</strong>.</p>'
        . ($refund > 0
            ? '<p>Un remboursement de <strong>' . money2($refund) . '</strong> a été effectué sur votre moyen de paiement. Il apparaît sous 5 à 10 jours selon votre banque.</p>'
            : '<p>Conformément aux conditions de location, aucun remboursement n’est effectué.</p>')
        . '<p>' . mail_button(bk_client_url($b), 'Voir ma réservation') . '</p>';
    $ok = send_mail($b['email'], 'Annulation de votre réservation — ' . s('site_name'), mail_layout('Réservation annulée', $content), s('contact_email'));
    bk_log((int)$b['id'], $ok ? 'E-mail d’annulation envoyé' : 'ÉCHEC de l’envoi de l’e-mail d’annulation');
    return $ok;
}
