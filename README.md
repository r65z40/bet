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
| **Mon compte** | Identifiant et mot de passe |
| **Tableau de bord** | Bouton pour passer la réservation en ligne **en maintenance** (message personnalisé à la place de l'agenda SuperSaaS, modifiable dans Textes › Réserver en ligne) |

### Côté visiteurs
- Menu « hamburger » sur mobile, animations d'apparition et chiffres clés animés (désactivés si le visiteur a demandé « réduire les animations »).
- Carte Google Maps interactive affichée directement dans la section Accès (chargée par le navigateur à l'approche de la section, pour ne pas ralentir le haut de page). Le lieu se règle dans Textes › Carte.
- Galerie en WebP avec vignettes légères ; la photo en grand n'est chargée qu'à l'ouverture (balayage gauche/droite sur mobile).

Dans les textes, `**mot**` affiche le mot en **gras**.

## Réservation en ligne (remplace SuperSaaS)

Page `reservation.php` : formule **24 h** (heure fixe, tarif du jour d'arrivée) ou **à l'heure** (8 h–21 h, 2 h minimum), calendrier des disponibilités, type d'événement, nombre de personnes, options payantes, remise association, coordonnées, acceptation des conditions, puis **paiement intégral** par **Stripe** (carte) ou **PayPal**.

- **Prix** : saisis HT dans *Admin › Tarifs & options*, affichés et encaissés TTC. Location à l'heure = prix du jour × heures ÷ 24 × coefficient (règles modifiables, simulation dans l'admin), plafonnée au prix 24 h.
- **Disponibilités** : réservations payées, paiements en cours (créneau bloqué 35 min), blocages manuels et **événements de l'agenda Google** ; **temps mort de 2 h** autour de chacun. Délai minimum 48 h, maximum 18 mois.
- **Après paiement** : confirmation immédiate, facture numérotée (F2026-0001…), e-mail au client (récapitulatif, facture, « Ajouter à mon agenda ») et alerte au propriétaire.
- **Admin › Réservations** : liste, planning mensuel, fiche (caution, notes, renvoi d'e-mail), **annulation avec remboursement** automatique Stripe/PayPal (montant proposé selon les conditions d'annulation) et avoir (A2026-0001…), blocage de créneaux.
- **Agenda** : l'« adresse secrète iCal » de Google Agenda bloque les créneaux (lecture toutes les 10 min et juste avant chaque paiement) ; un lien d'abonnement privé affiche les réservations dans l'iPhone.

### Mise en service (une fois)
1. *Admin › Réservations › Réglages › Société* : compléter forme juridique, RCS, vérifier SIREN et TVA.
2. *E-mails* : créer un **mot de passe d'application Gmail** (Compte Google › Sécurité › Validation en deux étapes › Mots de passe des applications), le coller, cliquer « Envoyer un e-mail de test ».
3. *Paiement* : clé secrète Stripe (`sk_test_…` pour tester), webhook Stripe vers `https://lasallern6.fr/stripe-webhook.php` (événement `checkout.session.completed`), Client ID + Secret PayPal (mode sandbox pour tester). Bouton « Tester la connexion ».
4. *Agenda Google* : coller l'adresse secrète iCal ; ajouter le lien d'abonnement dans l'iPhone.
5. *Annulation & conditions* : faire relire les conditions de location.
6. Tester une réservation en mode test, puis passer les clés en production.

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
- Installation protégée par un code lisible uniquement par FTP.
- Jeton anti-CSRF sur tous les formulaires de l'administration ; requêtes SQL préparées ; tout le contenu affiché est échappé.
- Photos envoyées : type vérifié, renommées au hasard, **réencodées** (suppression des données GPS/EXIF et de tout contenu caché), aucun script exécutable dans `photos/`.
- Base de données au nom aléatoire dans `data/`, dossier interdit d'accès (`.htaccess`), nom stocké dans un fichier PHP non téléchargeable.
- En-têtes de sécurité (anti-clickjacking, nosniff, HSTS en HTTPS, CSP sur l'administration), erreurs PHP jamais affichées.
- Statistiques plafonnées (anti-flood) et refus des envois venant d'autres sites.
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
