<?php
/**
 * Copie des sauvegardes sur Google Drive (compte Google du propriétaire).
 *
 * Connexion OAuth « application web » avec le droit le plus réduit (drive.file) : le site ne
 * voit et ne gère que les fichiers qu'il a lui-même déposés, dans un dossier dédié.
 *  - chaque jour : la base (fichier .sqlite.gz, quelques dizaines de Ko) ;
 *  - chaque mois : l'archive complète (base + photos + documents), uniquement depuis la
 *    tâche planifiée (pas pendant la visite d'un client).
 * Les copies anciennes sont supprimées du Drive (30 bases, 6 archives conservées).
 */

const DRIVE_SCOPE = 'https://www.googleapis.com/auth/drive.file';
const DRIVE_KEEP_DB = 30;
const DRIVE_KEEP_ZIP = 6;
const DRIVE_FOLDER_NAME = 'Sauvegardes du site La Salle RN6';

function drive_api_base(): string
{
    return rtrim(getenv('RN6_DRIVE_API') ?: 'https://www.googleapis.com', '/'); // variable réservée aux tests
}

function drive_auth_base(): string
{
    return getenv('RN6_DRIVE_AUTH') ?: 'https://accounts.google.com/o/oauth2/v2/auth';
}

function drive_redirect_uri(): string
{
    return rtrim(s('site_url'), '/') . '/admin/drive.php';
}

function drive_configured(): bool
{
    return trim(s('bk_drive_client_id')) !== '' && trim(s('bk_drive_client_secret')) !== '';
}

function drive_connected(): bool
{
    return s('_drive_refresh') !== '';
}

function drive_auth_url(string $state): string
{
    return drive_auth_base() . '?' . http_build_query([
        'client_id' => trim(s('bk_drive_client_id')), 'redirect_uri' => drive_redirect_uri(), 'response_type' => 'code',
        'scope' => DRIVE_SCOPE, 'access_type' => 'offline', 'prompt' => 'consent', 'include_granted_scopes' => 'false', 'state' => $state,
    ], '', '&', PHP_QUERY_RFC3986);
}

function drive_save_token(array $data): string
{
    save_setting('_drive_token', (string)$data['access_token']);
    save_setting('_drive_token_exp', (string)(time() + (int)($data['expires_in'] ?? 3600)));
    settings(true);
    return (string)$data['access_token'];
}

/** Fin de la connexion : échange du code contre un jeton durable, puis création du dossier. */
function drive_connect(string $code): string
{
    [$http, $data] = http_api('POST', gcal_token_url(), ['Accept: application/json'], [
        'grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => drive_redirect_uri(),
        'client_id' => trim(s('bk_drive_client_id')), 'client_secret' => trim(s('bk_drive_client_secret')),
    ]);
    if ($http >= 400 || empty($data['access_token'])) {
        throw new RuntimeException('Google a refusé la connexion (' . ($data['error_description'] ?? $data['error'] ?? 'erreur ' . $http) . ')');
    }
    if (empty($data['refresh_token'])) {
        throw new RuntimeException('Google n’a pas donné d’accès durable : retirez l’accès du site dans votre compte Google (Sécurité › Applications tierces) puis reconnectez-vous');
    }
    if (!str_contains((string)($data['scope'] ?? DRIVE_SCOPE), 'drive.file')) {
        throw new RuntimeException('l’accès à Google Drive n’a pas été coché sur l’écran de Google');
    }
    save_setting('_drive_refresh', (string)$data['refresh_token']);
    save_setting('_drive_folder', '');
    save_setting('_drive_error', '');
    drive_save_token($data);
    $email = '';
    try {
        [, $about] = drive_api('GET', '/drive/v3/about?fields=user(emailAddress)');
        $email = (string)($about['user']['emailAddress'] ?? '');
    } catch (Throwable) {
    }
    save_setting('_drive_email', $email);
    settings(true);
    drive_folder();
    return $email;
}

