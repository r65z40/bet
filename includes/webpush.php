<?php
/**
 * Notifications push (Web Push) pour l'admin installé sur l'écran d'accueil (iPhone iOS 16.4+,
 * Android, ordinateur). Implémentation sans bibliothèque :
 *  - identification VAPID (RFC 8292) : jeton ES256 signé avec openssl ;
 *  - chiffrement du message aes128gcm (RFC 8291 / 8188) : ECDH P-256 + HKDF + AES-128-GCM.
 */

/** Services de notification autorisés (évite d'utiliser le serveur pour joindre d'autres adresses). */
const PUSH_HOSTS = ['push.apple.com', 'fcm.googleapis.com', 'android.googleapis.com', 'push.services.mozilla.com', 'notify.windows.com'];

function push_endpoint_allowed(string $url): bool
{
    $p = parse_url($url);
    if (($p['scheme'] ?? '') !== 'https' && !(getenv('RN6_ALLOW_PUSH_HOST') && ($p['host'] ?? '') === getenv('RN6_ALLOW_PUSH_HOST'))) {
        return false;
    }
    $host = strtolower($p['host'] ?? '');
    if (getenv('RN6_ALLOW_PUSH_HOST') && $host === getenv('RN6_ALLOW_PUSH_HOST')) {
        return true; // tests locaux uniquement
    }
    foreach (PUSH_HOSTS as $h) {
        if ($host === $h || str_ends_with($host, '.' . $h)) {
            return true;
        }
    }
    return false;
}

/** Clés VAPID du site (créées au premier usage). Retourne [clé privée PEM, clé publique brute 65 octets]. */
function vapid_keys(): array
{
    if (s('_vapid_private') === '') {
        $k = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        openssl_pkey_export($k, $pem);
        save_setting('_vapid_private', $pem);
        settings(true);
    }
    $priv = openssl_pkey_get_private(s('_vapid_private'));
    $d = openssl_pkey_get_details($priv)['ec'];
    return [$priv, "\x04" . str_pad($d['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['y'], 32, "\0", STR_PAD_LEFT)];
}

function vapid_public_b64(): string
{
    return b64url(vapid_keys()[1]);
}

function b64url_decode(string $s): string
{
    return (string)base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4));
}

/** Signature DER (openssl) → format brut r||s de 64 octets attendu par ES256. */
function der_to_raw_signature(string $der): string
{
    $pos = 2;
    if ((ord($der[1]) & 0x80) !== 0) {
        $pos += ord($der[1]) & 0x7f;
    }
    $out = '';
    for ($i = 0; $i < 2; $i++) {
        $len = ord($der[$pos + 1]);
        $int = substr($der, $pos + 2, $len);
        $out .= str_pad(ltrim($int, "\0"), 32, "\0", STR_PAD_LEFT);
        $pos += 2 + $len;
    }
    return $out;
}

/** Clé publique brute P-256 (65 octets) → ressource openssl. */
function ec_public_from_raw(string $raw)
{
    $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $raw;
    return openssl_pkey_get_public("-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n");
}

/** Chiffre un message pour un abonnement (RFC 8291, aes128gcm). */
function push_encrypt(string $payload, string $uaPublicB64, string $authB64): string
{
    $uaPublic = b64url_decode($uaPublicB64);
    $authSecret = b64url_decode($authB64);
    if (strlen($uaPublic) !== 65 || strlen($authSecret) < 16) {
        throw new RuntimeException('abonnement invalide');
    }
    $local = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    $d = openssl_pkey_get_details($local)['ec'];
    $asPublic = "\x04" . str_pad($d['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['y'], 32, "\0", STR_PAD_LEFT);
    $shared = openssl_pkey_derive(ec_public_from_raw($uaPublic), $local, 32);
    if ($shared === false) {
        throw new RuntimeException('échange de clés impossible');
    }
    $prkKey = hash_hmac('sha256', $shared, $authSecret, true);
    $ikm = substr(hash_hmac('sha256', "WebPush: info\0" . $uaPublic . $asPublic . "\x01", $prkKey, true), 0, 32);
    $salt = random_bytes(16);
    $prk = hash_hmac('sha256', $ikm, $salt, true);
    $cek = substr(hash_hmac('sha256', "Content-Encoding: aes128gcm\0\x01", $prk, true), 0, 16);
    $nonce = substr(hash_hmac('sha256', "Content-Encoding: nonce\0\x01", $prk, true), 0, 12);
    $cipher = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
    return $salt . pack('N', 4096) . chr(65) . $asPublic . $cipher . $tag;
}

/** Envoie une notification à un abonnement. Retourne le code HTTP du service de notification. */
function push_send(array $sub, array $message): int
{
    if (!push_endpoint_allowed($sub['endpoint'])) {
        return 400;
    }
    [$priv, $pub] = vapid_keys();
    $p = parse_url($sub['endpoint']);
    $aud = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
    $head = b64url(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $claims = b64url(json_encode(['aud' => $aud, 'exp' => time() + 12 * 3600, 'sub' => 'mailto:' . (s('contact_email') ?: 'contact@example.com')]));
    openssl_sign($head . '.' . $claims, $der, $priv, OPENSSL_ALGO_SHA256);
    $jwt = $head . '.' . $claims . '.' . b64url(der_to_raw_signature($der));
    $body = push_encrypt(json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $sub['p256dh'], $sub['auth']);
    $ch = curl_init($sub['endpoint']);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 6, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER => ['Authorization: vapid t=' . $jwt . ', k=' . b64url($pub), 'Content-Encoding: aes128gcm', 'Content-Type: application/octet-stream', 'TTL: 86400', 'Urgency: high']]);
    curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    if (in_array($code, [404, 410], true)) {
        db()->prepare('DELETE FROM push_subs WHERE id = ?')->execute([$sub['id']]); // abonnement expiré
    }
    return $code;
}

/** Notifie tous les appareils des comptes actifs ayant le droit demandé. Ne bloque jamais. */
function push_notify(string $perm, string $title, string $body, string $url = 'index.php', string $tag = '', string $event = ''): int
{
    $sent = 0;
    try {
        foreach (db()->query('SELECT s.*, a.perms, a.active, a.notif_off FROM push_subs s JOIN admins a ON a.id = s.admin_id')->fetchAll() as $sub) {
            if (!(int)$sub['active'] || !in_array($perm, admin_perms($sub), true) || ($event !== '' && in_array($event, admin_notif_off($sub), true))) {
                continue;
            }
            try {
                $code = push_send($sub, ['title' => $title, 'body' => $body, 'url' => $url, 'tag' => $tag]);
                $sent += $code >= 200 && $code < 300 ? 1 : 0;
            } catch (Throwable) {
            }
        }
    } catch (Throwable) {
    }
    return $sent;
}
