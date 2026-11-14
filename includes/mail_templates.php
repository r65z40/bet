<?php
/**
 * Modèles d'e-mails modifiables dans Admin › E-mails clients › Modèles.
 * Chaque modèle : un objet et un texte avec {variables} ; une ligne contenant seulement
 * un {bloc} (récapitulatif, boutons…) est remplacée par l'élément mis en forme.
 * Apparence commune (couleurs, logo, pied de page) réglable au même endroit.
 */

const MAIL_TEMPLATES = [
    'confirmation' => [
        'label' => 'Confirmation de réservation (client)',
        'title' => 'Votre réservation est confirmée',
        'subject' => 'Réservation confirmée — {site} — {date}',
        'body' => "Bonjour {prenom},\n\nMerci pour votre réservation : votre paiement a bien été reçu et **la salle vous est réservée**.\n\n{recapitulatif}\n\n{boutons}\n\n{caution}\n\n{documents}\n\n**Adresse :** {adresse} — itinéraire : {itineraire}\n\nUne question ? Répondez simplement à cet e-mail.\n\nConditions de location : {conditions}",
        'vars' => ['prenom', 'nom', 'date', 'reservation', 'reference', 'evenement', 'personnes', 'total', 'adresse', 'itineraire', 'conditions', 'lien'],
        'blocks' => ['recapitulatif', 'boutons', 'caution', 'documents'],
    ],
    'annulation' => [
        'label' => 'Annulation (client)',
        'title' => 'Réservation annulée',
        'subject' => 'Annulation de votre réservation — {site}',
        'body' => "Bonjour {prenom},\n\nVotre réservation **{reference}** ({reservation}) a été **annulée**.\n\n{remboursement}\n\n{bouton}",
        'vars' => ['prenom', 'nom', 'date', 'reservation', 'reference', 'montant_rembourse', 'lien'],
        'blocks' => ['remboursement', 'bouton'],
    ],
    'deplacement' => [
        'label' => 'Changement de date (client)',
        'title' => 'Votre réservation a changé de date',
        'subject' => 'Nouvelle date pour votre réservation — {site} — {date}',
        'body' => "Bonjour {prenom},\n\nComme convenu, votre réservation **{reference}** a été **déplacée**.\n\n{changement}\n\nLe montant réglé reste inchangé. Pensez à mettre à jour votre agenda :\n\n{boutons}\n\nUne question ? Répondez simplement à cet e-mail.",
        'vars' => ['prenom', 'nom', 'date', 'reservation', 'ancienne_date', 'reference', 'evenement', 'personnes', 'total', 'lien'],
        'blocks' => ['changement', 'recapitulatif', 'boutons', 'caution'],
    ],
    'caution_demande' => [
        'label' => 'Caution : demande d’empreinte bancaire (client)',
        'title' => 'Votre caution',
        'subject' => 'Votre événement approche : déposez la caution — {site}',
        'body' => "Bonjour {prenom},\n\nVotre location à {site} approche ({reservation}).\n\nComme prévu dans les conditions de location, une caution de **{montant_caution}** est demandée sous forme d’**empreinte bancaire** : la somme est seulement bloquée sur votre carte, **rien n’est débité**. Elle est libérée automatiquement après l’état des lieux de sortie, sauf dégradation constatée.\n\nCela prend une minute, par carte bancaire, sur une page sécurisée :\n\n{bouton}\n\nVous pouvez aussi le faire sur place, à votre arrivée, depuis votre téléphone.\n\n{option_cheque}",
        'vars' => ['prenom', 'nom', 'reservation', 'reference', 'montant_caution', 'lien'],
        'blocks' => ['bouton', 'option_cheque'],
    ],
    'caution_liberee' => [
        'label' => 'Caution : empreinte libérée (client)',
        'title' => 'Caution libérée',
        'subject' => 'Votre caution a été libérée — {site}',
        'body' => "Bonjour {prenom},\n\nMerci pour votre location ! L’empreinte bancaire de caution de **{montant_caution}** a été **libérée** : rien n’a été débité. Selon votre banque, la somme peut encore apparaître quelques jours comme « en attente » avant de disparaître.\n\nÀ bientôt !",
        'vars' => ['prenom', 'nom', 'reservation', 'reference', 'montant_caution'],
        'blocks' => [],
    ],
    'caution_retenue' => [
        'label' => 'Caution : retenue après l’état des lieux (client)',
        'title' => 'Retenue sur caution',
        'subject' => 'Retenue sur votre caution — {site}',
        'body' => "Bonjour {prenom},\n\nSuite à l’état des lieux de sortie de votre location ({reservation}), une somme de **{montant_retenu}** a été retenue sur votre caution de {montant_caution}.\n\n**Motif :** {motif}\n\nLe reste de l’empreinte bancaire est libéré, sans aucun débit. Pour toute question, répondez simplement à cet e-mail.",
        'vars' => ['prenom', 'nom', 'reservation', 'reference', 'montant_caution', 'montant_retenu', 'motif'],
        'blocks' => [],
    ],
    'solde_demande' => [
        'label' => 'Acompte : demande du solde (client)',
        'title' => 'Le solde de votre location',
        'subject' => 'Votre événement approche : réglez le solde — {site}',
        'body' => "Bonjour {prenom},\n\nVotre location à {site} approche ({reservation}). Merci pour l’acompte de {montant_acompte} déjà réglé.\n\nComme prévu, le **solde de {montant_solde}** est à régler **avant le {date_limite}**, en ligne et en toute sécurité :\n\n{bouton}\n\nUne question ? Répondez simplement à cet e-mail.",
        'vars' => ['prenom', 'nom', 'reservation', 'reference', 'montant_solde', 'montant_acompte', 'date_limite', 'lien'],
        'blocks' => ['bouton'],
    ],
    'solde_relance' => [
        'label' => 'Acompte : relance du solde (client)',
        'title' => 'Rappel : solde à régler',
        'subject' => 'Rappel : solde de votre location à régler — {site}',
        'body' => "Bonjour {prenom},\n\nSauf erreur de notre part, le **solde de {montant_solde}** de votre location ({reservation}) n’est pas encore réglé : il était attendu avant le {date_limite}.\n\nVous pouvez le régler en une minute :\n\n{bouton}\n\nSi vous l’avez déjà réglé autrement (virement, chèque), ne tenez pas compte de ce message.",
        'vars' => ['prenom', 'nom', 'reservation', 'reference', 'montant_solde', 'date_limite', 'lien'],
        'blocks' => ['bouton'],
    ],
    'solde_recu' => [
        'label' => 'Acompte : solde reçu (client)',
        'title' => 'Solde reçu, merci !',
        'subject' => 'Solde reçu — {site}',
        'body' => "Bonjour {prenom},\n\nNous avons bien reçu le **solde de {montant_solde}** : votre location ({reservation}) est **entièrement réglée**. Merci !\n\nVotre facture à jour est disponible dans votre espace :\n\n{bouton}\n\nÀ bientôt !",
        'vars' => ['prenom', 'nom', 'reservation', 'reference', 'montant_solde', 'lien'],
        'blocks' => ['bouton'],
    ],
    'alerte' => [
        'label' => 'Alerte « nouvelle réservation » (vous)',
        'title' => 'Nouvelle réservation payée',
        'subject' => 'Nouvelle réservation : {date} — {nom} — {total}',
        'body' => "{attention}\n\n**{prenom} {nom}** {societe}\n{email_client} · {telephone}\n\n{recapitulatif}\n\n{message_client}\n\n{bouton}",
        'vars' => ['prenom', 'nom', 'societe', 'email_client', 'telephone', 'date', 'reservation', 'reference', 'evenement', 'personnes', 'total'],
        'blocks' => ['attention', 'recapitulatif', 'message_client', 'bouton'],
    ],
    'rappel' => [
        'label' => 'Rappel avant l’événement (J-7)',
        'title' => 'Votre événement approche',
        'subject' => 'J-{jours} : infos pratiques pour votre {evenement} — {site}',
        'body' => "Bonjour {prenom},\n\nVotre événement à {site} approche ! Voici les informations utiles pour que tout se passe bien.\n\n**Arrivée** : {arrivee}\n**Départ** : {depart}\n**Adresse** : {adresse} — itinéraire : {itineraire}\n\n{infos_pratiques}\n\n{caution}\n\n**À préparer :** votre attestation d’assurance responsabilité civile, à remettre avant la remise des clés.\n\n{documents}\n\nUne question ? Répondez simplement à cet e-mail.\n\n{bouton}",
        'vars' => ['prenom', 'nom', 'evenement', 'personnes', 'arrivee', 'depart', 'reservation', 'adresse', 'itineraire', 'jours', 'lien'],
        'blocks' => ['recapitulatif', 'infos_pratiques', 'caution', 'documents', 'bouton'],
    ],
    'avis' => [
        'label' => 'Demande d’avis après l’événement (J+1)',
        'title' => 'Merci pour votre venue !',
        'subject' => 'Merci {prenom} ! Votre avis sur {site} ?',
        'body' => "Bonjour {prenom},\n\nMerci d’avoir choisi {site} pour votre {evenement} ! Nous espérons que tout s’est bien passé.\n\nVotre avis compte beaucoup pour nous et aide les futurs clients à nous découvrir : il ne prend qu’une minute.\n\n{bouton_avis}\n\nMerci et à bientôt !",
        'vars' => ['prenom', 'nom', 'evenement', 'reservation', 'lien_avis'],
        'blocks' => ['bouton_avis'],
    ],
    'invitation' => [
        'label' => 'Invitation d’un nouvel administrateur',
        'title' => 'Bienvenue',
        'subject' => 'Votre accès à l’administration — {site}',
        'body' => "Bonjour {identifiant},\n\nUn compte d’administration du site {site} a été créé pour vous. Choisissez votre mot de passe pour l’activer :\n\n{bouton}\n\nCe lien est valable 48 heures et ne fonctionne qu’une fois.\nVotre identifiant : **{identifiant}**",
        'vars' => ['identifiant', 'lien'],
        'blocks' => ['bouton'],
    ],
    'motdepasse' => [
        'label' => 'Mot de passe oublié',
        'title' => 'Mot de passe oublié',
        'subject' => 'Réinitialisation de votre mot de passe — {site}',
        'body' => "Bonjour {identifiant},\n\nUne demande de réinitialisation du mot de passe de votre compte d’administration a été faite. Pour choisir un nouveau mot de passe :\n\n{bouton}\n\nCe lien est valable 1 heure et ne fonctionne qu’une fois. Si vous n’êtes pas à l’origine de cette demande, ignorez cet e-mail : votre mot de passe reste inchangé.",
        'vars' => ['identifiant', 'lien'],
        'blocks' => ['bouton'],
    ],
];

