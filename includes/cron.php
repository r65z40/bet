<?php
/**
 * Tâches automatiques : rappels J-7, demandes d'avis J+1, paiements abandonnés,
 * envois groupés en attente, purge des données anciennes.
 *
 * Lancées par la tâche planifiée OVH (cron.php, toutes les heures) ou, à défaut,
 * au plus une fois par heure après l'affichage d'une page (sans ralentir le visiteur).
 */

const CRON_EVERY = 3300; // ~55 min entre deux passages

function cron_due(): bool
{
    return time() - (int)s('_cron_last') >= CRON_EVERY;
}

/** Lance les tâches (verrou : jamais deux exécutions simultanées). Retourne le compte rendu. */
function run_scheduled_tasks(bool $force = false): array
{
    if (!$force && !cron_due()) {
        return ['skipped' => true];
    }
    $lock = fopen(DATA_DIR . '/.cron.lock', 'c');
    if (!flock($lock, LOCK_EX | LOCK_NB)) {
        fclose($lock);
        return ['skipped' => true];
    }
    $report = ['rappels' => 0, 'avis' => 0, 'expires' => 0, 'emails_groupes' => 0, 'purges' => 0, 'erreurs' => []];
    try {
        save_setting('_cron_last', (string)time());
        settings(true);
        $now = bk_now();

        // 1. Paiements abandonnés : créneaux libérés
        $before = (int)db()->query("SELECT COUNT(*) FROM bookings WHERE status = 'expired'")->fetchColumn();
        bk_expire_pending();
        $report['expires'] = (int)db()->query("SELECT COUNT(*) FROM bookings WHERE status = 'expired'")->fetchColumn() - $before;

        // 2. Rappel avant l'événement (infos pratiques)
        if (s('bk_remind_enabled') !== '0') {
            $days = max(1, (int)bk_num('bk_remind_days', 7));
            $st = db()->prepare("SELECT * FROM bookings WHERE status = 'confirmed' AND reminder_sent_at IS NULL
                AND start_at > ? AND start_at <= ? AND paid_at <= ?");
            $st->execute([$now->modify('+12 hours')->format(BK_FMT), $now->modify("+$days days")->format(BK_FMT), $now->modify('-1 day')->format(BK_FMT)]);
            foreach ($st->fetchAll() as $b) {
                if (bk_mail_reminder($b)) {
                    $report['rappels']++;
                } else {
                    $report['erreurs'][] = 'rappel ' . $b['ref'] . ' : ' . mail_last_error();
                }
                db()->prepare('UPDATE bookings SET reminder_sent_at = ? WHERE id = ?')->execute([$now->format(BK_FMT), $b['id']]);
            }
        }

        // 3. Demande d'avis après l'événement
        if (s('bk_review_enabled') !== '0') {
            $delay = max(0, (int)bk_num('bk_review_days', 1));
            $st = db()->prepare("SELECT * FROM bookings WHERE status = 'confirmed' AND review_sent_at IS NULL AND end_at <= ? AND end_at >= ?");
            $st->execute([$now->modify("-$delay days")->format(BK_FMT), $now->modify('-' . ($delay + 7) . ' days')->format(BK_FMT)]);
            foreach ($st->fetchAll() as $b) {
                if (!is_opted_out($b['email'])) {
                    if (bk_mail_review($b)) {
                        $report['avis']++;
                    } else {
                        $report['erreurs'][] = 'avis ' . $b['ref'] . ' : ' . mail_last_error();
                    }
                }
                db()->prepare('UPDATE bookings SET review_sent_at = ? WHERE id = ?')->execute([$now->format(BK_FMT), $b['id']]);
            }
        }

        // 4. Envois groupés encore en attente (continuent même si la page de suivi a été fermée)
        foreach (db()->query("SELECT id FROM mail_campaigns WHERE status = 'sending'")->fetchAll(PDO::FETCH_COLUMN) as $cid) {
            $before = mailing_counts((int)$cid);
            $after = mailing_process((int)$cid, 30);
            $report['emails_groupes'] += $after['sent'] - $before['sent'];
        }

        // 5. Confidentialité : réservations jamais payées supprimées au bout de 6 mois
        $old = db()->prepare("SELECT id FROM bookings WHERE paid_at IS NULL AND status IN ('expired','cancelled') AND created_at < ?");
        $old->execute([$now->modify('-6 months')->format(BK_FMT)]);
        foreach ($old->fetchAll(PDO::FETCH_COLUMN) as $id) {
            db()->prepare('DELETE FROM booking_log WHERE booking_id = ?')->execute([$id]);
            db()->prepare('DELETE FROM bookings WHERE id = ?')->execute([$id]);
            $report['purges']++;
        }
        stats_purge();
    } catch (Throwable $e) {
        $report['erreurs'][] = $e->getMessage();
    } finally {
        save_setting('_cron_report', json_encode($report, JSON_UNESCAPED_UNICODE));
        flock($lock, LOCK_UN);
        fclose($lock);
    }
    return $report;
}

/** Secours sans tâche planifiée : exécution après l'envoi de la page au visiteur. */
function cron_on_shutdown(): void
{
    if (!cron_due() || PHP_SAPI === 'cli') {
        return;
    }
    register_shutdown_function(function () {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request(); // la page est déjà affichée : le visiteur n'attend pas
        }
        ignore_user_abort(true);
        try {
            run_scheduled_tasks();
        } catch (Throwable) {
        }
    });
}

