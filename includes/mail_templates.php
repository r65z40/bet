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
        'body' => "Bonjour {prenom},\n\nMerci pour votre réservation : votre paiement a bien été reçu et **la salle vous est réservée**.\n\n{recapitulatif}\n\n{boutons}\n\n{caution}\n\n**Adresse :** {adresse} — itinéraire : {itineraire}\n\nUne question ? Répondez simplement à cet e-mail.\n\nConditions de location : {conditions}",
        'vars' => ['prenom', 'nom', 'date', 'reservation', 'reference', 'evenement', 'personnes', 'total', 'adresse', 'itineraire', 'conditions', 'lien'],
        'blocks' => ['recapitulatif', 'boutons', 'caution'],
    ],
    'annulation' => [
        'label' => 'Annulation (client)',
        'title' => 'Réservation annulée',
        'subject' => 'Annulation de votre réservation — {site}',
        'body' => "Bonjour {prenom},\n\nVotre réservation **{reference}** ({reservation}) a été **annulée**.\n\n{remboursement}\n\n{bouton}",
        'vars' => ['prenom', 'nom', 'date', 'reservation', 'reference', 'montant_rembourse', 'lien'],
        'blocks' => ['remboursement', 'bouton'],
    ],
    'alerte' => [
        'label' => 'Alerte « nouvelle réservation » (vous)',
        'title' => 'Nouvelle réservation payée',
        'subject' => 'Nouvelle réservation : {date} — {nom} — {total}',
        'body' => "{attention}\n\n**{prenom} {nom}** {societe}\n{email_client} · {telephone}\n\n{recapitulatif}\n\n{message_client}\n\n{bouton}",
        'vars' => ['prenom', 'nom', 'societe', 'email_client', 'telephone', 'date', 'reservation', 'reference', 'evenement', 'personnes', 'total'],
        'blocks' => ['attention', 'recapitulatif', 'message_client', 'bouton'],
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
    'bouton' => 'bouton principal', 'remboursement' => 'phrase sur le remboursement', 'attention' => 'alerte si conflit d’agenda', 'message_client' => 'message laissé par le client',
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