const MAIL_VAR_HELP = [
    'prenom' => 'prénom du client', 'nom' => 'nom du client', 'societe' => 'société (entre parenthèses)', 'email_client' => 'e-mail du client',
    'telephone' => 'téléphone du client', 'date' => 'date de début', 'reservation' => 'dates et horaires complets', 'reference' => 'référence',
    'evenement' => 'type d’événement', 'personnes' => 'nombre de personnes', 'total' => 'total TTC', 'montant_rembourse' => 'montant remboursé',
    'adresse' => 'adresse de la salle', 'itineraire' => 'lien Google Maps', 'conditions' => 'lien des conditions de location',
    'lien' => 'lien principal (espace client ou activation)', 'identifiant' => 'identifiant du compte', 'site' => 'nom de la salle',
    'recapitulatif' => 'tableau du détail et du total', 'boutons' => 'boutons espace client + ajout à l’agenda', 'caution' => 'encadré caution',
    'bouton' => 'bouton principal', 'arrivee' => 'date et heure d’arrivée', 'depart' => 'date et heure de départ', 'jours' => 'nombre de jours avant l’événement',
    'infos_pratiques' => 'infos pratiques (Réglages › E-mails automatiques)', 'bouton_avis' => 'bouton « Laisser un avis Google »', 'lien_avis' => 'lien vers vos avis Google', 'remboursement' => 'phrase sur le remboursement', 'ancienne_date' => 'ancienne date et horaires', 'montant_caution' => 'montant de la caution', 'montant_retenu' => 'montant retenu sur la caution', 'motif' => 'motif de la retenue', 'documents' => 'liens vers les documents (règlement intérieur, contrat…)', 'option_cheque' => 'phrase « vous préférez le chèque ? » (caution au choix)', 'montant_solde' => 'montant du solde', 'montant_acompte' => 'montant de l’acompte', 'date_limite' => 'date limite', 'changement' => 'encadré ancienne date → nouvelle date', 'attention' => 'alerte si conflit d’agenda', 'message_client' => 'message laissé par le client',
];

