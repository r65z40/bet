<?php
/**
 * Réglage des notifications : pour chaque étape d'une réservation, qui est prévenu et comment.
 * Canaux : e-mail au client, e-mail à l'administration, notification sur l'appli (téléphones des administrateurs).
 * Chaque administrateur peut en plus couper, pour lui seul, certaines notifications de l'appli (Mon compte).
 */

const NOTIF_CHANNELS = [
    'client' => 'E-mail au client',
    'admin'  => 'E-mail à l’administration',
    'push'   => 'Notification sur l’appli',
];

/** Étape => [libellé, explication, [canal => activé par défaut]] ; un canal absent n'a pas de sens pour l'étape. */
const NOTIF_EVENTS = [
    'new_booking'      => ['Nouvelle réservation payée', 'Client : confirmation, facture, boutons agenda et documents. Vous : alerte avec le détail.', ['client' => 1, 'admin' => 1, 'push' => 1]],
    'conflict'         => ['Conflit d’agenda après paiement', 'Une réservation payée chevauche un événement de votre agenda : à vérifier rapidement.', ['admin' => 1, 'push' => 1]],
    'abandoned'        => ['Paiement abandonné', 'Un client a commencé une réservation sans aller au bout du paiement (le créneau est libéré).', ['admin' => 0, 'push' => 0]],
    'moved'            => ['Changement de date', 'Ancienne et nouvelle date, boutons pour mettre à jour l’agenda.', ['client' => 1, 'admin' => 0, 'push' => 0]],
    'cancelled'        => ['Annulation / remboursement', 'Montant remboursé et avoir.', ['client' => 1, 'admin' => 0, 'push' => 0]],
    'reminder'         => ['Rappel avant l’événement', 'Infos pratiques, adresse, documents (nombre de jours : Réglages › E-mails automatiques).', ['client' => 1]],
    'deposit_request'  => ['Demande de caution', 'Lien pour déposer l’empreinte bancaire, quelques jours avant l’arrivée.', ['client' => 1]],
    'deposit_held'     => ['Caution déposée', 'Le client a déposé l’empreinte bancaire.', ['admin' => 0, 'push' => 1]],
    'eve'              => ['Veille de l’événement', 'Récapitulatif pour préparer la salle : client, horaires, options, nombre de personnes.', ['admin' => 0, 'push' => 1]],
    'deposit_released' => ['Caution libérée', 'Rien n’a été débité.', ['client' => 1, 'admin' => 0, 'push' => 0]],
    'deposit_retained' => ['Retenue sur caution', 'Montant et motif de la retenue.', ['client' => 1, 'admin' => 0, 'push' => 0]],
    'review'           => ['Demande d’avis après l’événement', 'Lien vers vos avis Google (nombre de jours : Réglages › E-mails automatiques).', ['client' => 1]],
    'contact'          => ['Message du formulaire de contact', 'Un visiteur vous écrit depuis la page d’accueil. L’e-mail part toujours ; vous pouvez y répondre directement.', ['push' => 1]],
];

/** Anciens réglages réutilisés tels quels (compatibilité). */
const NOTIF_ALIASES = ['reminder.client' => 'bk_remind_enabled', 'review.client' => 'bk_review_enabled'];

function notif_key(string $event, string $channel): string
{
    return NOTIF_ALIASES[$event . '.' . $channel] ?? 'notif_' . $event . '_' . $channel;
}

/** L'étape doit-elle être notifiée sur ce canal ? */
function notif_on(string $event, string $channel): bool
{
    if (!isset(NOTIF_EVENTS[$event][2][$channel])) {
        return false;
    }
    $v = s(notif_key($event, $channel));
    return $v === '' ? (bool)NOTIF_EVENTS[$event][2][$channel] : $v === '1';
}

/** Adresses qui reçoivent les e-mails d'administration (plusieurs possibles, séparées par des virgules). */
function notify_emails(): array
{
    $out = [];
    foreach (preg_split('/[\s,;]+/', s('bk_notify_email')) as $a) {
        if (filter_var($a, FILTER_VALIDATE_EMAIL)) {
            $out[strtolower($a)] = $a;
        }
    }
    return array_values($out);
}

