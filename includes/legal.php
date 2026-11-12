<?php
/**
 * Mentions légales et politique de confidentialité : textes par défaut (à faire relire),
 * modifiables dans Admin › Contenu du site › Textes › Pages légales.
 */

const LEGAL_MENTIONS_DEFAULT = <<<'TXT'
# Éditeur du site
Le site {adresse_site} est édité par **{societe}**{forme}, dont le siège est situé {adresse_siege}.
SIREN : {siren}{rcs} — TVA intracommunautaire : {tva}
Contact : {email}

# Directeur de la publication
{directeur}

# Hébergement
{hebergeur}

# Propriété intellectuelle
Les textes, photographies, logo et éléments graphiques de ce site sont la propriété de {societe} ou utilisés avec autorisation. Toute reproduction sans autorisation est interdite.

# Réservation et paiement
Les réservations en ligne sont régies par les conditions générales de location : {conditions}
Les paiements sont traités par Stripe et PayPal ; aucune donnée de carte bancaire n’est stockée par le site.

# Données personnelles
Voir la politique de confidentialité : {confidentialite}
TXT;

const LEGAL_PRIVACY_DEFAULT = <<<'TXT'
# Responsable du traitement
**{societe}**, {adresse_siege} — contact : {email}

# Données collectées et pourquoi
**Réservation** : nom, prénom, e-mail, téléphone, adresse de facturation (facultative), société ou association, type d’événement, nombre de personnes, message. Elles servent à gérer la location, à vous contacter et à établir la facture (exécution du contrat et obligations comptables).
**Paiement** : le paiement est réalisé directement chez Stripe ou PayPal. Le site ne reçoit ni ne conserve vos données bancaires, seulement la référence du paiement.
**E-mails** : confirmation, rappel avant l’événement, demande d’avis après l’événement (intérêt légitime, lié à votre location). Les envois d’information aux anciens clients comportent toujours un lien de désinscription.
**Statistiques de visite** : mesure anonyme réalisée par le site lui-même, sans cookie et sans conserver votre adresse IP.

# Destinataires
Les données sont réservées à {societe}. Elles sont transmises uniquement aux prestataires nécessaires : hébergement (OVH, en France), paiement (Stripe, PayPal), envoi d’e-mails et agenda (Google). Certains de ces prestataires peuvent traiter des données hors de l’Union européenne, dans le cadre des garanties prévues par le RGPD (clauses contractuelles types).

# Durées de conservation
Réservations et factures : 10 ans (obligation comptable).
Réservations non payées : supprimées au bout de 6 mois.
Envois d’information : jusqu’à votre désinscription, au plus 3 ans après votre dernière réservation.
Statistiques de visite : 25 mois.

# Cookies
Le site ne dépose aucun cookie de mesure d’audience ni de publicité. La carte Google Maps intégrée peut déposer des cookies de Google. Un cookie technique est utilisé uniquement pour l’espace d’administration.

# Vos droits
Vous pouvez demander l’accès, la rectification, l’effacement ou la portabilité de vos données, ou vous opposer à leur utilisation, en écrivant à {email}. Vous pouvez aussi adresser une réclamation à la CNIL (www.cnil.fr).
TXT;

const LEGAL_HOST_DEFAULT = "OVH SAS — 2 rue Kellermann, 59100 Roubaix, France — www.ovhcloud.com";

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
        '{directeur}' => s('legal_director') !== '' ? s('legal_director') : 'à compléter (nom du gérant)',
        '{hebergeur}' => s('legal_host') !== '' ? s('legal_host') : LEGAL_HOST_DEFAULT,
        '{site}' => s('site_name'),
        '{adresse_site}' => rtrim(s('site_url'), '/'),
        '{conditions}' => bk_url('conditions.php'),
        '{confidentialite}' => bk_url('confidentialite.php'),
    ];
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