const MAIL_STYLE_DEFAULTS = [
    'mail_color_header' => '#071c35',
    'mail_color_accent' => '#d8a83f',
    'mail_show_logo' => '1',
    'mail_footer' => "{site} — {adresse}\n{email_contact}",
];

function mail_style(string $key): string
{
    $v = s($key);
    if (str_starts_with($key, 'mail_color_')) {
        return preg_match('/^#[0-9a-f]{6}$/i', $v) ? $v : MAIL_STYLE_DEFAULTS[$key];
    }
    return $v !== '' ? $v : MAIL_STYLE_DEFAULTS[$key];
}

/** Texte lisible (noir/blanc) sur une couleur de fond. */
function mail_contrast(string $hex): string
{
    [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
    return (0.299 * $r + 0.587 * $g + 0.114 * $b) > 150 ? '#152131' : '#ffffff';
}

function mail_tpl_subject(string $key): string
{
    return s('tpl_' . $key . '_subject') !== '' ? s('tpl_' . $key . '_subject') : MAIL_TEMPLATES[$key]['subject'];
}

function mail_tpl_body(string $key): string
{
    return s('tpl_' . $key . '_body') !== '' ? s('tpl_' . $key . '_body') : MAIL_TEMPLATES[$key]['body'];
}

/** Variables communes à tous les modèles. */
function mail_common_vars(): array
{
    return ['{site}' => s('site_name'), '{adresse}' => s('address'), '{email_contact}' => s('contact_email')];
}

/**
 * Texte de modèle → HTML : paragraphes, **gras**, liens cliquables ; une ligne réduite
 * à un {bloc} est remplacée par le HTML du bloc (vide = ligne retirée).
 */
function mail_render_text(string $text, array $vars, array $blocks = []): string
{
    $vars += mail_common_vars();
    $html = '';
    foreach (preg_split('/\n\s*\n/', str_replace("\r", '', trim($text))) as $para) {
        $lines = [];
        foreach (explode("\n", $para) as $line) {
            $t = trim($line);
            if (preg_match('/^\{([a-z_]+)\}$/', $t, $m) && array_key_exists($m[1], $blocks)) {
                if ($lines) {
                    $html .= '<p style="margin:0 0 14px;line-height:1.55">' . implode('<br>', $lines) . '</p>';
                    $lines = [];
                }
                $html .= $blocks[$m[1]];
                continue;
            }
            $l = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', e(strtr($line, $vars)));
            $lines[] = preg_replace_callback('#https?://[^\s<]+#', fn($u) => '<a href="' . $u[0] . '" style="color:#0b2b4f">' . $u[0] . '</a>', $l);
        }
        if (array_filter($lines, fn($l) => trim(strip_tags($l)) !== '')) {
            $html .= '<p style="margin:0 0 14px;line-height:1.55">' . implode('<br>', $lines) . '</p>';
        }
    }
    return $html;
}

/** Construit un e-mail à partir d'un modèle : [objet, html]. */
function mail_from_template(string $key, array $vars, array $blocks = []): array
{
    $tpl = MAIL_TEMPLATES[$key];
    $wrapped = [];
    foreach ($vars as $k => $v) {
        $wrapped['{' . $k . '}'] = (string)$v;
    }
    $subject = trim(preg_replace('/\s+/', ' ', strtr(mail_tpl_subject($key), $wrapped + mail_common_vars())));
    return [$subject, mail_layout($tpl['title'], mail_render_text(mail_tpl_body($key), $wrapped, $blocks))];
}

/** Variables et blocs d'une réservation, partagés par les modèles client et alerte. */
function mail_booking_data(array $b): array
{
    $vars = [
        'prenom' => $b['first_name'], 'nom' => $b['last_name'], 'societe' => $b['company'] ? '(' . $b['company'] . ')' : '',
        'email_client' => $b['email'], 'telephone' => $b['phone'], 'date' => fr_date(bk_dt($b['start_at']), false),
        'reservation' => bk_slot_label($b), 'reference' => $b['ref'], 'evenement' => $b['event_type'], 'personnes' => (string)(int)$b['guests'],
        'total' => money2((float)$b['total_ttc']), 'itineraire' => s('maps_url'), 'conditions' => bk_url('conditions.php'), 'lien' => bk_client_url($b),
    ];
    $blocks = [
        'recapitulatif' => mail_recap($b),
        'boutons' => '<p style="margin:18px 0">' . mail_button(bk_client_url($b), 'Ma réservation et ma facture')
            . mail_button(gcal_template_url($b), 'Ajouter à Google Agenda', false)
            . mail_button(bk_client_url($b) . '&ics=1', 'Ajouter à mon agenda (Apple, Outlook)', false) . '</p>',
        'caution' => '<p style="background:#fff8e8;border:1px solid #f0dca8;border-radius:8px;padding:12px 14px;font-size:14px"><strong>Caution :</strong> ' . e(s('bk_deposit_text')) . '</p>',
        'documents' => mail_documents_block(),
        'message_client' => $b['message'] !== '' ? '<p style="background:#f4f6f8;border-radius:8px;padding:12px 14px"><strong>Message du client :</strong><br>' . nl2br(e($b['message'])) . '</p>' : '',
    ];
    return [$vars, $blocks];
}

/** Réservation fictive pour l'aperçu des modèles. */
function mail_sample_booking(): array
{
    $start = bk_now()->modify('+30 days')->setTime(10, 0);
    return [
        'id' => 0, 'ref' => 'RN6-EXEMPLE', 'token' => str_repeat('0', 32), 'status' => 'confirmed', 'mode' => 'day', 'hours' => 24,
        'start_at' => $start->format(BK_FMT), 'end_at' => $start->modify('+1 day')->format(BK_FMT), 'guests' => 80, 'event_type' => 'Mariage',
        'first_name' => 'Camille', 'last_name' => 'Martin', 'email' => 'camille@exemple.fr', 'phone' => '06 12 34 56 78', 'company' => '',
        'message' => 'Le traiteur arrivera vers 9 h.', 'discount_ht' => 0, 'total_ht' => 600, 'vat' => 120, 'total_ttc' => 720,
        'lines_json' => json_encode([['label' => 'Location 24 h — exemple', 'total_ht' => 500], ['label' => 'Écran + sonorisation', 'total_ht' => 100]]),
    ];
}

/** Encadré des documents à lire (liens vers les PDF), vide s'il n'y en a pas. */
function mail_documents_block(): string
{
    $docs = documents(false, true);
    if (!$docs) {
        return '';
    }
    $h = '<div style="background:#f4f6f8;border-radius:8px;padding:12px 16px;margin:0 0 14px"><p style="margin:0 0 6px"><strong>📎 Documents à lire avant votre événement</strong></p>';
    foreach ($docs as $d) {
        $h .= '<p style="margin:4px 0"><a href="' . e(bk_url(document_src($d))) . '" style="color:#0b2b4f;font-weight:bold">' . e($d['title']) . '</a> <span style="color:#617083;font-size:13px">(' . e(document_meta($d)) . ')</span></p>';
    }
    return $h . '</div>';
}
