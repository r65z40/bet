<?php
/**
 * Marketing et relances (RGPD) :
 *  - consentement newsletter (case non cochée, preuve conservée : date, formulaire, texte) ;
 *  - relances des devis non réservés et des paiements abandonnés (liées à la demande du visiteur) ;
 *  - après l'événement : remerciement, photos et code de parrainage ; 11 mois après : « Vous refaites la fête ? » ;
 *  - offres « dernière minute » : remise sur les dates libres proches + e-mail aux abonnés et clients ;
 *  - effacement des prospects sans activité depuis 3 ans.
 * Les anciens clients reçoivent les offres sur la même prestation (« soft opt-in », informés à la réservation) ;
 * tout e-mail commercial comporte un lien de désinscription, et une adresse désinscrite n'en reçoit plus.
 */

const NEWSLETTER_CONSENT = 'J’accepte de recevoir les dates libres et les offres de la salle par e-mail (1 à 2 e-mails par mois au plus, désinscription en un clic).';
const MARKETING_GAP_DAYS = 14;     // un destinataire reçoit au plus une offre groupée toutes les 2 semaines
const PROSPECT_RETENTION_YEARS = 3;

function mk_on(string $key): bool
{
    return s($key) !== '0';
}

/** Enregistre un contact ; avec $consent, la preuve du consentement (et lève une ancienne désinscription). */
function marketing_subscribe(string $email, string $firstName, string $source, bool $consent): void
{
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return;
    }
    $now = bk_now()->format(BK_FMT);
    try {
        db()->prepare('INSERT INTO contacts(email, first_name, source, created_at, last_activity_at) VALUES(?,?,?,?,?)
                ON CONFLICT(email) DO UPDATE SET last_activity_at = excluded.last_activity_at, first_name = CASE WHEN excluded.first_name <> \'\' THEN excluded.first_name ELSE first_name END')
            ->execute([$email, mb_substr(trim($firstName), 0, 60), $source, $now, $now]);
        if ($consent) {
            db()->prepare('UPDATE contacts SET consent = 1, consent_at = ?, consent_source = ?, consent_text = ? WHERE email = ?')
                ->execute([$now, $source, NEWSLETTER_CONSENT, $email]);
            db()->prepare('DELETE FROM mail_optout WHERE email = ?')->execute([$email]); // nouveau consentement explicite
        }
    } catch (Throwable) {
    }
}

/** Pied de page commercial : lien de désinscription (et en-têtes pour le bouton « Se désabonner » des messageries). */
function marketing_footer(string $email, string $why): array
{
    $url = optout_url($email);
    return ['<p style="margin-top:28px;font-size:12px;color:#617083">' . e($why) . ' <a href="' . e($url) . '" style="color:#617083">Ne plus recevoir ces e-mails</a>.</p>',
        ['List-Unsubscribe: <' . $url . '>', 'List-Unsubscribe-Post: List-Unsubscribe=One-Click']];
}

/** Envoie un modèle d'e-mail commercial (lien de désinscription ajouté). */
function marketing_send(string $tpl, string $email, array $vars, array $blocks, string $why): bool
{
    if (is_opted_out($email)) {
        return false;
    }
    [$foot, $headers] = marketing_footer($email, $why);
    [$subject, $html] = mail_from_template($tpl, $vars, $blocks, $foot);
    return send_mail($email, $subject, $html, mail_reply_to(), $headers);
}

/** Ajoute ?src=… à un lien du site (origine des réservations, pour les statistiques). */
function mk_link(string $url, string $src): string
{
    return $url . (str_contains($url, '?') ? '&' : '?') . 'src=' . rawurlencode($src);
}

