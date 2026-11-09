<?php
/**
 * Carte de tous les contenus modifiables depuis l'administration.
 * Les valeurs « default » reprennent le contenu du site d'origine et ne servent
 * qu'à l'initialisation de la base.
 *
 * Mise en forme possible dans les textes : **texte en gras**
 */

const SETTINGS_SCHEMA = [
    'general' => [
        'title' => 'Informations générales',
        'fields' => [
            'site_name'      => ['label' => 'Nom de la salle', 'default' => 'La Salle RN6'],
            'site_location'  => ['label' => 'Sous-titre du logo', 'default' => 'Appoigny · Yonne'],
            'contact_email'  => ['label' => 'E-mail de contact', 'default' => 'lasallern6@gmail.com', 'type' => 'email'],
            'address'        => ['label' => 'Adresse', 'default' => '71 C route d’Auxerre, 89380 Appoigny'],
            'maps_url'       => ['label' => 'Lien Google Maps', 'default' => 'https://www.google.com/maps/search/?api=1&query=71%20C%20route%20d%27Auxerre%2C%2089380%20Appoigny', 'type' => 'url'],
            'facebook_url'   => ['label' => 'Lien Facebook', 'default' => 'https://www.facebook.com/profile.php?id=61594089617332', 'type' => 'url'],
            'salles1001_url' => ['label' => 'Lien 1001Salles', 'default' => 'https://www.1001salles.com/mariage/69601-la-salle-rn6.aspx', 'type' => 'url'],
            'mail_subject'   => ['label' => 'Objet des e-mails « Demander une réservation »', 'default' => 'Demande de location - La Salle RN6'],
            'mail_body'      => ['label' => 'Corps pré-rempli des e-mails', 'type' => 'textarea', 'default' => "Bonjour,\n\nJe souhaite obtenir des informations pour la location de La Salle RN6.\n\nType d'événement :\nDate souhaitée :\nNombre de personnes :\n\nMerci."],
            'footer_text'    => ['label' => 'Pied de page', 'default' => 'La Salle RN6 — Appoigny, Yonne.'],
            'footer_tagline' => ['label' => 'Pied de page (droite)', 'default' => 'Événements privés & professionnels — Appoigny.'],
        ],
    ],
    'seo' => [
        'title' => 'Référencement (Google)',
        'fields' => [
            'site_url'         => ['label' => 'Adresse du site', 'default' => 'https://lasallern6.fr', 'type' => 'url'],
            'meta_title'       => ['label' => 'Titre de la page d’accueil', 'default' => 'Location de salle à Appoigny près d’Auxerre | La Salle RN6'],
            'meta_description' => ['label' => 'Description Google', 'type' => 'textarea', 'default' => 'Louez La Salle RN6 à Appoigny, près d’Auxerre : jusqu’à 120 personnes assises, parking, cuisine, mobilier, mariages, anniversaires et séminaires.'],
            'og_title'         => ['label' => 'Titre de partage (Facebook…)', 'default' => 'La Salle RN6 | Location de salle à Appoigny près d’Auxerre'],
            'og_description'   => ['label' => 'Description de partage', 'type' => 'textarea', 'default' => 'Salle de réception à Appoigny pour mariages, anniversaires, baptêmes, cocktails, séminaires et réceptions, jusqu’à 120 personnes assises.'],
            'capacity'         => ['label' => 'Capacité max. (nombre, pour Google)', 'default' => '120', 'type' => 'number'],
        ],
    ],
    'hero' => [
        'title' => 'Accueil (bandeau principal)',
        'fields' => [
            'hero_eyebrow' => ['label' => 'Petit titre', 'default' => 'Salle des fêtes & séminaires'],
            'hero_title_1' => ['label' => 'Titre — partie bleue', 'default' => 'La Salle'],
            'hero_title_2' => ['label' => 'Titre — partie rouge', 'default' => 'RN6'],
            'hero_tagline' => ['label' => 'Accroche', 'default' => 'Un lieu pour vos moments importants.'],
            'hero_lead'    => ['label' => 'Texte d’introduction', 'type' => 'textarea', 'default' => 'Pour votre **location de salle à Appoigny**, aux portes d’Auxerre, découvrez un espace événementiel moderne, modulable et refait à neuf pour vos mariages, anniversaires, baptêmes, cocktails, séminaires et réceptions.'],
        ],
    ],
    'salle' => [
        'title' => 'Sections « La salle », « Équipements », « Mobilier »',
        'fields' => [
            'salle_title'        => ['label' => 'La salle — titre', 'default' => 'Location de salle à Appoigny pour vos événements'],
            'salle_subtitle'     => ['label' => 'La salle — sous-titre', 'type' => 'textarea', 'default' => 'Des espaces fonctionnels pour accueillir vos invités, organiser votre réception et faciliter le travail de vos prestataires.'],
            'equip_title'        => ['label' => 'Équipements — titre', 'default' => 'Des équipements déjà sur place'],
            'equip_subtitle'     => ['label' => 'Équipements — sous-titre', 'default' => 'Pour simplifier l’organisation de vos événements privés comme professionnels.'],
            'mobilier_title'     => ['label' => 'Mobilier — titre', 'default' => 'Équipements & mobilier inclus'],
            'mobilier_subtitle'  => ['label' => 'Mobilier — sous-titre', 'type' => 'textarea', 'default' => 'Le mobilier et plusieurs équipements sont disponibles sur place pour faciliter l’organisation de votre événement.'],
            'mobilier_panel1'    => ['label' => 'Mobilier — titre colonne 1', 'default' => 'Mobilier'],
            'mobilier_panel2'    => ['label' => 'Mobilier — titre colonne 2', 'default' => 'Vaisselle & matériel de cuisine'],
            'plan_cta_eyebrow'   => ['label' => 'Encart plan — petit titre', 'default' => 'Plan de la salle'],
            'plan_cta_title'     => ['label' => 'Encart plan — titre', 'default' => 'Découvrez l’agencement de La Salle RN6'],
            'plan_cta_text'      => ['label' => 'Encart plan — texte', 'type' => 'textarea', 'default' => 'Consultez le plan détaillé de la salle, du bar, de la cuisine et des espaces de stockage sur une page dédiée.'],
        ],
    ],
    'tarifs' => [
        'title' => 'Section « Tarifs »',
        'fields' => [
            'tarifs_title'         => ['label' => 'Titre', 'default' => 'Tarifs de location'],
            'tarifs_subtitle'      => ['label' => 'Sous-titre', 'default' => 'Tarifs hors taxes. La TVA au taux de 20 % s’ajoute au montant total.'],
            'tarifs_badge'         => ['label' => 'Pastille', 'default' => 'Tarifs HT · TVA 20 % non incluse'],
            'tarifs_options_title' => ['label' => 'Titre du bloc options', 'default' => 'Options & services'],
            'tarifs_foot'          => ['label' => 'Phrase sous les tarifs (l’e-mail est ajouté à la fin)', 'default' => 'Pour toute demande particulière, réservation ou précision sur les conditions de location, contactez-nous à'],
        ],
    ],
    'acces' => [
        'title' => 'Sections « Accès », « Photos », « Contact »',
        'fields' => [
            'acces_title'     => ['label' => 'Accès — titre', 'default' => 'Une salle de réception proche d’Auxerre'],
            'acces_subtitle'  => ['label' => 'Accès — sous-titre', 'default' => 'Une localisation pratique pour vos invités venant d’Auxerre, de l’Yonne ou par l’autoroute A6.'],
            'acces_panel1'    => ['label' => 'Accès — titre colonne 1', 'default' => 'Adresse & accès'],
            'acces_panel2'    => ['label' => 'Accès — titre colonne 2', 'default' => 'Pour quels événements ?'],
            'photos_title'    => ['label' => 'Photos — titre', 'default' => 'Galerie photos'],
            'photos_subtitle' => ['label' => 'Photos — sous-titre', 'default' => 'Découvrez La Salle RN6, ses espaces, son mobilier et ses équipements.'],
            'contact_eyebrow' => ['label' => 'Contact — petit titre', 'default' => 'Réservations & renseignements'],
            'contact_title'   => ['label' => 'Contact — titre', 'default' => 'Une date en tête ? Contactez-nous.'],
            'contact_text'    => ['label' => 'Contact — texte', 'type' => 'textarea', 'default' => 'Pour toute demande de tarif, de disponibilité ou d’information sur la location de La Salle RN6, contactez-nous directement par e-mail.'],
        ],
    ],
    'plan' => [
        'title' => 'Page « Plan de la salle »',
        'fields' => [
            'plan_meta_title' => ['label' => 'Titre Google', 'default' => 'Plan de La Salle RN6 à Appoigny | Salle de réception'],
            'plan_meta_desc'  => ['label' => 'Description Google', 'type' => 'textarea', 'default' => 'Consultez le plan de La Salle RN6 à Appoigny : salle principale de 125 m², bar, cuisine et espaces de stockage près d’Auxerre.'],
            'plan_title'      => ['label' => 'Titre', 'default' => 'Plan de la salle'],
            'plan_lead'       => ['label' => 'Introduction', 'type' => 'textarea', 'default' => 'Consultez l\'agencement de La Salle RN6 avec la salle principale, le bar, la cuisine et les espaces de stockage.'],
            'plan_hint'       => ['label' => 'Légende sous le plan', 'default' => 'Plan du rez-de-chaussée après travaux — document fourni à titre informatif.'],
        ],
    ],
    'resa' => [
        'title' => 'Page « Réserver en ligne »',
        'fields' => [
            'resa_meta_title'     => ['label' => 'Titre Google', 'default' => 'Réserver en ligne | La Salle RN6'],
            'resa_title'          => ['label' => 'Titre', 'default' => 'Réservez La Salle RN6'],
            'resa_lead'           => ['label' => 'Introduction', 'type' => 'textarea', 'default' => 'Consultez les disponibilités de la salle et choisissez directement votre créneau de location.'],
            'resa_notice_title'   => ['label' => 'Encadré — titre', 'default' => 'Réservation de la salle'],
            'resa_notice'         => ['label' => 'Encadré — texte', 'type' => 'textarea', 'default' => 'Choisissez une date disponible dans l’agenda ci-dessous puis suivez les étapes de réservation.'],
            'supersaas_account'   => ['label' => 'SuperSaaS — compte', 'default' => '641423:lasallern6', 'help' => 'Ne modifier que si vous changez de compte SuperSaaS.'],
            'supersaas_schedule'  => ['label' => 'SuperSaaS — agenda', 'default' => '851719:Location_Salle_RN6'],
        ],
    ],
];

