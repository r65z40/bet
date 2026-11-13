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
            'site_url'         => ['perm' => 'reglages', 'label' => 'Adresse du site', 'default' => 'https://lasallern6.fr', 'type' => 'url'],
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
            'mobilier_title'     => ['label' => 'Mobilier — titre', 'default' => 'Mobilier inclus & équipements'],
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
            'tarifs_subtitle'      => ['label' => 'Sous-titre', 'default' => 'Prix TTC (TVA 20 % incluse), pour une location de 24 h selon le jour d’arrivée.'],
            'tarifs_badge'         => ['label' => 'Pastille', 'default' => 'Prix TTC · paiement en ligne sécurisé'],
            'tarifs_options_title' => ['label' => 'Titre du bloc options (les options payantes se gèrent dans Tarifs & options)', 'default' => 'Options & services'],
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
    'avis' => [
        'title' => 'Section « Avis clients »',
        'fields' => [
            'reviews_title'      => ['label' => 'Titre', 'default' => 'Ils ont fêté chez nous'],
            'reviews_subtitle'   => ['label' => 'Sous-titre', 'default' => 'Les avis de nos clients sur Google et 1001Salles.'],
            'google_rating'      => ['label' => 'Note Google (ex. 4,9)', 'default' => '', 'help' => 'Laisser vide pour masquer le badge Google.'],
            'google_count'       => ['label' => 'Nombre d’avis Google', 'default' => '', 'type' => 'number'],
            'google_reviews_url' => ['label' => 'Lien vers vos avis Google', 'default' => '', 'type' => 'url', 'help' => 'Sur Google Maps : votre fiche › Avis › Partager › Copier le lien.'],
            'salles1001_rating'  => ['label' => 'Note 1001Salles (ex. 5)', 'default' => '', 'help' => 'Laisser vide pour masquer le badge 1001Salles. Le lien utilisé est celui des Informations générales.'],
            'salles1001_count'   => ['label' => 'Nombre d’avis 1001Salles', 'default' => '', 'type' => 'number'],
            'reviews_visible'    => ['label' => 'Nombre d’avis affichés avant « Voir plus »', 'default' => '6', 'type' => 'number'],
        ],
    ],
    'carte' => [
        'title' => 'Carte Google Maps',
        'fields' => [
            'map_query'    => ['label' => 'Lieu affiché sur la carte', 'default' => 'La Salle RN6, 71 C route d’Auxerre, 89380 Appoigny', 'help' => 'Nom de la fiche Google ou adresse.'],
        ],
    ],
    'legal' => [
        'title' => 'Pages légales',
        'fields' => [
            'legal_director' => ['label' => 'Directeur de la publication (nom et prénom du gérant)', 'default' => ''],
            'legal_host'     => ['label' => 'Hébergeur', 'default' => '', 'help' => 'Vide = OVH SAS, 2 rue Kellermann, 59100 Roubaix.'],
            'legal_mentions' => ['label' => 'Mentions légales', 'type' => 'textarea', 'default' => '', 'fallback' => 'LEGAL_MENTIONS_DEFAULT',
                'help' => 'Lignes commençant par « # » = titres, **gras**. Variables : {societe} {forme} {adresse_siege} {siren} {rcs} {tva} {email} {directeur} {hebergeur} {adresse_site} {conditions} {confidentialite}. Société, SIREN et TVA viennent de Réservations › Réglages › Société.'],
            'legal_privacy'  => ['label' => 'Politique de confidentialité', 'type' => 'textarea', 'default' => '', 'fallback' => 'LEGAL_PRIVACY_DEFAULT',
                'help' => 'Texte proposé à faire relire. Mêmes variables que ci-dessus.'],
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
    'accueil2' => [
        'title' => 'Nouvelle page d’accueil',
        'fields' => [
            'home_design'     => ['label' => 'Version de la page d’accueil', 'type' => 'select', 'default' => 'nouveau', 'options' => [
                'classique' => 'Version actuelle',
                'nouveau'   => 'Nouvelle version (bandeaux photo, événements, FAQ…) — aperçu : adresse du site suivie de /?apercu=1',
            ]],
            'band1_photo'     => ['label' => 'Bandeau photo 1 — n° de la photo (#, page Photos)', 'default' => '4', 'type' => 'number'],
            'band1_text'      => ['label' => 'Bandeau photo 1 — phrase', 'default' => 'Un grand espace lumineux, prêt à accueillir jusqu’à 120 invités.'],
            'band2_photo'     => ['label' => 'Bandeau photo 2 — n° de la photo', 'default' => '7', 'type' => 'number'],
            'band2_text'      => ['label' => 'Bandeau photo 2 — phrase', 'default' => 'Un bar dédié pour vos vins d’honneur, cocktails et pauses café.'],
            'events_title'    => ['label' => 'Événements — titre', 'default' => 'Pour quel événement ?'],
            'events_subtitle' => ['label' => 'Événements — sous-titre', 'type' => 'textarea', 'default' => 'Une salle modulable qui s’adapte à vos invités, que vous receviez 20 ou 120 personnes.'],
            'equip2_photo'    => ['label' => 'Équipements — n° de la photo affichée à côté', 'default' => '9', 'type' => 'number'],
            'dates_title'     => ['label' => 'Tarifs — titre des prochaines dates libres', 'default' => 'Prochains samedis libres'],
            'dates_weekdays'  => ['label' => 'Prochaines dates libres — jours affichés', 'type' => 'select', 'default' => '6', 'options' => [
                '6'     => 'Samedis',
                '5,6'   => 'Vendredis et samedis',
                '5,6,7' => 'Vendredis, samedis et dimanches',
                '1,2,3,4,5,6,7' => 'Tous les jours',
            ]],
            'faq_title'       => ['label' => 'FAQ — titre', 'default' => 'Questions fréquentes'],
            'faq_subtitle'    => ['label' => 'FAQ — sous-titre', 'default' => 'Tout ce qu’on nous demande avant de réserver.'],
            'contact_phone'   => ['label' => 'Téléphone affiché (laisser vide pour ne pas l’afficher)', 'default' => ''],
            'contact_whatsapp' => ['label' => 'Bouton WhatsApp', 'type' => 'select', 'default' => '0', 'options' => [
                '0' => 'Non',
                '1' => 'Oui, sur le numéro ci-dessus (portable)',
            ]],
            'contact_form_intro' => ['label' => 'Formulaire de contact — phrase d’introduction', 'default' => 'Une question, une visite à organiser ? Écrivez-nous, réponse rapide.'],
        ],
    ],
    'resa' => [
        'title' => 'Page « Réserver en ligne » / maintenance',
        'fields' => [
            'resa_maintenance'       => ['perm' => 'reglages', 'label' => 'État de la réservation en ligne', 'type' => 'select', 'default' => '0', 'options' => [
                '0' => '✅ Ouverte : l’agenda SuperSaaS est affiché',
                '1' => '🛠 En maintenance : l’agenda est remplacé par le message ci-dessous',
            ]],
            'resa_maint_title'       => ['label' => 'Maintenance — titre', 'default' => 'Réservation en ligne momentanément indisponible'],
            'resa_maint_message'     => ['label' => 'Maintenance — message', 'type' => 'textarea', 'default' => "Notre agenda en ligne est en cours de mise à jour.\nPour réserver ou connaître nos disponibilités, contactez-nous directement par e-mail : nous vous répondons rapidement."],
            'resa_maint_contact'     => ['label' => 'Maintenance — boutons de contact', 'type' => 'select', 'default' => '1', 'options' => [
                '1' => 'Afficher les boutons « Nous contacter » et « Retour au site »',
                '0' => 'Ne rien afficher sous le message',
            ]],
            'resa_meta_title'     => ['label' => 'Titre Google', 'default' => 'Réserver en ligne | La Salle RN6'],
            'resa_title'          => ['label' => 'Titre', 'default' => 'Réservez La Salle RN6'],
            'resa_lead'           => ['label' => 'Introduction', 'type' => 'textarea', 'default' => 'Consultez les disponibilités, choisissez votre créneau et vos options, et réglez en ligne en toute sécurité.'],
            'resa_notice_title'   => ['label' => 'Encadré — titre', 'default' => 'Réservation de la salle'],
            'resa_notice'         => ['label' => 'Encadré — texte', 'type' => 'textarea', 'default' => 'Choisissez votre formule et une date disponible, ajoutez vos options puis réglez en ligne : la salle vous est réservée immédiatement.'],
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
    'tarif_infos'   => ['label' => 'Infos tarifaires (caution…)', 'menu' => 'tarifs', 'fields' => ['title' => 'Titre', 'text' => 'Texte']],
    'tarif_options' => ['label' => 'Lignes ajoutées aux options (remise, parking…)', 'menu' => 'tarifs', 'fields' => ['text' => 'Libellé', 'value' => 'Valeur']],
    'chiffres'      => ['label' => 'Nouvelle page — chiffres clés (bande bleue)', 'menu' => 'contenus', 'fields' => ['title' => 'Chiffre', 'text' => 'Libellé']],
    'evenements_cartes' => ['label' => 'Nouvelle page — cartes « Pour quel événement ? »', 'menu' => 'contenus', 'fields' => ['icon' => 'Icône', 'title' => 'Titre', 'text' => 'Texte', 'value' => 'N° de photo (#, page Photos)']],
    'faq'           => ['label' => 'Nouvelle page — questions fréquentes', 'menu' => 'contenus', 'fields' => ['title' => 'Question', 'text' => 'Réponse']],
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
        ['title' => '98 pouces', 'text' => 'Écran grand format pour présentations, séminaires et vidéos, **disponible en option**.'],
        ['title' => 'Audio pro', 'text' => 'Sonorisation intégrée avec micros sans fil, **disponible en option**.'],
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
    'tarif_infos' => [
        ['title' => 'Caution pour la salle :', 'text' => '2 × le montant du loyer HT.'],
        ['title' => 'Eau & électricité :', 'text' => 'comprises dans le prix de location.'],
    ],
    'tarif_options' => [
        ['text' => 'Remise association à but non lucratif', 'value' => '− 15 %'],
        ['text' => 'Parking', 'value' => 'Gratuit + accès PMR'],
    ],
    'chiffres' => [
        ['title' => '125 m²', 'text' => 'de grande salle'],
        ['title' => '120', 'text' => 'invités assis'],
        ['title' => '144', 'text' => 'chaises incluses'],
        ['title' => '5 min', 'text' => 'de l’A6, sortie n°19'],
    ],
    'evenements_cartes' => [
        ['icon' => '💍', 'title' => 'Mariages', 'text' => 'Repas, vin d’honneur et soirée dansante, jusqu’à 120 invités assis.', 'value' => '2'],
        ['icon' => '🎂', 'title' => 'Anniversaires & fêtes', 'text' => 'Tables rondes ou rectangulaires, bar, et la sono en option : il ne manque que vos invités.', 'value' => '10'],
        ['icon' => '🥂', 'title' => 'Vins d’honneur & cocktails', 'text' => 'Un bar dédié et des mange-debout pour recevoir debout.', 'value' => '6'],
        ['icon' => '💼', 'title' => 'Séminaires & réunions', 'text' => 'Wi-Fi haut débit, et en option un écran 98 pouces avec sono et micros sans fil.', 'value' => '9'],
    ],
    'faq' => [
        ['title' => 'Peut-on faire venir son traiteur ?', 'text' => 'Oui, le traiteur de votre choix. La cuisine sert d’office : maintien au frais et réchauffage des plats (il n’est pas possible de cuisiner sur place).'],
        ['title' => 'Jusqu’à quelle heure peut-on faire la fête ?', 'text' => 'La musique et les animations s’arrêtent à **2 h**, par respect du voisinage. La location à la journée court de 10 h 30 au lendemain 10 h.'],
        ['title' => 'Que comprend la location ?', 'text' => 'La salle, le bar, la cuisine, le mobilier (**144 chaises**, 20 tables de 180 cm, tables rondes, mange-debout), l’eau et l’électricité. D’autres services sont proposés en option lors de la réservation.'],
        ['title' => 'Peut-on décorer la salle ?', 'text' => 'Oui, librement, à condition de ne rien fixer sur les murs et de ne pas utiliser de confettis ni de flammes (bougies, feux de Bengale…).'],
        ['title' => 'Comment fonctionne la caution ?', 'text' => 'Une caution de **2 × le loyer HT** est demandée, au choix : par empreinte bancaire en ligne (rien n’est débité) ou par chèque remis à l’état des lieux. Elle est rendue après l’état des lieux de sortie.'],
        ['title' => 'Et si je dois annuler ?', 'text' => 'Vous pouvez annuler depuis votre espace client. Le montant remboursé dépend du délai avant l’événement, comme indiqué dans les conditions de location.'],
        ['title' => 'Peut-on visiter la salle avant de réserver ?', 'text' => 'Bien sûr, sur rendez-vous : écrivez-nous avec vos disponibilités.'],
        ['title' => 'Y a-t-il un parking ?', 'text' => 'Oui, un grand parking public gratuit juste à côté, et un accès adapté aux personnes à mobilité réduite.'],
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
    'carte'    => 'Affichage de la carte',
    'avis'     => 'Clic vers les avis Google / 1001Salles',
    'tel'      => 'Clic téléphone',
    'whatsapp' => 'Clic WhatsApp',
    'formulaire' => 'Message envoyé par le formulaire de contact',
    'date'     => 'Clic sur une prochaine date libre',
];

const REVIEW_SOURCES = [
    'google' => 'Google',
    '1001'   => '1001Salles',
    'autre'  => 'Autre (Facebook, e-mail…)',
];

/** Version du schéma : à incrémenter pour ajouter les nouveaux réglages aux sites déjà installés. */
const SCHEMA_VERSION = 19;

/** Documents remis aux clients à l'installation : [titre, description, fichier dans /documents]. */
const DEFAULT_DOCUMENTS = [
    ['Règlement intérieur', 'Les règles d’utilisation de la salle, à respecter et à faire respecter par vos invités et prestataires.', 'reglement-interieur.pdf'],
    ['Contrat de location (modèle)', 'Le contrat signé lors de la remise des clés : vous pouvez en prendre connaissance à l’avance.', 'contrat-de-location.pdf'],
    ['État des lieux et inventaire', 'Le document rempli ensemble à l’arrivée et au départ (salle, mobilier, équipements, caution).', 'etat-des-lieux.pdf'],
];

/**
 * Sections de la page d'accueil dont l'ordre et l'affichage se règlent dans
 * l'administration (le bandeau d'accueil et ses 4 points forts restent en haut).
 * nav = libellé dans le menu (null = pas de lien), href = cible du lien.
 */
const HOME_SECTIONS = [
    'salle'       => ['label' => 'La salle (cartes)', 'nav' => 'La salle', 'mobile' => 'La salle', 'href' => '#salle'],
    'equipements' => ['label' => 'Équipements sur place', 'nav' => 'Équipements', 'mobile' => 'Équipements', 'href' => '#equipements'],
    'mobilier'    => ['label' => 'Équipements & mobilier inclus', 'nav' => 'Mobilier', 'mobile' => 'Mobilier', 'href' => '#mobilier'],
    'plan'        => ['label' => 'Encart « Plan de la salle »', 'nav' => 'Plan', 'mobile' => 'Plan de la salle', 'href' => 'plan.php'],
    'tarifs'      => ['label' => 'Tarifs de location', 'nav' => 'Tarifs', 'mobile' => 'Tarifs', 'href' => '#tarifs'],
    'acces'       => ['label' => 'Accès & carte', 'nav' => 'Accès', 'mobile' => 'Accès', 'href' => '#acces'],
    'photos'      => ['label' => 'Galerie photos', 'nav' => 'Photos', 'mobile' => 'Photos', 'href' => '#photos'],
    'avis'        => ['label' => 'Avis clients', 'nav' => 'Avis', 'mobile' => 'Avis clients', 'href' => '#avis'],
    'contact'     => ['label' => 'Contact (encadré bleu)', 'nav' => null, 'mobile' => 'Contact', 'href' => '#contact'],
    // Sections de la nouvelle page d'accueil uniquement
    'bandeau1'    => ['label' => 'Bandeau photo 1', 'nav' => null, 'mobile' => null, 'href' => '', 'v2' => true],
    'chiffres'    => ['label' => 'Chiffres clés (bande bleue)', 'nav' => null, 'mobile' => null, 'href' => '', 'v2' => true],
    'evenements'  => ['label' => 'Pour quel événement ? (cartes photo)', 'nav' => null, 'mobile' => 'Événements', 'href' => '#evenements', 'v2' => true],
    'bandeau2'    => ['label' => 'Bandeau photo 2', 'nav' => null, 'mobile' => null, 'href' => '', 'v2' => true],
    'faq'         => ['label' => 'Questions fréquentes', 'nav' => 'FAQ', 'mobile' => 'Questions fréquentes', 'href' => '#faq', 'v2' => true],
];

/** Ordre d'origine de la nouvelle page d'accueil (le mobilier y est regroupé avec les équipements). */
const HOME_V2_ORDER = ['avis', 'salle', 'bandeau1', 'evenements', 'chiffres', 'equipements', 'plan', 'bandeau2', 'tarifs', 'photos', 'faq', 'acces', 'contact'];

const PHOTO_CATEGORIES = ['salle' => 'Salle', 'bar' => 'Bar', 'cuisine' => 'Cuisine', 'sanitaires' => 'Sanitaires', 'exterieur' => 'Extérieur'];