/** Dates libres (location à la journée) entre deux dates, sur certains jours de la semaine. */
function marketing_free_days(DateTimeImmutable $from, DateTimeImmutable $to, array $weekdays): array
{
    $minStart = bk_now()->modify('+' . (int)bk_num('bk_min_notice_hours', 48) . ' hours');
    try {
        $dayBusy = bk_busy_for_days(bk_busy($from->setTime(0, 0), $to->modify('+3 days')));
    } catch (Throwable) {
        return [];
    }
    $out = [];
    for ($d = $from->setTime(0, 0); $d <= $to; $d = $d->modify('+1 day')) {
        if (!in_array((int)$d->format('N'), $weekdays, true)) {
            continue;
        }
        [$s, $e] = bk_day_slot($d);
        if ($s >= $minStart && !bk_conflict_day($s->getTimestamp(), $e->getTimestamp(), $dayBusy)) {
            $out[] = $d;
        }
    }
    return $out;
}

/** « Il reste 2 samedis libres en juin » — seulement si c'est vrai et que c'est peu. */
function marketing_scarcity(DateTimeImmutable $date): string
{
    static $j = ['', 'lundis', 'mardis', 'mercredis', 'jeudis', 'vendredis', 'samedis', 'dimanches'];
    static $m = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $first = $date->modify('first day of this month');
    $n = count(marketing_free_days(max($first, bk_now()), $date->modify('last day of this month'), [(int)$date->format('N')]));
    if ($n < 1 || $n > 3) {
        return '';
    }
    return 'Il ne reste que ' . $n . ' ' . ($n > 1 ? $j[(int)$date->format('N')] : rtrim($j[(int)$date->format('N')], 's')) . ' libre' . ($n > 1 ? 's' : '') . ' en ' . $m[(int)$date->format('n')] . '.';
}

/* ---------- Parrainage ---------- */