function drive_disconnect(): void
{
    $t = s('_drive_refresh');
    if ($t !== '') {
        try {
            http_api('POST', getenv('RN6_DRIVE_REVOKE') ?: 'https://oauth2.googleapis.com/revoke', [], ['token' => $t]);
        } catch (Throwable) {
        }
    }
    foreach (['_drive_refresh', '_drive_token', '_drive_token_exp', '_drive_email', '_drive_folder', '_drive_error', '_drive_last', '_drive_last_name'] as $k) {
        save_setting($k, '');
    }
    settings(true);
}

/** Jeton d'accès (renouvelé avec le jeton durable). */
function drive_token(): string
{
    if (s('_drive_token') !== '' && (int)s('_drive_token_exp') > time() + 60) {
        return s('_drive_token');
    }
    if (!drive_connected()) {
        throw new RuntimeException('Google Drive n’est pas connecté');
    }
    [$http, $data] = http_api('POST', gcal_token_url(), ['Accept: application/json'], [
        'grant_type' => 'refresh_token', 'refresh_token' => s('_drive_refresh'),
        'client_id' => trim(s('bk_drive_client_id')), 'client_secret' => trim(s('bk_drive_client_secret')),
    ]);
    if ($http >= 400 || empty($data['access_token'])) {
        if (($data['error'] ?? '') === 'invalid_grant') {
            save_setting('_drive_refresh', ''); // accès retiré ou expiré : il faut se reconnecter
            settings(true);
            throw new RuntimeException('l’accès à Google Drive a été retiré ou a expiré : reconnectez Google Drive dans Réglages › Sauvegardes');
        }
        throw new RuntimeException('Google a refusé le renouvellement de l’accès (' . ($data['error_description'] ?? $data['error'] ?? 'erreur ' . $http) . ')');
    }
    return drive_save_token($data);
}

function drive_api(string $method, string $path, ?array $body = null): array
{
    [$http, $data] = http_api($method, drive_api_base() . $path, ['Authorization: Bearer ' . drive_token(), 'Content-Type: application/json', 'Accept: application/json'],
        $body === null ? null : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    if ($http >= 400) {
        throw new RuntimeException('Google Drive : ' . ($data['error']['message'] ?? 'erreur ' . $http), $http);
    }
    return [$http, $data];
}

/** Dossier des sauvegardes sur le Drive (recréé s'il a été supprimé ou mis à la corbeille). */
function drive_folder(): string
{
    $id = s('_drive_folder');
    if ($id !== '') {
        try {
            [, $f] = drive_api('GET', '/drive/v3/files/' . rawurlencode($id) . '?fields=id,trashed');
            if (empty($f['trashed'])) {
                return $id;
            }
        } catch (RuntimeException $e) {
            if ($e->getCode() !== 404) {
                throw $e;
            }
        }
    }
    [, $f] = drive_api('POST', '/drive/v3/files?fields=id', ['name' => DRIVE_FOLDER_NAME, 'mimeType' => 'application/vnd.google-apps.folder']);
    save_setting('_drive_folder', (string)$f['id']);
    settings(true);
    return (string)$f['id'];
}

/** Envoi d'un fichier (envoi « reprenable » de Google, le fichier est lu par morceaux). */
function drive_upload(string $path, string $name, string $mime): string
{
    $size = (int)filesize($path);
    $meta = json_encode(['name' => $name, 'parents' => [drive_folder()]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $ch = curl_init(drive_api_base() . '/upload/drive/v3/files?uploadType=resumable&fields=id');
    $location = '';
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => $meta, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25, CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . drive_token(), 'Content-Type: application/json; charset=UTF-8', 'X-Upload-Content-Type: ' . $mime, 'X-Upload-Content-Length: ' . $size],
        CURLOPT_HEADERFUNCTION => function ($c, $h) use (&$location) {
            if (stripos($h, 'location:') === 0) {
                $location = trim(substr($h, 9));
            }
            return strlen($h);
        },
    ]);
    $res = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    if ($res === false || $http >= 400 || $location === '') {
        $d = json_decode((string)$res, true);
        throw new RuntimeException('Google Drive a refusé l’envoi (' . ($d['error']['message'] ?? ($res === false ? curl_error($ch) : 'erreur ' . $http)) . ')');
    }
    $fh = fopen($path, 'rb');
    $ch = curl_init($location);
    curl_setopt_array($ch, [
        CURLOPT_UPLOAD => true, CURLOPT_CUSTOMREQUEST => 'PUT', CURLOPT_INFILE => $fh, CURLOPT_INFILESIZE => $size, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => max(60, (int)($size / 200000)), CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . drive_token(), 'Content-Type: ' . $mime],
    ]);
    $res = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    fclose($fh);
    $d = json_decode((string)$res, true) ?: [];
    if ($res === false || $http >= 400 || empty($d['id'])) {
        throw new RuntimeException('envoi sur Google Drive interrompu (' . ($d['error']['message'] ?? ($res === false ? curl_error($ch) : 'erreur ' . $http)) . ')');
    }
    return (string)$d['id'];
}

