<?php
/**
 * Comptes administrateurs : droits par domaine, sessions, invitations,
 * mot de passe oublié et journal d'activité.
 */

/** Droits disponibles (cases à cocher dans Admin › Utilisateurs). */
const ADMIN_PERMS = [
    'reservations'   => ['Réservations', 'Voir le planning et les réservations, bloquer des créneaux, suivre les cautions'],
    'remboursements' => ['Annulations & remboursements', 'Annuler une réservation et rembourser le client'],
    'tarifs'         => ['Tarifs & options', 'Modifier les prix et les options payantes'],
    'emails'         => ['E-mails clients', 'Écrire aux clients, envois groupés'],
    'contenu'        => ['Contenu du site', 'Textes, photos, avis, listes, ordre des sections'],
    'stats'          => ['Statistiques', 'Fréquentation et chiffre d’affaires'],
    'reglages'       => ['Réglages techniques', 'Paiement, envoi d’e-mails, agenda, société, conditions, maintenance'],
    'utilisateurs'   => ['Utilisateurs', 'Créer des comptes et attribuer les droits'],
];

/** Rôles prédéfinis (raccourcis pour cocher les droits). */
const ADMIN_ROLES = [
    'admin'     => ['Administrateur', ['reservations', 'remboursements', 'tarifs', 'emails', 'contenu', 'stats', 'reglages', 'utilisateurs']],
    'gerant'    => ['Gérant', ['reservations', 'remboursements', 'tarifs', 'emails', 'contenu', 'stats']],
    'accueil'   => ['Accueil / planning', ['reservations', 'emails']],
    'redacteur' => ['Rédacteur du site', ['contenu']],
];

const ADMIN_IDLE_TIMEOUT = 7200; // déconnexion automatique après 2 h d'inactivité

function admin_count(): int
{
    return (int)db()->query('SELECT COUNT(*) FROM admins')->fetchColumn();
}

/** Liste des droits d'un compte. */
function admin_perms(array $admin): array
{
    $p = trim((string)($admin['perms'] ?? ''));
    return $p === 'all' ? array_keys(ADMIN_PERMS) : array_values(array_intersect(array_filter(explode(',', $p)), array_keys(ADMIN_PERMS)));
}

function admin_role_label(array $admin): string
{
    $mine = admin_perms($admin);
    sort($mine);
    foreach (ADMIN_ROLES as [$label, $perms]) {
        sort($perms);
        if ($perms === $mine) {
            return $label;
        }
    }
    return $mine ? 'Personnalisé' : 'Aucun droit';
}

function current_admin(): ?array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache ?: null;
    }
    if (empty($_SESSION['admin_id'])) {
        return null;
    }
    $logout = function () use (&$cache) {
        unset($_SESSION['admin_id'], $_SESSION['admin_seen'], $_SESSION['admin_login_at']);
        $cache = false;
        return null;
    };
    if (time() - (int)($_SESSION['admin_seen'] ?? 0) > ADMIN_IDLE_TIMEOUT) {
        return $logout();
    }
    $st = db()->prepare('SELECT id, username, email, perms, active, pw_changed_at FROM admins WHERE id = ?');
    $st->execute([$_SESSION['admin_id']]);
    $a = $st->fetch();
    // Compte supprimé ou désactivé, ou mot de passe changé depuis la connexion : session fermée
    if (!$a || !(int)$a['active'] || (int)$a['pw_changed_at'] > (int)($_SESSION['admin_login_at'] ?? 0)) {
        return $logout();
    }
    $_SESSION['admin_seen'] = time();
    $a['perms_list'] = admin_perms($a);
    $cache = $a;
    return $a;
}

function admin_can(string $perm): bool
{
    $a = current_admin();
    return $a && in_array($perm, $a['perms_list'], true);
}

function require_admin(): array
{
    $admin = current_admin();
    if (!$admin) {
        redirect(admin_count() === 0 ? 'setup.php' : 'login.php');
    }
    return $admin;
}

/** Bloque la page si le compte n'a pas le droit demandé. */
function require_perm(string $perm): array
{
    $admin = require_admin();
    if (!in_array($perm, $admin['perms_list'], true)) {
        http_response_code(403);
        if (function_exists('admin_header')) {
            admin_header('Accès refusé');
            echo '<h1>Accès refusé</h1><p class="intro">Votre compte n’a pas le droit « ' . e(ADMIN_PERMS[$perm][0] ?? $perm)
                . ' ». Demandez-le à un administrateur.</p><p><a class="btn" href="index.php">← Tableau de bord</a></p>';
            admin_footer();
        } else {
            echo 'Accès refusé.';
        }
        exit;
    }
    return $admin;
}

