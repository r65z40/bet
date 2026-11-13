<?php
/** Sauvegardes : automatiques et manuelles, téléchargement (archive complète), restauration. */
require __DIR__ . '/_init.php';
require_perm('reglages');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $do = (string)($_POST['do'] ?? '');
    $name = (string)($_POST['name'] ?? '');
    // Télécharger, restaurer ou supprimer touche aux données de tous les clients : mot de passe redemandé
    if (in_array($do, ['zip', 'db', 'restore', 'upload', 'delete', 'drive_keys', 'drive_start', 'drive_off'], true)) {
        require_reauth('sauvegardes.php');
    }
    try {
        if ($do === 'create') {
            $n = backup_create('manuelle');
            admin_log('Sauvegarde créée : ' . $n);
            flash('Sauvegarde créée : ' . $n);
        } elseif ($do === 'settings') {
            save_setting('bk_backup_auto', !empty($_POST['auto']) ? '1' : '0');
            save_setting('bk_backup_keep', (string)max(3, min(60, (int)($_POST['keep'] ?? 14))));
            admin_log('Réglages des sauvegardes modifiés');
            flash('Réglages enregistrés.');
        } elseif ($do === 'zip') {
            $path = backup_zip($name !== '' ? $name : null);
            admin_log('Archive de sauvegarde téléchargée' . ($name !== '' ? ' : ' . $name : ' (état actuel)'));
            backup_send($path, 'sauvegarde-lasallern6-' . ($name !== '' && preg_match(BACKUP_RE, $name, $m) ? $m[1] . '-' . $m[2] : date('Y-m-d-Hi')) . '.zip', 'application/zip', true);
        } elseif ($do === 'db') {
            $path = backup_path($name) ?? throw new RuntimeException('sauvegarde introuvable');
            admin_log('Base de sauvegarde téléchargée : ' . $name);
            backup_send($path, $name, 'application/gzip', false);
        } elseif ($do === 'restore') {
            $path = backup_path($name) ?? throw new RuntimeException('sauvegarde introuvable');
            admin_log('Restauration de la sauvegarde ' . $name);
            $safety = backup_restore_file(backup_extract_checked($path));
            flash('Base restaurée depuis ' . $name . '. L’état précédent a été sauvegardé (' . $safety . ') : vous pouvez revenir en arrière.');
        } elseif ($do === 'upload') {
            $f = $_FILES['file'] ?? [];
            if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
                throw new RuntimeException(($f['error'] ?? 0) === UPLOAD_ERR_INI_SIZE ? 'fichier trop lourd pour l’hébergement : envoyez plutôt la « Base » seule (fichier .sqlite.gz, quelques dizaines de Ko), et les photos par FTP' : 'aucun fichier reçu');
            }
            $ext = strtolower((string)$f['name']);
            $dir = backup_dir();
            if (str_ends_with($ext, '.zip')) {
                $zip = new ZipArchive();
                if ($zip->open($f['tmp_name']) !== true || ($data = $zip->getFromName('base-de-donnees.sqlite')) === false) {
                    throw new RuntimeException('archive sans « base-de-donnees.sqlite »');
                }
                $zip->close();
                $tmp = $dir . '/.restore-' . bin2hex(random_bytes(6)) . '.sqlite';
                file_put_contents($tmp, $data);
                unset($data);
                backup_check_db($tmp);
            } elseif (str_ends_with($ext, '.gz')) {
                $tmp = backup_extract_checked($f['tmp_name']);
            } elseif (str_ends_with($ext, '.sqlite')) {
                $tmp = $dir . '/.restore-' . bin2hex(random_bytes(6)) . '.sqlite';
                move_uploaded_file($f['tmp_name'], $tmp);
                backup_check_db($tmp);
            } else {
                throw new RuntimeException('envoyez une archive .zip, un fichier .sqlite.gz ou .sqlite téléchargés depuis cette page');
            }
            admin_log('Restauration depuis un fichier envoyé : ' . basename((string)$f['name']));
            $safety = backup_restore_file($tmp);
            flash('Base restaurée depuis le fichier envoyé. L’état précédent a été sauvegardé (' . $safety . ').');
        } elseif ($do === 'drive_keys') {
            $id = trim((string)($_POST['drive_client_id'] ?? ''));
            $secret = trim((string)($_POST['drive_client_secret'] ?? ''));
            if ($id !== '' && !preg_match('/^[\w.-]+\.apps\.googleusercontent\.com$/', $id)) {
                throw new RuntimeException('l’« ID client » doit se terminer par .apps.googleusercontent.com');
            }
            if ($id !== s('bk_drive_client_id') && drive_connected()) {
                drive_disconnect();
            }
            save_setting('bk_drive_client_id', $id);
            if ($secret !== '' || $id === '') {
                save_setting('bk_drive_client_secret', $secret);
            }
            admin_log('Identifiants Google Drive modifiés');
            flash($id === '' ? 'Identifiants Google Drive effacés.' : 'Identifiants enregistrés : cliquez maintenant sur « Connecter Google Drive ».');
        } elseif ($do === 'drive_start' && drive_configured()) {
            $_SESSION['drive_state'] = bin2hex(random_bytes(16));
            $_SESSION['drive_state_at'] = time();
            $url = drive_auth_url($_SESSION['drive_state']);
            // Page relais : la politique de sécurité de l'admin interdit de rediriger un formulaire vers un autre site
            echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Google Drive</title>'
                . '<p style="font:16px system-ui;margin:40px auto;max-width:420px;text-align:center">Ouverture de Google…<br><br><a href="' . e($url) . '" id="go">Continuer vers Google</a></p>'
                . '<script>location.replace(document.getElementById("go").href)</script>';
            exit;
        } elseif ($do === 'drive_now') {
            $sent = drive_sync(true, true);
            admin_log('Copie manuelle sur Google Drive');
            flash($sent ? 'Copié sur Google Drive : ' . implode(', ', $sent) . '.' : 'Rien à copier : faites d’abord une sauvegarde.');
        } elseif ($do === 'drive_off') {
            drive_disconnect();
            admin_log('Google Drive déconnecté des sauvegardes');
            flash('Google Drive déconnecté. Les copies déjà faites restent sur votre Drive.');
        } elseif ($do === 'delete') {
            $path = backup_path($name) ?? throw new RuntimeException('sauvegarde introuvable');
            unlink($path);
            admin_log('Sauvegarde supprimée : ' . $name);
            flash('Sauvegarde supprimée.');
        }
    } catch (Throwable $e) {
        flash('Opération impossible : ' . $e->getMessage(), 'err');
    }
    redirect('sauvegardes.php');
}

