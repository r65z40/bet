# La Salle RN6 — site + administration

Site vitrine de location de salle (Appoigny) avec une administration simple.

**Stack : PHP 8 + SQLite.** Pas de framework, pas de Composer, pas de npm, pas de serveur MySQL.
Fonctionne sur n'importe quel hébergement mutualisé PHP (OVH, o2switch, Hostinger, IONOS…).

## Installation

1. Envoyer **tout le dossier** sur l'hébergement (FTP), à la racine du site.
2. Vérifier que les dossiers `data/` et `photos/` sont **accessibles en écriture** par PHP (droits 755 ou 775).
3. Ouvrir `https://votre-site.fr/admin/` → la page d'installation demande un **code** : l'ouvrir par FTP dans `data/setup-code.php` (Bloc-notes), le recopier et créer le compte administrateur. Le fichier est supprimé automatiquement ensuite.

C'est tout : la base `data/site.sqlite` est créée automatiquement et pré-remplie avec le contenu actuel du site.

> Prérequis : PHP ≥ 8.1 avec les extensions `pdo_sqlite` et `fileinfo` (activées par défaut presque partout).

## Ce que l'on peut modifier dans l'administration (`/admin/`)

| Menu | Contenu |
|---|---|
| **Sections** | Ordre des blocs de la page d'accueil (la salle, tarifs, photos, avis, accès, contact…) et masquage ; le menu du site suit le même ordre |
| **Textes** | Tous les titres et paragraphes, e-mail, adresse, liens Facebook / Maps / 1001Salles, référencement Google, pages Plan et Réservation (identifiants SuperSaaS) |
| **Tarifs** | Prix par jour, infos (caution…), options & services : modifier, ajouter, supprimer, réordonner, masquer |
| **Listes & cartes** | Points forts, cartes « La salle », équipements, mobilier, vaisselle, accès, événements, surfaces du plan |
| **Photos** | Envoi multiple, légendes, ordre, masquer, supprimer, choix de l'image du bandeau d'accueil, remplacement du plan. Chaque photo est automatiquement réduite et déclinée en WebP (vignette 800 px pour la galerie, 1920 px pour l'agrandissement) |
| **Avis** | Avis Google / 1001Salles recopiés (nom, note, date, événement, texte), ordre, masquage ; notes globales et liens dans Textes › Avis clients |
| **Statistiques** | Visiteurs, pages vues, provenance (Google, Facebook…), appareils, jours/heures, clics sur « Réserver », e-mail, Maps, photos… et taux de conversion, avec comparaison à la période précédente |
| **Mon compte** | Identifiant, e-mail et mot de passe |
| **Équipe** (👥) | Comptes administrateurs : invitation par e-mail, rôles (Administrateur, Gérant, Accueil, Rédacteur) ou droits au choix (réservations, remboursements, tarifs, e-mails, contenu, statistiques, réglages, utilisateurs), désactivation, journal d'activité |
| **E-mails clients** | Écrire à un client ou à un groupe (réservations à venir, passées, tous les clients) avec variables ({prenom}, {reservation}…), aperçu, test, envoi par lots avec suivi, désinscription automatique (RGPD) |
| **Modèles d'e-mails** | Objet et texte de chaque e-mail automatique (confirmation, annulation, alerte, invitation, mot de passe oublié) et apparence commune (couleurs, logo, pied de page), avec aperçu |
| **Tableau de bord** | Bouton pour passer la réservation en ligne **en maintenance** (message personnalisé à la place de l'agenda SuperSaaS, modifiable dans Textes › Réserver en ligne) |

### Côté visiteurs
- Menu « hamburger » sur mobile, animations d'apparition et chiffres clés animés (désactivés si le visiteur a demandé « réduire les animations »).
- Carte Google Maps interactive affichée directement dans la section Accès (chargée par le navigateur à l'approche de la section, pour ne pas ralentir le haut de page). Le lieu se règle dans Textes › Carte.
- Galerie en WebP avec vignettes légères ; la photo en grand n'est chargée qu'à l'ouverture (balayage gauche/droite sur mobile).

Dans les textes, `**mot**` affiche le mot en **gras**.

## Réservation en ligne (remplace SuperSaaS)

Page `reservation.php` : formule **à la journée** (24 h, 48 h ou 72 h, de 10 h à 10 h, chaque journée au tarif de son jour, remise multi-jours possible) ou **à l'heure** (8 h–21 h, 2 h minimum), calendrier des disponibilités, type d'événement, nombre de personnes, options payantes, remise association, coordonnées, acceptation des conditions, puis **paiement intégral** par **Stripe** (carte) ou **PayPal**.

- **Prix** : journées saisies HT dans *Admin › Réservations › Tarifs & options*, affichées et encaissées TTC. Location à l'heure saisie TTC : 150 € les 2 h, puis 65 € la 3e heure et −5 % pour chaque heure suivante (arrondi à l'euro), plafonnée au prix de la journée ; tableau de simulation dans l'admin.
- **Disponibilités** : réservations payées, paiements en cours (créneau bloqué 35 min), blocages manuels et **événements de l'agenda Google** ; **temps mort de 2 h** autour de chacun. Délai minimum 48 h, maximum 18 mois.
- **Après paiement** : confirmation immédiate, facture numérotée (F2026-0001…), e-mail au client (récapitulatif, facture, « Ajouter à mon agenda ») et alerte au propriétaire.
- **Admin › Réservations** : liste, planning mensuel, fiche (caution, notes, renvoi d'e-mail), **annulation avec remboursement** automatique Stripe/PayPal (montant proposé selon les conditions d'annulation) et avoir (A2026-0001…), blocage de créneaux.
- **Agenda unique (Google)** : le site **écrit** chaque réservation payée et chaque blocage de l'admin dans l'agenda Google de la salle (compte de service, guide pas à pas dans *Réglages › Agenda Google*) et **lit** ce même agenda (adresse secrète iCal, toutes les 10 min et juste avant chaque paiement) : ce que vous y ajoutez depuis l'iPhone bloque les réservations. Les événements écrits par le site sont reconnus et ne sont pas comptés deux fois ; une annulation les retire. Les locations de plus d'un jour sont découpées par jour (10 h → 23 h 59, puis minuit → 10 h) pour remplir la colonne de chaque jour. Un lien d'abonnement iCal privé reste disponible en lecture seule.

### Mise en service (une fois)
1. *Admin › Réservations › Réglages › Société* : compléter forme juridique, RCS, vérifier SIREN et TVA.
2. *E-mails* : choisir le fournisseur (Gmail, OVH ou autre serveur SMTP), saisir l'identifiant et le mot de passe (Gmail : **mot de passe d'application** — Compte Google › Sécurité › Validation en deux étapes › Mots de passe des applications ; OVH : mot de passe de la boîte), cliquer « Envoyer un e-mail de test ».
3. *Paiement* : clé secrète Stripe (`sk_test_…` pour tester), webhook Stripe vers `https://lasallern6.fr/stripe-webhook.php` (événement `checkout.session.completed`), Client ID + Secret PayPal (mode sandbox pour tester). Bouton « Tester la connexion ».
4. *Agenda Google* : suivre le guide de la page (compte de service Google, partage de l'agenda), coller l'ID de l'agenda, la clé JSON et l'adresse secrète iCal, puis « Écrire toutes les réservations à venir dans l'agenda ». Sur l'iPhone, afficher cet agenda Google (Réglages › Calendrier › Comptes › Google).
5. *Annulation & conditions* : faire relire les conditions de location.
6. Tester une réservation en mode test, puis passer les clés en production.

## Tarifs spéciaux, codes promo, bons cadeaux
*Admin › Réservations › Tarifs & options* :
- **Dates spéciales** : majoration (ou remise) en % ou prix de journée fixe sur une date ou une période (réveillon, fêtes, haute saison). La location à l'heure suit la même proportion ; le calendrier affiche le prix réel (★).
- **Codes promo** : remise en % ou en €, dates de validité, nombre d'utilisations, minimum de réservation.
- **Bons cadeaux** : montant prépayé avec solde, code long généré automatiquement, imprimable ou envoyé par e-mail. C'est un moyen de paiement : la facture garde le prix complet et indique la part réglée par le bon ; s'il couvre tout, la réservation est confirmée sans paiement en ligne ; en cas d'annulation, le bon peut être recrédité.

## E-mails automatiques et tâche planifiée
- **Rappel J-7** (infos pratiques modifiables) et **demande d'avis Google J+1** (lien « Laisser un avis » à renseigner), modèles modifiables dans *E-mails clients › Modèles*.
- Tâche planifiée OVH : *Hébergement › Tâches planifiées - Cron* › commande `www/cron.php`, toutes les heures. Sans elle, les tâches tournent au plus une fois par heure après l'affichage d'une page. Elle s'occupe aussi des paiements abandonnés, des envois groupés en attente et de la suppression des réservations jamais payées après 6 mois.

## Application admin et notifications
L'administration s'installe sur l'écran d'accueil (iPhone : Safari › Partager › Sur l'écran d'accueil ; Android : Installer l'application) et envoie une **notification à chaque réservation payée** (iOS 16.4+), à activer dans *Mon compte › Application et notifications* sur chaque appareil. Notifications Web Push chiffrées (VAPID), sans service tiers.

## Pages légales
`mentions-legales.php` et `confidentialite.php`, textes préremplis (à faire relire) et modifiables dans *Contenu du site › Textes › Pages légales* ; liens en pied de page de toutes les pages et dans le tunnel de réservation.

## Statistiques et RGPD

Mesure maison, sans Google Analytics ni cookie : l'adresse IP n'est jamais enregistrée (seulement un hash anonyme qui change chaque jour). Les robots et l'administrateur connecté ne sont pas comptés. Les données de plus de 25 mois sont effacées automatiquement. Aucun bandeau cookies n'est donc nécessaire pour ces statistiques.

## Structure

```
index.php, plan.php                    pages publiques (même design que l'ancien site HTML)
reservation.php, api.php               tunnel de réservation et son API (disponibilités, prix, création)
paiement.php, retour-paiement.php      lancement / retour du paiement Stripe ou PayPal
stripe-webhook.php                     confirmation par Stripe (même si le client ferme la page)
ma-reservation.php, facture.php        espace client (lien secret) et facture / avoir imprimables
calendrier.php                         flux iCal privé des réservations (abonnement iPhone)
conditions.php                         conditions générales de location
track.php                              réception des clics pour les stats
admin/                                 administration
includes/                              code commun (base, fonctions, contenus par défaut) — non accessible du web
data/                                  base SQLite — non accessible du web
photos/                                photos du site (les scripts y sont bloqués)
```

Les vignettes WebP sont rangées dans `photos/cache/` ; si elles manquent (photos ajoutées par FTP), elles sont créées automatiquement à l'ouverture de l'administration.

Les mises à jour du site s'installent en renvoyant les fichiers par FTP : la base existante est complétée automatiquement (nouveaux réglages), sans perte de contenu.

Les anciennes adresses `plan.html` et `reservation.html` sont redirigées (301) vers les pages PHP via `.htaccess`.

## Sauvegarde

Sauvegarder régulièrement **tout le dossier `data/`** (la base `site-xxxx.sqlite` et `config.php` qui contient son nom) et le dossier `photos/`.

## Sécurité

- Mots de passe hachés (bcrypt), 10 caractères minimum ; 5 essais ratés par IP = blocage 15 min ; déconnexion après 2 h d'inactivité.
- Plusieurs comptes avec droits vérifiés sur chaque page et chaque action ; un compte désactivé, supprimé ou dont le mot de passe change est déconnecté partout immédiatement ; il reste toujours au moins un compte « Utilisateurs ».
- Actions sensibles (réglages de paiement, d'envoi d'e-mails, d'agenda, de société ; gestion des comptes) : mot de passe redemandé (valable 15 min). L'adresse du site et la maintenance ne sont modifiables qu'avec le droit « Réglages ». Limite de 10 essais ratés par compte en plus de la limite par IP ; session de 12 h maximum ; e-mail d'alerte au titulaire quand son mot de passe change.
- Mot de passe oublié : lien à usage unique valable 1 h (seule son empreinte est stockée), 3 demandes max. par heure et par compte, réponse identique que le compte existe ou non.
- Installation protégée par un code lisible uniquement par FTP.
- Jeton anti-CSRF sur tous les formulaires de l'administration ; requêtes SQL préparées ; tout le contenu affiché est échappé.
- Photos envoyées : type vérifié, renommées au hasard, **réencodées** (suppression des données GPS/EXIF et de tout contenu caché), aucun script exécutable dans `photos/`.
- Base de données au nom aléatoire dans `data/`, dossier interdit d'accès (`.htaccess`), nom stocké dans un fichier PHP non téléchargeable.
- En-têtes de sécurité (anti-clickjacking, nosniff, HSTS en HTTPS, CSP sur l'administration), erreurs PHP jamais affichées.
- Statistiques plafonnées (anti-flood) et refus des envois venant d'autres sites.
- Réservation : prix toujours recalculé par le serveur, montant payé vérifié auprès de Stripe/PayPal, webhook signé, réservation créée sous verrou (pas de double réservation), 3 tentatives non payées max. par IP et par heure, 15 paiements en cours max. au total, API en JSON uniquement et même origine, liens des e-mails construits avec l'adresse configurée (jamais l'en-tête Host), remboursements sous verrou, agenda Google en HTTPS uniquement, CSP sur tout le site.
- Pour forcer le HTTPS sur tout le site, décommenter le bloc prévu dans `.htaccess`.
- Mot de passe oublié : supprimer la ligne du compte n'est pas possible sans outil SQLite ; demander de l'aide plutôt que de supprimer la base.

## Développement local

```bash
php -S localhost:8000
```
puis ouvrir http://localhost:8000 et http://localhost:8000/admin/

## Serveur Nginx (si pas Apache)

Les `.htaccess` ne sont pas lus par Nginx ; ajouter :
```nginx
location ~ ^/(data|includes)/ { deny all; }
location ~ /\. { deny all; }
location ~ ^/photos/.*\.php$ { deny all; }
location = /plan.html { return 301 /plan.php; }
location = /reservation.html { return 301 /reservation.php; }
```