/**
 * Prévient l'administration d'une étape : e-mail simple et/ou notification de l'appli, selon les réglages.
 * $lines : lignes de détail ; $url : page de l'admin (relative au dossier admin).
 */
function admin_notify(string $event, string $title, array $lines, string $url = 'index.php', string $tag = ''): void
{
    if (!notif_on($event, 'admin') && !notif_on($event, 'push')) {
        return;
    }
    after_response(fn() => admin_notify_now($event, $title, $lines, $url, $tag), 'alerte ' . $event);
}

function admin_notify_now(string $event, string $title, array $lines, string $url, string $tag): void
{
    if (notif_on($event, 'admin') && ($to = notify_emails())) {
        $html = '';
        foreach ($lines as $l) {
            $html .= '<p style="margin:0 0 10px;line-height:1.5">' . e($l) . '</p>';
        }
        $html .= '<p style="margin:18px 0">' . mail_button(bk_url('admin/' . $url), 'Voir dans l’administration') . '</p>';
        foreach ($to as $addr) {
            send_mail($addr, $title . ' — ' . s('site_name'), mail_layout($title, $html));
        }
    }
    if (notif_on($event, 'push')) {
        push_notify('reservations', $title, implode(' · ', array_slice($lines, 0, 3)), $url, $tag !== '' ? $tag : $event, $event);
    }
}

/** Notifications de l'appli refusées par un administrateur. */
function admin_notif_off(array $admin): array
{
    return array_values(array_filter(explode(',', (string)($admin['notif_off'] ?? ''))));
}

/** Résumé court d'une réservation pour les notifications. */
function notif_booking_line(array $b): string
{
    return $b['first_name'] . ' ' . $b['last_name'] . ' · ' . $b['event_type'] . ', ' . (int)$b['guests'] . ' pers. · ' . bk_slot_label($b);
}

/** Tâche planifiée : récapitulatif la veille de chaque événement. */
function notify_eve_cron(): int
{
    if (!notif_on('eve', 'admin') && !notif_on('eve', 'push')) {
        return 0;
    }
    $now = bk_now();
    $st = db()->prepare("SELECT * FROM bookings WHERE status = 'confirmed' AND eve_notified_at IS NULL AND start_at > ? AND start_at <= ?");
    $st->execute([$now->format(BK_FMT), $now->modify('+30 hours')->format(BK_FMT)]);
    $n = 0;
    foreach ($st->fetchAll() as $b) {
        // une seule fois, même si deux tâches tournent en même temps
        $upd = db()->prepare('UPDATE bookings SET eve_notified_at = ? WHERE id = ? AND eve_notified_at IS NULL');
        $upd->execute([$now->format(BK_FMT), $b['id']]);
        if (!$upd->rowCount()) {
            continue;
        }
        $opts = array_map(fn($l) => $l['label'], array_filter(json_decode($b['lines_json'], true) ?: [], fn($l) => ($l['kind'] ?? '') !== 'rental'));
        $s = bk_dt($b['start_at']);
        admin_notify('eve', '📋 ' . ($s->format('Y-m-d') === $now->format('Y-m-d') ? 'Aujourd’hui' : 'Demain') . ' ' . $s->format('H\hi') . ' : ' . $b['first_name'] . ' ' . $b['last_name'], [
            $b['event_type'] . ', ' . (int)$b['guests'] . ' personnes · ' . bk_slot_label($b),
            $opts ? 'Options : ' . implode(', ', $opts) : 'Aucune option',
            'Contact : ' . $b['phone'] . ' · ' . $b['email'],
            'Caution : ' . (DEPOSIT_STATUSES[$b['deposit_status']][0] ?? '—'),
        ] + ($b['message'] !== '' ? [4 => 'Message du client : ' . $b['message']] : []), 'reservation.php?id=' . (int)$b['id'], 'veille-' . $b['id']);
        $n++;
    }
    return $n;
}