/**
 * Listes éditables. « fields » = colonnes utilisées parmi icon / title / text / value.
 */
const ITEM_GROUPS = [
    'hero_facts'    => ['label' => 'Accueil — 4 points forts', 'menu' => 'contenus', 'fields' => ['icon' => 'Icône', 'title' => 'Titre', 'text' => 'Détail']],
    'salle_cards'   => ['label' => 'La salle — cartes', 'menu' => 'contenus', 'fields' => ['icon' => 'Icône', 'title' => 'Titre', 'text' => 'Texte']],
    'equipements'   => ['label' => 'Équipements sur place', 'menu' => 'contenus', 'fields' => ['title' => 'Titre', 'text' => 'Texte']],
    'mobilier'      => ['label' => 'Mobilier (colonne 1)', 'menu' => 'contenus', 'fields' => ['text' => 'Élément']],
    'vaisselle'     => ['label' => 'Vaisselle & cuisine (colonne 2)', 'menu' => 'contenus', 'fields' => ['text' => 'Élément']],
    'acces'         => ['label' => 'Adresse & accès', 'menu' => 'contenus', 'fields' => ['text' => 'Élément']],
    'evenements'    => ['label' => 'Pour quels événements ?', 'menu' => 'contenus', 'fields' => ['text' => 'Élément']],
    'plan_facts'    => ['label' => 'Page plan — surfaces', 'menu' => 'contenus', 'fields' => ['title' => 'Valeur', 'text' => 'Libellé']],
    'tarif_jours'   => ['label' => 'Tarifs par jour', 'menu' => 'tarifs', 'fields' => ['title' => 'Jour', 'value' => 'Prix']],
    'tarif_infos'   => ['label' => 'Infos tarifaires (caution…)', 'menu' => 'tarifs', 'fields' => ['title' => 'Titre', 'text' => 'Texte']],
    'tarif_options' => ['label' => 'Options & services', 'menu' => 'tarifs', 'fields' => ['text' => 'Prestation', 'value' => 'Prix']],
];

