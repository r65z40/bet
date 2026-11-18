<?php
/**
 * Mentions légales et politique de confidentialité : textes par défaut (à faire relire),
 * modifiables dans Admin › Contenu du site › Textes › Pages légales.
 */

const LEGAL_MENTIONS_DEFAULT = <<<'TXT'
# Éditeur du site
Le site {adresse_site} est édité par **{societe}**{forme}, dont le siège est situé {adresse_siege}.
SIREN : {siren}{rcs} — TVA intracommunautaire : {tva}
Contact : {email} — téléphone : {tel}

# Directeur de la publication
{directeur}

# Hébergement
{hebergeur}

# Propriété intellectuelle
Les textes, photographies, logo et éléments graphiques de ce site sont la propriété de {societe} ou utilisés avec autorisation. Toute reproduction sans autorisation est interdite.

# Réservation et paiement
Les réservations en ligne sont régies par les conditions générales de location : {conditions}
Les paiements sont traités par Stripe et PayPal ; aucune donnée de carte bancaire n’est stockée par le site.

# Médiation de la consommation
En cas de litige non résolu à l’amiable, le client consommateur peut recourir gratuitement au médiateur de la consommation : {mediateur}.

# Données personnelles
Voir la politique de confidentialité : {confidentialite}
TXT;

const LEGAL_PRIVACY_DEFAULT = <<<'TXT'
# Responsable du traitement
**{societe}**, {adresse_siege} — contact : {email}

# Données collectées et pourquoi
**Réservation** : nom, prénom, e-mail, téléphone, adresse de facturation (facultative), société ou association, type d’événement, nombre de personnes, message. Elles servent à gérer la location, à vous contacter et à établir la facture (exécution du contrat et obligations comptables).
**Paiement** : le paiement est réalisé directement chez Stripe ou PayPal. Le site ne reçoit ni ne conserve vos données bancaires, seulement la référence du paiement.
**E-mails liés à votre demande** : confirmation, rappel avant l’événement, demande et rappels du solde, demande d’avis et remerciement après l’événement, rappel d’un devis non réservé ou d’une réservation non finalisée (au plus deux rappels, intérêt légitime lié à votre demande). Une copie des e-mails envoyés est conservée pour pouvoir répondre à vos questions et prouver leur envoi.
**Caution** : si vous choisissez l’empreinte bancaire, elle est réalisée par Stripe ; le site ne connaît que sa référence et son montant.
**Actualités et offres** : uniquement si vous avez coché la case prévue (consentement, dont nous conservons la date et le formulaire), ou si vous êtes déjà client pour des offres sur la location de la salle. Chaque e-mail comporte un lien de désinscription en un clic.
**Parrainage** : lorsqu’un client partage son code de parrainage, nous conservons le lien entre le code et son adresse e-mail pour lui offrir son avoir.
**Statistiques de visite** : mesure anonyme réalisée par le site lui-même, sans cookie et sans conserver votre adresse IP.
**Sécurité** : les données sont sauvegardées chaque jour, avec une copie transférée de façon sécurisée vers un espace Google Drive réservé à {societe}.

# Destinataires
Les données sont réservées à {societe}. Elles sont transmises uniquement aux prestataires nécessaires : hébergement (OVH, en France), paiement et caution (Stripe, PayPal), envoi d’e-mails, agenda et sauvegardes (Google). Certains de ces prestataires peuvent traiter des données hors de l’Union européenne, dans le cadre des garanties prévues par le RGPD (clauses contractuelles types).

# Durées de conservation
Réservations et factures : 10 ans (obligation comptable).
Réservations non payées : supprimées au bout de 6 mois.
Envois d’information : jusqu’à votre désinscription, au plus 3 ans après votre dernier contact ou votre dernière réservation.
Demandes de devis et contacts sans réservation : effacés automatiquement 3 ans après le dernier contact.
Copie des e-mails envoyés : 2 ans.
Statistiques de visite : 25 mois.
Sauvegardes : effacées automatiquement au bout de quelques semaines.

