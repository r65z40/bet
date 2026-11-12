<?php
/**
 * Réglages du système de réservation (modifiables dans Admin › Réservations › Réglages).
 * Les valeurs « default » ne servent qu'à l'initialisation.
 * Tous les prix sont saisis HT ; le site affiche et encaisse le TTC.
 */

const BOOKING_DEFAULT_CGV = <<<'TXT'
# 1. Objet
Les présentes conditions régissent la location de La Salle RN6, 71 C route d’Auxerre, 89380 Appoigny, réservée sur le site internet.

# 2. Réservation et paiement
La réservation est ferme dès le paiement intégral du prix TTC en ligne (carte bancaire ou PayPal). Un e-mail de confirmation et une facture sont envoyés au client.

# 3. Durée et horaires
Location de 24 heures (horaires indiqués lors de la réservation) ou location à l’heure, entre 8 h et 21 h, pour une durée minimum de 2 heures. Les horaires réservés doivent être respectés, installation et rangement compris.

# 4. Capacité
La capacité maximale de la salle est de 120 personnes assises. Le client s’engage à ne pas la dépasser.

# 5. Caution et état des lieux
Une caution de 2 × le montant du loyer HT est remise par chèque lors de l’état des lieux d’entrée. Elle est restituée après l’état des lieux de sortie, déduction faite des éventuelles dégradations, du ménage non effectué ou du matériel manquant.

# 6. Annulation par le client
Toute annulation doit être faite par e-mail. **Au moins 30 jours avant** la date : remboursement intégral du montant payé. **Entre 30 et 7 jours avant** : remboursement de 50 %. **Moins de 7 jours avant** : aucun remboursement.

# 7. Responsabilité et assurance
Le client est responsable de la salle, du matériel et de ses invités pendant toute la durée de la location. Il doit disposer d’une assurance responsabilité civile couvrant l’événement.

# 8. Règlement intérieur
Respect du voisinage, du volume sonore, de l’interdiction de fumer à l’intérieur et des consignes de sécurité. La salle est rendue rangée et propre.

# 9. Données personnelles
Les informations demandées servent uniquement à la gestion de la réservation et à la facturation. Elles sont conservées pendant la durée légale de conservation des pièces comptables.
TXT;