/** Code de parrainage du client (créé au besoin) : remise pour ses proches. */
function marketing_referral_code(array $b): ?array
{
    $pct = (float)bk_num('mk_parrain_percent', 10);
    if ($pct <= 0) {
        return null;
    }
    $email = strtolower(trim($b['email']));
    $st = db()->prepare("SELECT * FROM promo_codes WHERE referral = 1 AND lower(owner_email) = ? AND active = 1 ORDER BY id DESC LIMIT 1");
    $st->execute([$email]);
    if ($p = $st->fetch()) {
        return $p;
    }
    $code = promo_generate('AMI');
    db()->prepare("INSERT INTO promo_codes(code, kind, value, balance, max_uses, valid_from, valid_to, min_total, label, active, created_at, owner_email, referral)
            VALUES(?, 'percent', ?, 0, 0, '', ?, 0, ?, 1, ?, ?, 1)")
        ->execute([$code, min(50, $pct), bk_now()->modify('+18 months')->format('Y-m-d'), 'Parrainage de ' . $b['first_name'] . ' ' . mb_substr($b['last_name'], 0, 1) . '.', bk_now()->format(BK_FMT), $email]);
    return promo_find($code);
}

/** Réservation payée avec un code de parrainage : avoir pour le parrain (une seule fois par réservation). */
function marketing_referral_reward(array $b): void
{
    $reward = (float)bk_num('mk_parrain_reward', 30);
    if ($b['promo_code'] === '' || (int)$b['referral_rewarded'] || $reward <= 0) {
        return;
    }
    $p = promo_find($b['promo_code']);
    if (!$p || !(int)$p['referral'] || $p['owner_email'] === '' || strtolower($p['owner_email']) === strtolower(trim($b['email']))) {
        return;
    }
    $st = db()->prepare('UPDATE bookings SET referral_rewarded = 1 WHERE id = ? AND referral_rewarded = 0');
    $st->execute([$b['id']]);
    if (!$st->rowCount()) {
        return;
    }
    $code = promo_generate('MERCI');
    db()->prepare("INSERT INTO promo_codes(code, kind, value, balance, max_uses, valid_from, valid_to, min_total, label, active, created_at, owner_email)
            VALUES(?, 'gift', ?, ?, 0, '', ?, 0, ?, 1, ?, ?)")
        ->execute([$code, $reward, $reward, bk_now()->modify('+2 years')->format('Y-m-d'), 'Avoir parrainage (' . $b['ref'] . ')', bk_now()->format(BK_FMT), $p['owner_email']]);
    bk_log((int)$b['id'], 'Parrainage : avoir de ' . money2($reward) . ' créé pour ' . $p['owner_email'] . ' (' . $code . ')');
    $name = db()->prepare('SELECT first_name FROM bookings WHERE lower(email) = ? ORDER BY id DESC LIMIT 1');
    $name->execute([strtolower($p['owner_email'])]);
    $vars = ['prenom' => (string)($name->fetchColumn() ?: ''), 'ami' => $b['first_name'], 'montant' => money2($reward), 'code' => $code,
        'validite' => bk_now()->modify('+2 years')->format('d/m/Y'), 'lien' => mk_link(bk_url('reservation.php'), 'parrainage')];
    $blocks = ['bouton' => '<p style="margin:18px 0">' . mail_button($vars['lien'], 'Réserver avec mon avoir') . '</p>'];
    [$subject, $html] = mail_from_template('parrainage_merci', $vars, $blocks);
    send_mail($p['owner_email'], $subject, $html, mail_reply_to()); // e-mail lié à son parrainage : pas commercial
}

/** À la confirmation d'une réservation : parrainage, contact client, relances arrêtées. */
function marketing_on_confirmed(array $b): void
{
    try {
        if ($b['source'] === '' && !empty($b['abandon_r1_at'])) { // payée après une relance « réservation non finalisée »
            db()->prepare("UPDATE bookings SET source = 'relance-paiement' WHERE id = ?")->execute([$b['id']]);
        }
        marketing_subscribe($b['email'], $b['first_name'], 'client', false);
        // plus de relance de devis pour ce client
        db()->prepare('UPDATE quotes SET relance1_at = COALESCE(relance1_at, ?), relance2_at = COALESCE(relance2_at, ?) WHERE lower(email) = lower(?)')
            ->execute([bk_now()->format(BK_FMT), bk_now()->format(BK_FMT), $b['email']]);
    } catch (Throwable $e) {
        bk_log((int)$b['id'], 'Marketing : ' . $e->getMessage());
    }
}

/* ---------- Tâche planifiée ---------- */

/** Le client a-t-il une réservation payée créée depuis cette date ? (on ne relance pas un client qui a réservé) */
function marketing_booked_since(string $email, string $since): bool
{
    $st = db()->prepare("SELECT 1 FROM bookings WHERE lower(email) = lower(?) AND paid_at IS NOT NULL AND status = 'confirmed' AND created_at >= ? LIMIT 1");
    $st->execute([$email, $since]);
    return (bool)$st->fetchColumn();
}

function marketing_cron(array &$errors): array
{
    $r = ['relances_devis' => 0, 'relances_paiement' => 0, 'apres_evenement' => 0, 'anniversaires' => 0, 'derniere_minute' => 0, 'prospects_effaces' => 0];
    $now = bk_now();
    $nowS = $now->format(BK_FMT);
    $mark = fn(string $sql, array $p) => db()->prepare($sql)->execute($p);

    // 5. Devis non réservés : 3 jours après, puis 2 jours avant la fin de validité
    if (mk_on('mk_devis_relance')) {
        $st = db()->prepare("SELECT q.* FROM quotes q LEFT JOIN bookings b ON b.id = q.booking_id
            WHERE (b.id IS NULL OR b.status <> 'confirmed') AND q.valid_until > ? AND (q.relance1_at IS NULL OR q.relance2_at IS NULL)");
        $st->execute([$nowS]);
        foreach ($st->fetchAll() as $q) {
            $second = $q['relance1_at'] !== null;
            $due = $second ? $now >= bk_dt($q['valid_until'])->modify('-2 days') && bk_dt($q['valid_until']) > bk_dt($q['relance1_at'])->modify('+3 days')
                : $now >= bk_dt($q['created_at'])->modify('+3 days');
            if (!$due) {
                continue;
            }
            $col = $second ? 'relance2_at' : 'relance1_at';
            $mark("UPDATE quotes SET $col = ? WHERE id = ?", [$nowS, $q['id']]);
            if (marketing_booked_since($q['email'], $q['created_at']) || bk_slot_error($q['mode'], bk_dt($q['start_at']), bk_dt($q['end_at']))) {
                continue; // déjà client depuis, ou la date n'est plus libre : pas de relance
            }
            $vars = ['prenom' => $q['first_name'], 'numero' => $q['number'], 'reservation' => devis_slot_label($q), 'total' => money2((float)$q['total_ttc']),
                'validite' => bk_dt($q['valid_until'])->format('d/m/Y'), 'lien' => devis_url($q)];
            $book = mk_link(bk_url('reservation.php?devis=' . rawurlencode($q['number']) . '&t=' . $q['token']), 'relance-devis');
            $blocks = ['bouton' => '<p style="margin:18px 0">' . mail_button($book, 'Réserver cette date') . mail_button(devis_url($q), 'Revoir le devis', false) . '</p>',
                'rarete' => ($sc = marketing_scarcity(bk_dt($q['start_at']))) !== '' ? '<p style="margin:0 0 14px;color:#8a5a00;font-weight:bold">⏳ ' . e($sc) . '</p>' : ''];
            if (marketing_send($second ? 'devis_relance2' : 'devis_relance', $q['email'], $vars, $blocks, 'Vous recevez cet e-mail suite à votre demande de devis.')) {
                $r['relances_devis']++;
            }
        }
    }

    // 6. Paiements abandonnés : 1 heure après, puis le lendemain (réservations de moins de 3 jours)
    if (mk_on('mk_abandon_relance')) {
        $st = db()->prepare("SELECT * FROM bookings WHERE paid_at IS NULL AND status IN ('pending', 'expired') AND created_at > ? AND (abandon_r1_at IS NULL OR abandon_r2_at IS NULL)");
        $st->execute([$now->modify('-3 days')->format(BK_FMT)]);
        foreach ($st->fetchAll() as $b) {
            if ($b['status'] === 'pending' && $b['hold_until'] && $b['hold_until'] > $nowS) {
                continue; // paiement peut-être encore en cours
            }
            $second = $b['abandon_r1_at'] !== null;
            if (!($second ? $now >= bk_dt($b['created_at'])->modify('+1 day') : $now >= bk_dt($b['created_at'])->modify('+1 hour'))) {
                continue;
            }
            $mark('UPDATE bookings SET ' . ($second ? 'abandon_r2_at' : 'abandon_r1_at') . ' = ? WHERE id = ?', [$nowS, $b['id']]);
            if (marketing_booked_since($b['email'], $b['created_at']) || bk_slot_error($b['mode'], bk_dt($b['start_at']), bk_dt($b['end_at']), (int)$b['id'])) {
                continue;
            }
            [$vars, $blocks] = mail_booking_data($b);
            $blocks['bouton'] = '<p style="margin:18px 0">' . mail_button(mk_link(bk_client_url($b), 'relance-paiement'), '🔒 Finaliser ma réservation') . '</p>';
            if (marketing_send($second ? 'paiement_relance2' : 'paiement_relance', $b['email'], $vars, $blocks, 'Vous recevez cet e-mail suite à votre réservation non finalisée.')) {
                $r['relances_paiement']++;
                bk_log((int)$b['id'], 'Relance « paiement non finalisé » envoyée' . ($second ? ' (2e)' : ''));
            }
        }
    }

    // 7. Après l'événement : remerciement, photos, code de parrainage
    if (mk_on('mk_post_event')) {
        $days = max(1, (int)bk_num('mk_post_days', 5));
        $st = db()->prepare("SELECT * FROM bookings WHERE status = 'confirmed' AND post_sent_at IS NULL AND end_at <= ? AND end_at >= ?
            AND COALESCE(deposit_status, '') <> 'retenue' AND no_review = 0");
        $st->execute([$now->modify("-$days days")->format(BK_FMT), $now->modify('-' . ($days + 10) . ' days')->format(BK_FMT)]);
        foreach ($st->fetchAll() as $b) {
            $mark('UPDATE bookings SET post_sent_at = ? WHERE id = ?', [$nowS, $b['id']]);
            $p = marketing_referral_code($b);
            [$vars, $blocks] = mail_booking_data($b);
            $vars['evenement'] = mb_strtolower(mb_substr($vars['evenement'], 0, 1)) . mb_substr($vars['evenement'], 1);
            $vars += ['code' => $p['code'] ?? '', 'remise' => rating_label((string)bk_num('mk_parrain_percent', 10)) . ' %', 'cadeau' => money2((float)bk_num('mk_parrain_reward', 30))];
            $blocks['parrainage'] = $p ? '<div style="background:#fff8e8;border:1px solid #f0dca8;border-radius:10px;padding:14px 16px;margin:0 0 16px">'
                . '<p style="margin:0 0 6px"><strong>🎁 Faites plaisir à vos proches</strong></p>'
                . '<p style="margin:0 0 8px">Avec votre code, ils ont <strong>−' . e($vars['remise']) . '</strong> sur leur location, et vous recevez <strong>' . e($vars['cadeau']) . '</strong> à valoir sur votre prochaine fête une fois leur événement passé.</p>'
                . '<p style="margin:0;font-size:20px;letter-spacing:.06em"><strong>' . e($p['code']) . '</strong></p></div>' : '';
            $blocks['bouton'] = '<p style="margin:18px 0">' . mail_button(mk_link(bk_url(''), 'apres-evenement'), 'Voir la salle') . '</p>';
            if (marketing_send('apres_evenement', $b['email'], $vars, $blocks, 'Vous recevez cet e-mail suite à votre location.')) {
                $r['apres_evenement']++;
                bk_log((int)$b['id'], 'E-mail de remerciement (photos, parrainage) envoyé');
            }
        }
    }

    // 8. 11 mois après : « Vous refaites la fête ? » (si pas déjà réservé depuis)
    if (mk_on('mk_anniv')) {
        $st = db()->prepare("SELECT * FROM bookings WHERE status = 'confirmed' AND anniv_sent_at IS NULL AND start_at <= ? AND start_at >= ?");
        $st->execute([$now->modify('-11 months')->format(BK_FMT), $now->modify('-12 months')->format(BK_FMT)]);
        foreach ($st->fetchAll() as $b) {
            $mark('UPDATE bookings SET anniv_sent_at = ? WHERE id = ?', [$nowS, $b['id']]);
            $again = db()->prepare("SELECT 1 FROM bookings WHERE lower(email) = lower(?) AND status = 'confirmed' AND start_at > ? LIMIT 1");
            $again->execute([$b['email'], $nowS]);
            if ($again->fetchColumn()) {
                continue; // a déjà une nouvelle date
            }
            $next = bk_dt($b['start_at'])->modify('+1 year')->setTime(0, 0);
            // même jour de la semaine, l'année suivante (ex. samedi → samedi le plus proche)
            $diff = (int)bk_dt($b['start_at'])->format('N') - (int)$next->format('N');
            $next = $next->modify(($diff > 3 ? $diff - 7 : ($diff < -3 ? $diff + 7 : $diff)) . ' days');
            $free = $next > $now && marketing_free_days($next, $next, [(int)$next->format('N')]);
            [$vars, $blocks] = mail_booking_data($b);
            $vars['evenement'] = mb_strtolower(mb_substr($vars['evenement'], 0, 1)) . mb_substr($vars['evenement'], 1);
            $vars['date_proposee'] = fr_date($next);
            $blocks['disponibilite'] = '<p style="margin:0 0 14px">' . ($free
                ? 'Bonne nouvelle : le <strong>' . e(fr_date($next)) . '</strong>, à la même période, la salle est <strong>encore libre</strong>.'
                : 'Les dates partent vite à cette période : jetez un œil au calendrier.') . '</p>';
            $blocks['bouton'] = '<p style="margin:18px 0">' . mail_button(mk_link(bk_url('reservation.php' . ($free ? '?date=' . $next->format('Y-m-d') : '')), 'relance-11-mois'), 'Voir les disponibilités') . '</p>';
            if (marketing_send('anniversaire', $b['email'], $vars, $blocks, 'Vous recevez cet e-mail car vous avez réservé la salle.')) {
                $r['anniversaires']++;
                bk_log((int)$b['id'], 'Relance « 11 mois après » envoyée');
            }
        }
    }

    // 9. Dernière minute : chaque lundi (une fois par semaine)
    if (mk_on('mk_lastminute') && s('mk_lastminute') === '1' && (int)$now->format('N') === 1 && (int)$now->format('G') >= 9 && s('_lm_week') !== $now->format('o-W')) {
        save_setting('_lm_week', $now->format('o-W'));
        try {
            $r['derniere_minute'] = marketing_lastminute_run();
        } catch (Throwable $e) {
            $errors[] = 'dernière minute : ' . $e->getMessage();
        }
    }

    // Parrainage : avoir offert au parrain une fois l'événement de l'ami passé (et non annulé)
    $st = db()->prepare("SELECT * FROM bookings WHERE status = 'confirmed' AND promo_code <> '' AND referral_rewarded = 0 AND end_at <= ? AND end_at >= ?");
    $st->execute([$nowS, $now->modify('-60 days')->format(BK_FMT)]);
    foreach ($st->fetchAll() as $b) {
        try {
            marketing_referral_reward($b);
        } catch (Throwable $e) {
            $errors[] = 'parrainage ' . $b['ref'] . ' : ' . $e->getMessage();
        }
    }

    // RGPD : prospects sans activité ni réservation depuis 3 ans
    if (s('_mk_purge') !== $now->format('Y-m')) {
        save_setting('_mk_purge', $now->format('Y-m'));
        $limit = $now->modify('-' . PROSPECT_RETENTION_YEARS . ' years')->format(BK_FMT);
        $st = db()->prepare("DELETE FROM contacts WHERE last_activity_at < ? AND email NOT IN (SELECT lower(email) FROM bookings WHERE paid_at IS NOT NULL AND created_at >= ?)");
        $st->execute([$limit, $limit]);
        $r['prospects_effaces'] = $st->rowCount();
        db()->prepare('DELETE FROM quotes WHERE created_at < ? AND booking_id IS NULL')->execute([$limit]);
    }
    settings(true);
    return $r;
}

/** Destinataires des offres : abonnés (consentement) + anciens clients, hors désinscrits et hors envoi récent. */
function marketing_audience(bool $respectGap = true): array
{
    $optout = array_flip(db()->query('SELECT email FROM mail_optout')->fetchAll(PDO::FETCH_COLUMN));
    $recent = $respectGap ? array_flip(db()->query("SELECT email FROM contacts WHERE last_marketing_at > " . db()->quote(bk_now()->modify('-' . MARKETING_GAP_DAYS . ' days')->format(BK_FMT)))->fetchAll(PDO::FETCH_COLUMN)) : [];
    $out = [];
    foreach (db()->query('SELECT email, first_name FROM contacts WHERE consent = 1')->fetchAll() as $c) {
        $out[$c['email']] = ['email' => $c['email'], 'first_name' => $c['first_name'], 'last_name' => '', 'booking' => null];
    }
    foreach (db()->query("SELECT email, first_name, last_name FROM bookings WHERE paid_at IS NOT NULL ORDER BY id DESC")->fetchAll() as $b) {
        $k = strtolower(trim($b['email']));
        $out[$k] ??= ['email' => $k, 'first_name' => $b['first_name'], 'last_name' => $b['last_name'], 'booking' => null];
    }
    return array_filter($out, fn($r, $k) => !isset($optout[$k]) && !isset($recent[$k]) && filter_var($k, FILTER_VALIDATE_EMAIL), ARRAY_FILTER_USE_BOTH);
}

/** Dates « dernière minute » : dates libres proches, remise appliquée (tarifs spéciaux automatiques). */
function marketing_lastminute_dates(): array
{
    $days = max(7, min(90, (int)bk_num('lm_days', 30)));
    $wd = array_map('intval', explode(',', s('lm_weekdays') ?: '5,6,7'));
    return marketing_free_days(bk_now(), bk_now()->modify("+$days days"), $wd);
}

/**
 * Applique la remise sur les dates libres (dates spéciales « Dernière minute », remplacées à chaque passage,
 * sans toucher aux dates spéciales créées à la main) et prépare l'e-mail. Retourne le nombre de destinataires.
 */
function marketing_lastminute_run(bool $send = true): int
{
    $pct = max(1, min(50, (float)bk_num('lm_percent', 15)));
    db()->exec('DELETE FROM special_prices WHERE auto = 1');
    $dates = [];
    $manual = db()->prepare('SELECT 1 FROM special_prices WHERE active = 1 AND auto = 0 AND ? BETWEEN date_from AND date_to');
    foreach (marketing_lastminute_dates() as $d) {
        $manual->execute([$d->format('Y-m-d')]);
        if ($manual->fetchColumn()) {
            continue; // date déjà au tarif spécial (fête, haute saison)
        }
        db()->prepare("INSERT INTO special_prices(label, date_from, date_to, kind, value, active, auto) VALUES('Dernière minute', ?, ?, 'percent', ?, 1, 1)")
            ->execute([$d->format('Y-m-d'), $d->format('Y-m-d'), -$pct]);
        $dates[] = $d;
    }
    if (!$dates || !$send) {
        return 0;
    }
    $lines = [];
    foreach ($dates as $d) {
        $lines[] = '• ' . ucfirst(fr_date($d)) . ' : ' . money(ttc(bk_day_price((int)$d->format('N')) * (1 - $pct / 100))) . ' au lieu de ' . money(ttc(bk_day_price((int)$d->format('N'))))
            . ' — ' . mk_link(bk_url('reservation.php?date=' . $d->format('Y-m-d')), 'derniere-minute');
    }
    $body = "Bonjour {prenom},\n\nQuelques dates sont encore libres dans les prochaines semaines : profitez de **−" . rating_label((string)$pct) . " %** sur la location de la salle !\n\n"
        . implode("\n", $lines) . "\n\nRéservation en ligne en quelques minutes, paiement sécurisé. Premier arrivé, premier servi !\n\nÀ bientôt,\n" . s('site_name');
    $rec = marketing_audience();
    if (!$rec) {
        return 0;
    }
    mailing_create('offres', '⚡ Dernière minute : −' . rating_label((string)$pct) . ' % sur nos dates libres — ' . s('site_name'), $body, $rec);
    $mark = db()->prepare("INSERT INTO contacts(email, first_name, source, created_at, last_activity_at, last_marketing_at) VALUES(?,?,'client',?,?,?)
        ON CONFLICT(email) DO UPDATE SET last_marketing_at = excluded.last_marketing_at");
    foreach ($rec as $k => $x) {
        $mark->execute([$k, $x['first_name'], bk_now()->format(BK_FMT), bk_now()->format(BK_FMT), bk_now()->format(BK_FMT)]);
    }
    return count($rec);
}