$list = backup_list();
$last = backup_last_time();
$size = fn(int $b) => $b >= 1048576 ? number_format($b / 1048576, 1, ',', '') . ' Mo' : max(1, (int)round($b / 1024)) . ' Ko';
$kinds = ['auto' => 'Automatique', 'manuelle' => 'Manuelle', 'avantrestauration' => 'Avant restauration'];
admin_header('Sauvegardes', 'reglages');
?>
<h1>Réglages des réservations</h1>
<p class="intro">Sauvegardes de la base : réservations, factures, clients, réglages, textes et comptes.</p>
<div class="layout">
  <aside class="side">
    <?php foreach (BOOKING_SETTINGS as $key => $sec): if ($key === 'tarifs') continue; ?>
      <a href="reglages.php?s=<?= e($key) ?>"><?= e($sec['title']) ?></a>
    <?php endforeach; ?>
    <a href="notifications.php">Notifications</a>
    <a href="sauvegardes.php" class="on">Sauvegardes</a>
  </aside>
  <div style="min-width:0">
    <?php if (s('_backup_error') !== ''): ?><div class="flash flash-err">Dernière sauvegarde automatique en échec : <?= e(s('_backup_error')) ?></div><?php endif; ?>
    <div class="card">
      <h2>Sauvegardes</h2>
      <p class="muted" style="margin-top:-6px">Dernière sauvegarde : <strong><?= $last ? e(date('d/m/Y à H:i', $last)) : 'jamais' ?></strong>.
        <?= s('bk_backup_auto') === '0' ? 'Sauvegarde automatique désactivée.' : 'Une sauvegarde automatique est faite chaque jour par la tâche planifiée ; les ' . max(3, (int)bk_num('bk_backup_keep', 14)) . ' dernières sont conservées.' ?></p>
      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:6px">
        <form method="post"><?= csrf_field() ?><button class="btn" name="do" value="create">💾 Sauvegarder maintenant</button></form>
        <form method="post"><?= csrf_field() ?><?= reauth_field() ?><button class="btn light" name="do" value="zip">⬇ Télécharger tout (base + photos + documents)</button></form>
      </div>
      <p class="muted" style="font-size:.85rem">Conseil : téléchargez une archive complète une fois par mois et rangez-la en lieu sûr (clé USB, disque, coffre en ligne). Elle contient les données de vos clients et vos clés Stripe / e-mail : ne l’envoyez jamais par e-mail.</p>
    </div>

    <div class="card">
      <h2>Sauvegardes disponibles (<?= count($list) ?>)</h2>
      <?php if (!$list): ?><p class="muted">Aucune pour l’instant.</p><?php else: ?>
      <div style="overflow-x:auto"><table class="bklist">
        <tr><th>Date</th><th>Type</th><th class="num">Taille</th><th></th></tr>
        <?php foreach ($list as $b): ?>
        <tr>
          <td><strong><?= e(date('d/m/Y H:i', $b['time'])) ?></strong></td>
          <td><?= e($kinds[$b['kind']] ?? ucfirst($b['kind'])) ?></td>
          <td class="num"><?= e($size($b['size'])) ?></td>
          <td>
            <form method="post" style="display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end;margin:0">
              <?= csrf_field() ?><input type="hidden" name="name" value="<?= e($b['name']) ?>"><?= reauth_needed() ? '<input type="password" name="reauth" placeholder="Votre mot de passe" style="width:150px" autocomplete="current-password">' : '' ?>
              <button class="btn light sm" name="do" value="db" title="Base seule, compressée">⬇ Base</button>
              <button class="btn light sm" name="do" value="zip" title="Base + photos + documents">⬇ Archive</button>
              <button class="btn sm" name="do" value="restore" onclick="return confirm('Restaurer la base telle qu’elle était le <?= e(date('d/m/Y à H:i', $b['time'])) ?> ? Les réservations et modifications faites depuis seront remplacées (l’état actuel est sauvegardé juste avant).')">↺ Restaurer</button>
              <button class="btn danger sm" name="do" value="delete" onclick="return confirm('Supprimer cette sauvegarde ?')">✕</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </table></div>
      <?php endif; ?>
    </div>

    <div class="card" id="drive">
      <h2>Copie sur Google Drive</h2>
      <?php if (s('_drive_error') !== ''): ?><div class="flash flash-err">Dernière copie en échec : <?= e(s('_drive_error')) ?></div><?php endif; ?>
      <?php if (drive_connected()): ?>
        <p>✅ Connecté<?= s('_drive_email') !== '' ? ' à <strong>' . e(s('_drive_email')) . '</strong>' : '' ?>, dossier « <?= e(DRIVE_FOLDER_NAME) ?> ».<br>
          <span class="muted">Chaque jour, la base y est copiée après la sauvegarde automatique (<?= DRIVE_KEEP_DB ?> dernières gardées) ; chaque mois, l’archive complète avec les photos et documents (<?= DRIVE_KEEP_ZIP ?> dernières gardées).
          Dernière copie : <?= (int)s('_drive_last') ? e(date('d/m/Y à H:i', (int)s('_drive_last'))) : 'pas encore' ?><?= (int)s('_drive_last_zip') ? ' · dernière archive complète : ' . e(date('d/m/Y', (int)s('_drive_last_zip'))) : '' ?>.</span></p>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
          <form method="post"><?= csrf_field() ?><button class="btn" name="do" value="drive_now">☁ Copier maintenant (base + archive complète)</button></form>
          <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center"><?= csrf_field() ?><?= reauth_needed() ? '<input type="password" name="reauth" placeholder="Votre mot de passe" style="width:170px" autocomplete="current-password">' : '' ?><button class="btn light" name="do" value="drive_off" onclick="return confirm('Arrêter les copies sur Google Drive ?')">Déconnecter</button></form>
        </div>
        <p class="muted" style="font-size:.85rem;margin-top:12px">Pour restaurer : téléchargez le fichier depuis votre Drive, puis utilisez « Restaurer depuis un fichier » ci-dessous. Ce dossier contient les données de vos clients : ne le partagez avec personne.</p>
      <?php else: ?>
        <p class="muted" style="margin-top:-6px">Une copie de chaque sauvegarde part automatiquement sur votre Google Drive : si l’hébergement avait un problème, vos réservations restent récupérables. Le site n’a accès qu’à son propre dossier, pas au reste de votre Drive.</p>
        <?php if (drive_configured()): ?>
          <form method="post" style="margin:10px 0 16px"><?= csrf_field() ?><?= reauth_field() ?><button class="btn gold" name="do" value="drive_start">Connecter Google Drive</button></form>
        <?php endif; ?>
        <details<?= drive_configured() ? '' : ' open' ?>>
          <summary style="cursor:pointer;font-weight:700">Mise en place (une seule fois, 10 minutes)</summary>
          <ol style="line-height:1.6;padding-left:20px">
            <li>Ouvrez <a href="https://console.cloud.google.com/" target="_blank" rel="noopener">console.cloud.google.com</a> avec votre compte Google, dans le même projet que pour l’agenda.</li>
            <li>« API et services » › « Bibliothèque » : cherchez <strong>Google Drive API</strong> et cliquez sur « Activer ».</li>
            <li>« Écran de consentement OAuth » (ou « Google Auth Platform ») : type <strong>Externe</strong>, nom de l’application « La Salle RN6 », votre adresse e-mail. Puis dans « Audience », cliquez sur <strong>Publier l’application</strong> (« En production ») : sinon Google coupe l’accès au bout de 7 jours. Aucune validation par Google n’est nécessaire.</li>
            <li>« Identifiants » (ou « Clients ») › « Créer des identifiants » › <strong>ID client OAuth</strong> › type « Application Web ». Dans « URI de redirection autorisés », ajoutez exactement :<br><code style="user-select:all;word-break:break-all"><?= e(drive_redirect_uri()) ?></code></li>
            <li>Copiez l’<strong>ID client</strong> et le <strong>code secret</strong> ci-dessous, enregistrez, puis cliquez sur « Connecter Google Drive ».</li>
          </ol>
          <form method="post">
            <?= csrf_field() ?>
            <?= field('drive_client_id', 'ID client', s('bk_drive_client_id'), 'text', '….apps.googleusercontent.com') ?>
            <?= field('drive_client_secret', 'Code secret du client', '', 'password', s('bk_drive_client_secret') !== '' ? 'Enregistré — laissez vide pour le garder' : 'GOCSPX-…') ?>
            <?= reauth_field() ?>
            <button class="btn" name="do" value="drive_keys">Enregistrer</button>
          </form>
        </details>
      <?php endif; ?>
    </div>

    <form method="post" enctype="multipart/form-data" class="card">
      <?= csrf_field() ?>
      <h2>Restaurer depuis un fichier</h2>
      <p class="muted" style="margin-top:-6px">Une archive <code>.zip</code> ou une base <code>.sqlite.gz</code> téléchargée depuis cette page (par exemple pour remettre le site en état ou le déplacer chez un autre hébergeur). Le fichier est vérifié avant d’être utilisé, et l’état actuel est sauvegardé juste avant. Les photos et documents de l’archive se renvoient par FTP.</p>
      <label class="field"><span>Fichier de sauvegarde</span><input type="file" name="file" accept=".zip,.gz,.sqlite" required></label>
      <?= reauth_field() ?>
      <button class="btn danger" name="do" value="upload" onclick="return confirm('Remplacer toute la base par ce fichier ?')">↺ Restaurer ce fichier</button>
    </form>

    <form method="post" class="card">
      <?= csrf_field() ?>
      <h2>Réglages</h2>
      <label style="display:block;margin-bottom:12px"><input type="checkbox" name="auto" value="1"<?= s('bk_backup_auto') !== '0' ? ' checked' : '' ?>> Sauvegarde automatique chaque jour</label>
      <?= field('keep', 'Nombre de sauvegardes automatiques conservées (3 à 60)', (string)max(3, (int)bk_num('bk_backup_keep', 14)), 'number') ?>
      <button class="btn" name="do" value="settings">Enregistrer</button>
      <p class="muted" style="font-size:.85rem;margin-top:12px">En plus, OVH garde ses propres copies de l’hébergement (de la veille à 2 semaines), récupérables depuis l’espace client OVH › Hébergement › FTP-SSH › « Restaurer une sauvegarde ».</p>
    </form>
  </div>
</div>
<style>
@media(max-width:640px){
  table.bklist tr:first-child{display:none}
  table.bklist tr{display:grid;grid-template-columns:1fr auto auto;gap:4px 10px;padding:10px 0;border-bottom:1px solid var(--line,#e5e7eb)}
  table.bklist td{border:0;padding:0}
  table.bklist td:last-child{grid-column:1/-1}
  table.bklist td:last-child form{justify-content:flex-start!important}
}
</style>
<?php admin_footer();