const BOOKING_SETTINGS = [
    'regles' => [
        'title' => 'Horaires & règles',
        'fields' => [
            'bk_day_start'        => ['label' => 'Location à la journée : heure d’arrivée et de départ', 'default' => '10:00', 'type' => 'time', 'help' => 'Ex. 10:00 = du jour J 10 h au lendemain 10 h (ou 2 / 3 jours plus tard).'],
            'bk_max_days'         => ['label' => 'Location à la journée : durées proposées', 'type' => 'select', 'default' => '3', 'options' => ['1' => '24 h uniquement', '2' => '24 h ou 48 h', '3' => '24 h, 48 h ou 72 h']],
            'bk_hour_open'        => ['label' => 'Location à l’heure : ouverture', 'default' => '08:00', 'type' => 'time'],
            'bk_hour_close'       => ['label' => 'Location à l’heure : fermeture (fin au plus tard)', 'default' => '21:00', 'type' => 'time'],
            'bk_hour_step'        => ['label' => 'Location à l’heure : pas des horaires', 'default' => '60', 'type' => 'select', 'options' => ['30' => 'Toutes les 30 minutes', '60' => 'Toutes les heures']],
            'bk_min_hours'        => ['label' => 'Durée minimum à l’heure (heures)', 'default' => '2', 'type' => 'number'],
            'bk_buffer_hours'     => ['label' => 'Temps mort entre deux locations (heures)', 'default' => '2', 'type' => 'number', 'help' => 'S’applique aussi aux événements de votre agenda Google et aux blocages.'],
            'bk_min_notice_hours' => ['label' => 'Délai minimum avant le début (heures)', 'default' => '48', 'type' => 'number'],
            'bk_max_months'       => ['label' => 'Réservation possible jusqu’à (mois à l’avance)', 'default' => '18', 'type' => 'number'],
            'bk_max_guests'       => ['label' => 'Nombre maximum de personnes', 'default' => '120', 'type' => 'number'],
            'bk_event_types'      => ['label' => 'Types d’événement proposés (un par ligne)', 'type' => 'textarea', 'default' => "Mariage\nAnniversaire\nBaptême / communion\nCocktail / vin d’honneur\nRepas de famille\nSéminaire / réunion professionnelle\nFormation / conférence\nAutre"],
        ],
    ],
    'tarifs' => [
        'title' => 'Tarifs',
        'fields' => [
            'bk_price_1' => ['label' => 'Lundi — prix de la journée (24 h) HT (€)', 'default' => '250', 'type' => 'number'],
            'bk_price_2' => ['label' => 'Mardi — prix de la journée (24 h) HT (€)', 'default' => '250', 'type' => 'number'],
            'bk_price_3' => ['label' => 'Mercredi — prix de la journée (24 h) HT (€)', 'default' => '300', 'type' => 'number'],
            'bk_price_4' => ['label' => 'Jeudi — prix de la journée (24 h) HT (€)', 'default' => '250', 'type' => 'number'],
            'bk_price_5' => ['label' => 'Vendredi — prix de la journée (24 h) HT (€)', 'default' => '300', 'type' => 'number'],
            'bk_price_6' => ['label' => 'Samedi — prix de la journée (24 h) HT (€)', 'default' => '350', 'type' => 'number'],
            'bk_price_7' => ['label' => 'Dimanche — prix de la journée (24 h) HT (€)', 'default' => '350', 'type' => 'number'],
            'bk_discount_2d' => ['label' => 'Remise pour une location de 48 h (%)', 'default' => '0', 'type' => 'number', 'help' => '48 h = prix des 2 jours, moins cette remise.'],
            'bk_discount_3d' => ['label' => 'Remise pour une location de 72 h (%)', 'default' => '0', 'type' => 'number'],
            'bk_hour_first_price' => ['label' => 'Location à l’heure : prix de la durée minimum (2 h), TTC', 'default' => '150', 'type' => 'number'],
            'bk_hour_extra_price' => ['label' => 'Location à l’heure : prix de la 1re heure supplémentaire, TTC', 'default' => '65', 'type' => 'number'],
            'bk_hour_degress'     => ['label' => 'Dégressivité : baisse du prix de chaque heure supplémentaire suivante (%)', 'default' => '5', 'type' => 'number',
                'help' => 'Ex. 65 € puis 5 % : 3e heure 65 €, 4e heure 61,75 €, 5e heure 58,66 €… Prix arrondis à l’euro. Voir la simulation ci-dessous.'],
            'bk_hour_cap'      => ['label' => 'Plafond de la location à l’heure', 'type' => 'select', 'default' => '1', 'options' => ['1' => 'Jamais plus cher que la journée (24 h) du même jour', '0' => 'Pas de plafond']],
            'bk_vat_rate'      => ['label' => 'Taux de TVA (%)', 'default' => '20', 'type' => 'number'],
            'bk_asso_discount' => ['label' => 'Remise association à but non lucratif (%)', 'default' => '15', 'type' => 'number'],
            'bk_deposit_text'  => ['label' => 'Caution (texte affiché au client)', 'type' => 'textarea', 'default' => 'Une caution de 2 × le montant du loyer HT est demandée par chèque lors de l’état des lieux d’entrée. Elle est restituée après l’état des lieux de sortie.'],
        ],
    ],
    'annulation' => [
        'title' => 'Annulation & conditions',
        'fields' => [
            'bk_cancel_rules' => ['label' => 'Remboursement en cas d’annulation', 'type' => 'textarea', 'default' => "30 = 100\n7 = 50",
                'help' => 'Une règle par ligne « au moins X jours avant = % remboursé ». En dessous du plus petit délai : 0 %. Utilisé pour proposer le montant du remboursement dans l’admin.'],
            'bk_cgv' => ['label' => 'Conditions générales de location (page publique)', 'type' => 'textarea', 'default' => BOOKING_DEFAULT_CGV,
                'help' => 'Texte à faire relire par votre comptable ou un juriste. **gras** autorisé. Les lignes commençant par « # » deviennent des titres.'],
        ],
    ],
    'paiement' => [
        'title' => 'Paiement (Stripe & PayPal)',
        'fields' => [
            'bk_stripe_enabled'  => ['label' => 'Carte bancaire (Stripe)', 'type' => 'select', 'default' => '0', 'options' => ['1' => 'Activé', '0' => 'Désactivé']],
            'bk_stripe_secret'   => ['label' => 'Stripe — clé secrète', 'type' => 'secret', 'default' => '', 'help' => 'Stripe › Développeurs › Clés API › « Clé secrète » (sk_test_… pour tester, sk_live_… en production).'],
            'bk_stripe_webhook'  => ['label' => 'Stripe — secret du webhook', 'type' => 'secret', 'default' => '', 'help' => 'Stripe › Développeurs › Webhooks › Ajouter un endpoint : l’adresse est indiquée en haut de cette page, événement « checkout.session.completed ». Copiez ensuite le « Secret de signature » (whsec_…).'],
            'bk_paypal_enabled'  => ['label' => 'PayPal', 'type' => 'select', 'default' => '0', 'options' => ['1' => 'Activé', '0' => 'Désactivé']],
            'bk_paypal_mode'     => ['label' => 'PayPal — mode', 'type' => 'select', 'default' => 'sandbox', 'options' => ['sandbox' => 'Test (sandbox)', 'live' => 'Production (vrais paiements)']],
            'bk_paypal_client'   => ['label' => 'PayPal — Client ID', 'type' => 'text', 'default' => '', 'help' => 'developer.paypal.com › Apps & Credentials › votre application (Live ou Sandbox selon le mode).'],
            'bk_paypal_secret'   => ['label' => 'PayPal — Secret', 'type' => 'secret', 'default' => ''],
        ],
    ],
    'emails' => [
        'title' => 'E-mails',
        'fields' => [
            'bk_notify_email' => ['label' => 'Adresse qui reçoit les alertes « nouvelle réservation »', 'type' => 'email', 'default' => 'lasallern6@gmail.com'],
            'bk_mail_method'  => ['label' => 'Méthode d’envoi', 'type' => 'select', 'default' => 'smtp', 'options' => [
                'smtp' => 'Serveur SMTP (recommandé : Gmail, OVH, autre)',
                'php'  => 'Fonction mail() de l’hébergeur (sans réglage, souvent classé en spam)',
            ]],
            'bk_smtp_preset'  => ['label' => 'Fournisseur', 'type' => 'select', 'default' => 'gmail', 'options' => [
                'gmail' => 'Gmail (smtp.gmail.com)',
                'ovh'   => 'Adresse e-mail OVH (ssl0.ovh.net)',
                'autre' => 'Autre serveur SMTP',
            ], 'help' => 'Choisir un fournisseur préremplit le serveur, le port et le chiffrement.'],
            'bk_smtp_host'    => ['label' => 'Serveur SMTP', 'default' => 'smtp.gmail.com'],
            'bk_smtp_port'    => ['label' => 'Port', 'default' => '587', 'type' => 'number'],
            'bk_smtp_secure'  => ['label' => 'Chiffrement', 'type' => 'select', 'default' => 'tls', 'options' => ['tls' => 'STARTTLS (port 587)', 'ssl' => 'SSL/TLS (port 465)', 'none' => 'Aucun (déconseillé)']],
            'bk_smtp_user'    => ['label' => 'Identifiant SMTP (en général l’adresse e-mail complète)', 'default' => 'lasallern6@gmail.com', 'help' => 'Laisser vide seulement si votre serveur n’exige pas d’identification.'],
            'bk_smtp_pass'    => ['label' => 'Mot de passe SMTP', 'type' => 'secret', 'default' => '',
                'help' => 'Gmail : un « mot de passe d’application » (compte Google › Sécurité › Validation en deux étapes › Mots de passe des applications), pas votre mot de passe habituel. OVH : le mot de passe de la boîte e-mail.'],
            'bk_mail_from'    => ['label' => 'Nom de l’expéditeur', 'default' => 'La Salle RN6'],
            'bk_mail_from_addr' => ['label' => 'Adresse d’expédition (facultatif)', 'type' => 'email', 'default' => '',
                'help' => 'Vide = l’identifiant SMTP. Gmail et OVH n’acceptent en général que l’adresse du compte ou un alias déclaré.'],
        ],
    ],
    'automatique' => [
        'title' => 'E-mails automatiques',
        'fields' => [
            'bk_remind_enabled' => ['label' => 'Rappel avant l’événement', 'type' => 'select', 'default' => '1', 'options' => ['1' => 'Activé', '0' => 'Désactivé']],
            'bk_remind_days'    => ['label' => 'Envoyé combien de jours avant ?', 'type' => 'number', 'default' => '7'],
            'bk_practical_info' => ['label' => 'Infos pratiques (insérées dans le rappel)', 'type' => 'textarea', 'default' => "**Clés et état des lieux** : rendez-vous à l’heure d’arrivée pour la remise des clés et l’état des lieux d’entrée.\n**Parking** : grand parking public juste à côté de la salle.\n**Avant de partir** : salle rangée, poubelles triées et sorties, lumières éteintes.\n**Voisinage** : merci de modérer le volume sonore après 22 h et à la sortie."],
            'bk_review_enabled' => ['label' => 'Demande d’avis après l’événement', 'type' => 'select', 'default' => '1', 'options' => ['1' => 'Activée', '0' => 'Désactivée']],
            'bk_review_days'    => ['label' => 'Envoyée combien de jours après la fin ?', 'type' => 'number', 'default' => '1'],
            'bk_review_url'     => ['label' => 'Lien « Laisser un avis Google »', 'type' => 'url', 'default' => '',
                'help' => 'Profil d’établissement Google (recherchez « La Salle RN6 » connecté à votre compte) › « Demander des avis » › copier le lien. Vide = lien des avis saisi dans Textes › Avis clients.'],
        ],
    ],
    'societe' => [
        'title' => 'Société (factures)',
        'fields' => [
            'bk_company_name'    => ['label' => 'Dénomination', 'default' => 'La Salle RN6'],
            'bk_company_form'    => ['label' => 'Forme juridique et capital (ex. SAS au capital de 1 000 €)', 'default' => ''],
            'bk_company_address' => ['label' => 'Adresse du siège', 'type' => 'textarea', 'default' => "71 C route d’Auxerre\n89380 Appoigny"],
            'bk_company_siren'   => ['label' => 'SIREN / SIRET', 'default' => '131 463 846'],
            'bk_company_rcs'     => ['label' => 'RCS (ville)', 'default' => ''],
            'bk_company_vat'     => ['label' => 'N° de TVA intracommunautaire', 'default' => 'FR26131463846'],
            'bk_invoice_footer'  => ['label' => 'Mentions en bas de facture', 'type' => 'textarea', 'default' => 'Facture acquittée. Pas d’escompte pour paiement anticipé. En cas de retard de paiement : pénalités au taux légal et indemnité forfaitaire de 40 € pour frais de recouvrement (clients professionnels).'],
        ],
    ],
    'agenda' => [
        'title' => 'Agenda Google',
        'fields' => [
            'bk_gcal_url' => ['label' => 'Lecture — adresse secrète au format iCal de l’agenda', 'type' => 'secret', 'default' => '',
                'help' => 'Google Agenda (sur ordinateur) › Paramètres › votre agenda › « Intégrer l’agenda » › « Adresse secrète au format iCal ». Chaque événement que vous y ajoutez bloque les réservations (avec le temps mort).'],
            'bk_gcal_calendar_id' => ['label' => 'Écriture — ID de l’agenda', 'default' => '',
                'help' => 'Même page « Intégrer l’agenda » › « ID de l’agenda » (ex. abc123@group.calendar.google.com, ou votre adresse Gmail pour l’agenda principal).'],
            'bk_gcal_sa' => ['label' => 'Écriture — clé du compte de service Google (contenu du fichier JSON)', 'type' => 'secret', 'default' => '',
                'help' => 'Voir le guide ci-dessus. Collez tout le contenu du fichier .json téléchargé.'],
            'bk_gcal_details' => ['label' => 'Détails des réservations dans l’agenda', 'type' => 'select', 'default' => 'full', 'options' => [
                'full' => 'Complets : nom, téléphone, e-mail, montant, lien vers la fiche',
                'minimal' => 'Sans coordonnées ni montant : nom, type d’événement et nombre de personnes (pour partager l’agenda)',
            ], 'help' => 'Choisissez « Sans coordonnées » si vous partagez l’agenda avec d’autres personnes. Pour réécrire les événements existants, utilisez ensuite « Tout resynchroniser ».'],
            'bk_cal_split' => ['label' => 'Affichage des locations de plus d’un jour', 'type' => 'select', 'default' => '1', 'options' => [
                '1' => 'Un bloc par jour (10 h → minuit, puis minuit → 10 h) : remplit la colonne de chaque jour',
                '0' => 'Un seul événement (affiché en bande « journée entière » en haut)',
            ]],
        ],
    ],
];

const BOOKING_DEFAULT_OPTIONS = [
    ['label' => 'Écran 98 pouces + sonorisation', 'description' => 'Écran grand format, sono et micros sans fil.', 'price' => 150, 'unit' => 'forfait'],
    ['label' => 'Vaisselle', 'description' => 'Assiettes, verres et couverts pour tous vos invités.', 'price' => 2, 'unit' => 'personne'],
    ['label' => 'Préparation & mise en place', 'description' => 'Installation des tables et chaises selon votre plan.', 'price' => 250, 'unit' => 'forfait'],
];

const OPTION_UNITS = ['forfait' => 'forfait', 'personne' => 'par personne'];

const BOOKING_STATUSES = [
    'pending'   => ['En attente de paiement', 'gray'],
    'confirmed' => ['Confirmée (payée)', 'green'],
    'cancelled' => ['Annulée', 'red'],
    'expired'   => ['Paiement abandonné', 'gray'],
];