/* ---------- E-mails automatiques ---------- */

function bk_mail_reminder(array $b): bool
{
    [$vars, $blocks] = mail_booking_data($b);
    $s = bk_dt($b['start_at']);
    $e = bk_dt($b['end_at']);
    $vars['jours'] = (string)max(1, (int)ceil(($s->getTimestamp() - time()) / 86400));
    $vars['arrivee'] = fr_date($s) . ' à ' . $s->format('H\hi');
    $vars['depart'] = fr_date($e) . ' à ' . $e->format('H\hi');
    $blocks['infos_pratiques'] = s('bk_practical_info') !== '' ? '<div style="background:#f4f6f8;border-radius:8px;padding:12px 16px;margin:0 0 14px">' . render_mail_paragraphs(s('bk_practical_info')) . '</div>' : '';
    $blocks['bouton'] = '<p>' . mail_button(bk_client_url($b), 'Ma réservation') . '</p>';
    [$subject, $html] = mail_from_template('rappel', $vars, $blocks);
    $ok = send_mail($b['email'], $subject, $html, s('contact_email'));
    bk_log((int)$b['id'], $ok ? 'Rappel J-' . (int)bk_num('bk_remind_days', 7) . ' envoyé' : 'ÉCHEC du rappel avant l’événement');
    return $ok;
}

function review_url(): string
{
    return s('bk_review_url') !== '' ? s('bk_review_url') : s('google_reviews_url');
}

function bk_mail_review(array $b): bool
{
    [$vars, $blocks] = mail_booking_data($b);
    $url = review_url();
    $blocks['bouton_avis'] = $url !== ''
        ? '<p style="text-align:center;margin:20px 0">' . mail_button($url, '★ Laisser un avis Google') . '</p>'
        : '<p>Répondez simplement à cet e-mail pour nous dire ce que vous en avez pensé.</p>';
    $vars['lien_avis'] = $url;
    [$subject, $html] = mail_from_template('avis', $vars, $blocks);
    $unsub = optout_url($b['email']);
    $html = str_replace('</td></tr><tr><td style="background:#fbf8f1', '<p style="font-size:12px;color:#617083;margin-top:20px"><a href="' . e($unsub) . '" style="color:#617083">Ne plus recevoir ces e-mails</a></p></td></tr><tr><td style="background:#fbf8f1', $html);
    $ok = send_mail($b['email'], $subject, $html, s('contact_email'), ['List-Unsubscribe: <' . $unsub . '>', 'List-Unsubscribe-Post: List-Unsubscribe=One-Click']);
    bk_log((int)$b['id'], $ok ? 'Demande d’avis envoyée' : 'ÉCHEC de la demande d’avis');
    return $ok;
}

/** Paragraphes simples (texte saisi dans l'admin) pour un e-mail. */
function render_mail_paragraphs(string $text): string
{
    return mail_render_text($text, []);
}