const DEFAULT_ITEMS = [
    'hero_facts' => [
        ['icon' => '👥', 'title' => 'Jusqu’à 120 personnes', 'text' => 'assises'],
        ['icon' => '📍', 'title' => 'À 5 min de l’A6', 'text' => 'Sortie n°19'],
        ['icon' => '✦', 'title' => 'Salle moderne', 'text' => 'modulable et refaite à neuf'],
        ['icon' => 'P', 'title' => 'Grand parking', 'text' => 'accessible à proximité'],
    ],
    'salle_cards' => [
        ['icon' => '◫', 'title' => 'Grande salle principale', 'text' => '**125 m²** avec une capacité confortable de **jusqu’à 120 personnes assises**.'],
        ['icon' => '◇', 'title' => 'Espace bar dédié', 'text' => '**11,77 m²**, idéal pour les vins d’honneur, réceptions et pauses café.'],
        ['icon' => '⌂', 'title' => 'Cuisine / office traiteur', 'text' => '**8,56 m²**, équipée pour le maintien au frais et le réchauffage des plats.'],
        ['icon' => '▤', 'title' => 'Espaces de stockage', 'text' => '**26,34 m²** au total, répartis en deux réserves de 17,11 m² et 9,23 m².'],
        ['icon' => '♿', 'title' => 'Accessibilité PMR', 'text' => 'Rampes d’accès extérieures et sanitaires adaptés séparés.'],
        ['icon' => 'P', 'title' => 'Stationnement', 'text' => 'Grand parking public immédiatement accessible pour les invités.'],
    ],
    'equipements' => [
        ['title' => '98 pouces', 'text' => 'Écran grand format pour présentations, séminaires et vidéos.'],
        ['title' => 'Audio pro', 'text' => 'Sonorisation intégrée avec micros sans fil.'],
        ['title' => 'Wi‑Fi', 'text' => 'Connexion haut débit gratuite dans tout l’établissement.'],
        ['title' => 'Confort', 'text' => 'Climatisation réversible et chauffage par pompe à chaleur.'],
    ],
    'mobilier' => [
        ['text' => '**144 chaises**'],
        ['text' => '**20 tables pliantes** de 180 cm de longueur'],
        ['text' => '**Tables rondes**'],
        ['text' => '**Mange-debout**'],
    ],
    'vaisselle' => [
        ['text' => '**Vaisselle disponible sur place** : assiettes, verres et couverts'],
        ['text' => '**Lave-vaisselle** à disposition'],
        ['text' => '**Machine à glaçons**'],
    ],
    'acces' => [
        ['text' => '**71 C route d’Auxerre, 89380 Appoigny**'],
        ['text' => 'À environ **5 minutes** de la sortie n°19 Auxerre Nord de l’A6 via la D606.'],
        ['text' => 'Grand parking public accessible à proximité immédiate.'],
    ],
    'evenements' => [
        ['text' => 'Mariages, anniversaires et baptêmes'],
        ['text' => 'Cocktails et réceptions'],
        ['text' => 'Séminaires et événements professionnels'],
        ['text' => 'Espace moderne et modulable, refait à neuf'],
    ],
    'plan_facts' => [
        ['title' => '125 m²', 'text' => 'Salle principale'],
        ['title' => '11,77 m²', 'text' => 'Espace bar'],
        ['title' => '8,56 m²', 'text' => 'Cuisine'],
        ['title' => '26,34 m²', 'text' => 'Stockage / réserves'],
    ],
    'tarif_jours' => [
        ['title' => 'Lundi', 'value' => '250 €'],
        ['title' => 'Mardi', 'value' => '250 €'],
        ['title' => 'Mercredi', 'value' => '300 €'],
        ['title' => 'Jeudi', 'value' => '250 €'],
        ['title' => 'Vendredi', 'value' => '300 €'],
        ['title' => 'Samedi', 'value' => '350 €'],
        ['title' => 'Dimanche', 'value' => '350 €'],
    ],
    'tarif_infos' => [
        ['title' => 'Caution pour la salle :', 'text' => '2 × le montant du loyer HT.'],
        ['title' => 'Eau & électricité :', 'text' => 'comprises dans le prix de location.'],
    ],
    'tarif_options' => [
        ['text' => 'Location Ecran + sono', 'value' => '150 € HT'],
        ['text' => 'Location de vaisselle', 'value' => '2 € HT / personne'],
        ['text' => 'Préparation & mise en place des tables et chaises selon vos plans', 'value' => '250 € HT'],
        ['text' => 'Remise association à but non lucratif', 'value' => '− 15 %'],
        ['text' => 'Parking', 'value' => 'Gratuit + accès PMR'],
    ],
];

/** Types de clics suivis dans les statistiques. */
const CLICK_KINDS = [
    'reserver' => 'Clic « Réserver en ligne »',
    'email'    => 'Clic e-mail / demande de réservation',
    'copy'     => 'Copie de l’adresse e-mail',
    'plan'     => 'Clic « Voir le plan »',
    'maps'     => 'Clic itinéraire Google Maps',
    'facebook' => 'Clic Facebook',
    '1001'     => 'Clic 1001Salles',
    'photo'    => 'Ouverture d’une photo',
];
