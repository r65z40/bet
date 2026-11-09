# La Salle RN6 — site + administration

Site vitrine de location de salle (Appoigny) avec une administration simple.

**Stack : PHP 8 + SQLite.** Pas de framework, pas de Composer, pas de npm, pas de serveur MySQL.
Fonctionne sur n'importe quel hébergement mutualisé PHP (OVH, o2switch, Hostinger, IONOS…).

## Installation

1. Envoyer **tout le dossier** sur l'hébergement (FTP), à la racine du site.
2. Vérifier que les dossiers `data/` et `photos/` sont **accessibles en écriture** par PHP (droits 755 ou 775).
3. Ouvrir `https://votre-site.fr/admin/` → créer le compte administrateur (une seule fois).

C'est tout : la base `data/site.sqlite` est créée automatiquement et pré-remplie avec le contenu actuel du site.

> Prérequis : PHP ≥ 8.1 avec les extensions `pdo_sqlite` et `fileinfo` (activées par défaut presque partout).

## Ce que l'on peut modifier dans l'administration (`/admin/`)

| Menu | Contenu |
|---|---|
| **Textes** | Tous les titres et paragraphes, e-mail, adresse, liens Facebook / Maps / 1001Salles, référencement Google, pages Plan et Réservation (identifiants SuperSaaS) |
| **Tarifs** | Prix par jour, infos (caution…), options & services : modifier, ajouter, supprimer, réordonner, masquer |
| **Listes & cartes** | Points forts, cartes « La salle », équipements, mobilier, vaisselle, accès, événements, surfaces du plan |
| **Photos** | Envoi multiple, légendes, ordre, masquer, supprimer, choix de l'image du bandeau d'accueil, remplacement du plan |
| **Statistiques** | Visiteurs, pages vues, provenance (Google, Facebook…), appareils, jours/heures, clics sur « Réserver », e-mail, Maps, photos… et taux de conversion, avec comparaison à la période précédente |
| **Mon compte** | Identifiant et mot de passe |

Dans les textes, `**mot**` affiche le mot en **gras**.

## Statistiques et RGPD

Mesure maison, sans Google Analytics ni cookie : l'adresse IP n'est jamais enregistrée (seulement un hash anonyme qui change chaque jour). Les robots et l'administrateur connecté ne sont pas comptés. Les données de plus de 25 mois sont effacées automatiquement. Aucun bandeau cookies n'est donc nécessaire pour ces statistiques.

## Structure

```
index.php, plan.php, reservation.php   pages publiques (même design que l'ancien site HTML)
track.php                              réception des clics pour les stats
admin/                                 administration
includes/                              code commun (base, fonctions, contenus par défaut) — non accessible du web
data/                                  base SQLite — non accessible du web
photos/                                photos du site (les scripts y sont bloqués)
```

Les anciennes adresses `plan.html` et `reservation.html` sont redirigées (301) vers les pages PHP via `.htaccess`.

## Sauvegarde

Sauvegarder régulièrement `data/site.sqlite` et le dossier `photos/` (c'est tout le contenu du site).

## Développement local

```bash
php -S localhost:8000
```
puis ouvrir http://localhost:8000 et http://localhost:8000/admin/

## Serveur Nginx (si pas Apache)

Les `.htaccess` ne sont pas lus par Nginx ; ajouter :
```nginx
location ~ ^/(data|includes)/ { deny all; }
location ~ ^/photos/.*\.php$ { deny all; }
location = /plan.html { return 301 /plan.php; }
location = /reservation.html { return 301 /reservation.php; }
```
