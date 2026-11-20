# 01 — Présentation

## 1. Finalité

TV+A OS est une application web de gestion de production audiovisuelle. Elle couvre tout le cycle de vie d'un programme de télévision :

1. **Réunion de rédaction** : présences, sujets proposés, décisions.
2. **Conducteur de rédaction** : planification éditoriale des sujets, affectation aux journalistes/JRI, workflow terrain → montage → habillage → livraison → diffusion.
3. **Conducteur d'émission** : timeline de plateau, séquences, invités, présentateur, chronomètre, chat interne.
4. **Antenne** : pilotage du direct, interaction du public (questions, sondage en direct).
5. **Après émission** : veille, publications numériques, archives, suivi logistique des intervenants.

L'application est à la fois un outil interne (rédaction, plateau, direction) et une plateforme publique limitée (inscription du personnel, pages invitées, vote par QR code).

## 2. Utilisateurs

| Profil | Usage principal |
|---|---|
| Direction générale | Vue consolidée, validation, réglages |
| Administrateur / superadministrateur | Utilisateurs, rôles, permissions, configuration |
| Chef d'édition / rédacteur en chef | Conducteurs, rédaction, affectation des sujets |
| Journaliste / correspondant / JRI | Sujets personnels, terrain, montage, livraison |
| Présentateur / réalisateur / chef de plateau | Conducteur antenne, séquences, direct |
| Monteur / habilleur / documentaliste | Étapes dédiées du workflow |
| Logistique / accueil | Intervenants, QR codes, check-in, présences |
| Lecteur | Consultation seule |
| Téléspectateur (anonyme) | Page publique de vote via QR code |

La liste complète des rôles est dans [06 — Rôles et permissions](06-roles-permissions.md).

## 3. Parcours type

```text
Réunion de rédaction
   → Conducteur de rédaction (sujets, responsables, durées)
   → Workflow (terrain, montage, habillage, livraison)
   → Transfert vers conducteur d'émission
   → Timeline plateau (séquences, invités, débat)
   → Antenne (chronomètre, direct, sondage)
   → Veille & publications numériques
```

## 4. Grande fonctionnalités

- **Tableaux de bord** différenciés par rôle, avec indicateurs et raccourcis.
- **Conducteurs** : rédaction et émission, avec calcul automatique des horaires et durées.
- **Émissions** : catalogue, types (JT, magazine, débat…), statuts, équipe, conducteurs.
- **Séquences** : sujets, type (SUJET, DÉBAT, REPORTAGE…), intervenants, durées, étapes.
- **Présences** : pointage des journalistes à la réunion, ponctualité.
- **Intervenants** : fiches, photos, logistique, QR code signé, scanner de check-in.
- **Veille** : sources, sujets, remixes IA, automatisation (cron/planification).
- **Espace métier partagé** : dossiers, tâches, commentaires, pièces jointes, notifications.
- **Publications numériques** : brouillon → validation → planification → publication.
- **Documentation et archives** : historique, activités, journal de sécurité.
- **Sondage en direct** : pour les émissions de type débat, QR code et vote anonyme.
- **PWA** : installation, icônes, page hors-ligne, cache applicatif.

## 5. État de production (référence)

Au 20 novembre 2026 :

- production sur `https://tvplusaos.com` ;
- mode `production`, `APP_DEBUG=false` ;
- PWA opérationnelle (service worker v4) ;
- accès MySQL rétabli après une panne d'authentification du 19/11 (voir [11 — Sécurité et audit](11-securite-audit.md)) ;
- sondage en direct déployé et vérifié de bout en bout.

## 6. Organisation du projet

Le code applicatif réside dans `conducteurApp/` (racine web sur l'hébergeur). Les fichiers publics (assets Vite, images, PWA) sont servis depuis la racine web. La configuration serveur est dans `.htaccess` à la racine.

## 7. Vocabulaire minimal

| Terme | Sens |
|---|---|
| Conducteur | Document de production du jour (rédaction ou émission) |
| Séquence | Un sujet / un bloc dans un conducteur |
| Débat | Type d'émission ou de séquence avec 2 à 4 débatteurs |
| Intervenant | Personne invitée (externe ou interne) participant à une séquence |
| Journaliste | Personnel interne, parfois JRI (journaliste reporter d'images) |
| JRI | Journaliste reporter d'images, rôle/fonction interne |
| QR signé | Jeton JWT ou opaque permettant un accès public contrôlé |
| Veille | Veille informationnelle automatisée (sources, sujets, remixes IA) |

Le lexique complet est dans [13 — Lexique](13-lexique.md).