/** Supprime du Drive les copies les plus anciennes (seulement celles déposées par le site). */
function drive_prune(): int
{
    $q = "'" . drive_folder() . "' in parents and trashed = false";
    [, $d] = drive_api('GET', '/drive/v3/files?' . http_build_query(['q' => $q, 'orderBy' => 'createdTime desc', 'pageSize' => 200, 'fields' => 'files(id,name)'], '', '&', PHP_QUERY_RFC3986));
    $seen = ['db' => 0, 'zip' => 0];
    $n = 0;
    foreach ($d['files'] ?? [] as $f) {
        $kind = str_ends_with($f['name'], '.zip') ? 'zip' : (str_ends_with($f['name'], '.sqlite.gz') ? 'db' : '');
        if ($kind === '') {
            continue; // fichier ajouté à la main : on n'y touche pas
        }
        if (++$seen[$kind] > ($kind === 'zip' ? DRIVE_KEEP_ZIP : DRIVE_KEEP_DB)) {
            drive_api('DELETE', '/drive/v3/files/' . rawurlencode($f['id']));
            $n++;
        }
    }
    return $n;
}

/**
 * Envoie la dernière sauvegarde si elle n'est pas encore sur le Drive, et l'archive complète
 * une fois par mois ($full : seulement depuis la tâche planifiée ou à la demande).
 * Retourne la liste de ce qui a été envoyé.
 */
function drive_sync(bool $full, bool $forceZip = false): array
{
    if (!drive_connected() || !drive_configured()) {
        return [];
    }
    $sent = [];
    try {
        $list = array_values(array_filter(backup_list(), fn($b) => $b['kind'] !== 'avantrestauration'));
        if ($list && $list[0]['name'] !== s('_drive_last_name')) {
            drive_upload(backup_path($list[0]['name']), $list[0]['name'], 'application/gzip');
            save_setting('_drive_last_name', $list[0]['name']);
            save_setting('_drive_last', (string)time());
            $sent[] = $list[0]['name'];
        }
        if ($full && ($forceZip || time() - (int)s('_drive_last_zip') > 28 * 86400)) {
            $zip = backup_zip();
            try {
                $name = 'archive-complete-' . date('Y-m-d') . '.zip';
                drive_upload($zip, $name, 'application/zip');
                save_setting('_drive_last_zip', (string)time());
                $sent[] = $name;
            } finally {
                @unlink($zip);
            }
        }
        if ($sent) {
            drive_prune();
        }
        if (s('_drive_error') !== '') {
            save_setting('_drive_error', '');
        }
        settings(true);
    } catch (Throwable $e) {
        $first = s('_drive_error') === '';
        save_setting('_drive_error', date('d/m/Y H:i') . ' — ' . $e->getMessage());
        settings(true);
        if ($first) { // une seule alerte, pas une par heure
            admin_notify('conflict', '⚠ Copie sur Google Drive impossible', [$e->getMessage(), 'Vérifiez Réglages › Sauvegardes.'], 'sauvegardes.php');
        }
        throw $e;
    }
    return $sent;
}