function login_admin(int $id): void
{
    session_regenerate_id(true);
    $_SESSION['admin_id'] = $id;
    $_SESSION['admin_seen'] = time();
    $_SESSION['admin_login_at'] = time();
    db()->prepare('UPDATE admins SET last_login = ? WHERE id = ?')->execute([date('Y-m-d H:i:s'), $id]);
}

/** Change le mot de passe ; ferme les autres sessions de ce compte. */
function admin_set_password(int $id, string $password, bool $keepCurrentSession = false): void
{
    $now = time();
    db()->prepare('UPDATE admins SET password_hash = ?, pw_changed_at = ? WHERE id = ?')
        ->execute([password_hash($password, PASSWORD_DEFAULT), $now, $id]);
    db()->prepare('UPDATE password_resets SET used_at = ? WHERE admin_id = ? AND used_at IS NULL')->execute([date('Y-m-d H:i:s'), $id]);
    if ($keepCurrentSession && (int)($_SESSION['admin_id'] ?? 0) === $id) {
        session_regenerate_id(true);
        $_SESSION['admin_login_at'] = $now;
    }
}

/** Nombre de comptes actifs ayant le droit de gérer les utilisateurs (garde-fou). */
function admin_managers_count(?int $excludeId = null): int
{
    $n = 0;
    foreach (db()->query('SELECT id, perms, active FROM admins')->fetchAll() as $a) {
        if ((int)$a['active'] && (int)$a['id'] !== $excludeId && in_array('utilisateurs', admin_perms($a), true)) {
            $n++;
        }
    }
    return $n;
}

/* ---------- Jetons : mot de passe oublié et invitations ---------- */

/** Crée un lien à usage unique ; seul le hachage du jeton est stocké. */
function admin_create_token(int $adminId, string $purpose, int $validMinutes): string
{
    $raw = bin2hex(random_bytes(32));
    db()->prepare('INSERT INTO password_resets(admin_id, token_hash, purpose, expires_at, created_at) VALUES(?,?,?,?,?)')
        ->execute([$adminId, hash('sha256', $raw), $purpose, time() + $validMinutes * 60, date('Y-m-d H:i:s')]);
    return bk_url('admin/nouveau-mot-de-passe.php?t=' . $raw);
}

/** Jeton valide (non utilisé, non expiré, compte actif) → [reset, admin] ou null. */
function admin_find_token(string $raw): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $raw)) {
        return null;
    }
    $st = db()->prepare('SELECT * FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > ?');
    $st->execute([hash('sha256', $raw), time()]);
    $r = $st->fetch();
    if (!$r) {
        return null;
    }
    $st = db()->prepare('SELECT * FROM admins WHERE id = ? AND active = 1');
    $st->execute([$r['admin_id']]);
    $a = $st->fetch();
    return $a ? [$r, $a] : null;
}

function admin_send_link(array $admin, string $purpose): array
{
    $invite = $purpose === 'invite';
    $url = admin_create_token((int)$admin['id'], $purpose, $invite ? 48 * 60 : 60);
    [$subject, $html] = mail_from_template($invite ? 'invitation' : 'motdepasse', ['identifiant' => $admin['username'], 'lien' => $url],
        ['bouton' => '<p>' . mail_button($url, $invite ? 'Créer mon mot de passe' : 'Choisir un nouveau mot de passe') . '</p>']);
    $ok = send_mail($admin['email'], $subject, $html);
    return [$ok, $url];
}

/* ---------- Journal d'activité ---------- */

function admin_log(string $action): void
{
    $a = current_admin();
    try {
        db()->prepare('INSERT INTO admin_log(admin_id, username, at, ip, action) VALUES(?,?,?,?,?)')
            ->execute([$a['id'] ?? null, $a['username'] ?? '—', date('Y-m-d H:i:s'), ip_fingerprint(), mb_substr($action, 0, 500)]);
    } catch (Throwable) {
    }
}

function admin_log_as(array $admin, string $action): void
{
    db()->prepare('INSERT INTO admin_log(admin_id, username, at, ip, action) VALUES(?,?,?,?,?)')
        ->execute([$admin['id'], $admin['username'], date('Y-m-d H:i:s'), ip_fingerprint(), mb_substr($action, 0, 500)]);
}