# Cookies
Le site ne dépose aucun cookie de mesure d’audience ni de publicité. La carte Google Maps ne s’affiche que si vous cliquez sur « Afficher la carte » : Google peut alors déposer ses propres cookies. Un cookie technique est utilisé uniquement pour l’espace d’administration. Le navigateur garde aussi en mémoire, sur votre appareil, quelques préférences d’affichage (par exemple pour ne pas vous reproposer le récapitulatif par e-mail).

# Vos droits
Vous pouvez demander l’accès, la rectification, l’effacement, la limitation ou la portabilité de vos données, vous opposer à leur utilisation (notamment à tout moment pour les e-mails d’offres), et définir des directives sur leur sort après votre décès, en écrivant à {email}. Réponse sous un mois. Les factures restent conservées pendant la durée légale. Vous pouvez aussi adresser une réclamation à la CNIL (www.cnil.fr).
TXT;

const LEGAL_HOST_DEFAULT = "OVH SAS — 2 rue Kellermann, 59100 Roubaix, France — tél. 1007 — www.ovhcloud.com";

/** Valeurs remplacées dans les textes légaux. */
function legal_vars(): array
{
    $siren = trim(s('bk_company_siren'));
    return [
        '{societe}' => s('bk_company_name') ?: s('site_name'),
        '{forme}' => s('bk_company_form') !== '' ? ', ' . s('bk_company_form') : '',
        '{adresse_siege}' => str_replace("\n", ', ', trim(s('bk_company_address'))),
        '{siren}' => $siren !== '' ? $siren : 'à compléter',
        '{rcs}' => s('bk_company_rcs') !== '' ? ' — RCS ' . s('bk_company_rcs') : '',
        '{tva}' => s('bk_company_vat') ?: 'non applicable',
        '{email}' => s('contact_email'),
        '{telephone}' => s('contact_phone') !== '' ? ' — tél. ' . s('contact_phone') : '',
        '{tel}' => s('contact_phone') !== '' ? s('contact_phone') : 'à compléter',
        '{mediateur}' => s('legal_mediator') !== '' ? s('legal_mediator') : 'à compléter (médiateur de la consommation)',
        '{directeur}' => s('legal_director') !== '' ? s('legal_director') : 'à compléter (nom du gérant)',
        '{hebergeur}' => s('legal_host') !== '' ? s('legal_host') : LEGAL_HOST_DEFAULT,
        '{site}' => s('site_name'),
        '{adresse_site}' => rtrim(s('site_url'), '/'),
        '{conditions}' => bk_url('conditions.php'),
        '{confidentialite}' => bk_url('confidentialite.php'),
    ];
}

/** Conditions de location mises en forme (page publique et e-mail de confirmation). */
function cgv_html(): string
{
    return render_doc(str_replace('{paiement}', acompte_terms(), s('bk_cgv')), legal_vars());
}

/** Texte effectif d'une page légale (valeur modifiée ou texte par défaut). */
function legal_text(string $key): string
{
    $fallback = ['legal_mentions' => LEGAL_MENTIONS_DEFAULT, 'legal_privacy' => LEGAL_PRIVACY_DEFAULT];
    return s($key) !== '' ? s($key) : ($fallback[$key] ?? '');
}

/**
 * Document mis en forme : lignes commençant par « # » = titres, **gras**, liens cliquables,
 * variables {…} remplacées. Tout le texte est échappé.
 */
function render_doc(string $text, array $vars = []): string
{
    $html = '';
    foreach (preg_split('/\R/', trim($text)) as $line) {
        $line = strtr($line, $vars);
        if (trim($line) === '') {
            continue;
        }
        if (str_starts_with($line, '#')) {
            $html .= '<h2>' . e(ltrim($line, '# ')) . '</h2>';
            continue;
        }
        $l = rich($line);
        $html .= '<p>' . preg_replace_callback('#https?://[^\s<]+#', fn($m) => '<a href="' . $m[0] . '">' . $m[0] . '</a>', $l) . '</p>';
    }
    return $html;
}
