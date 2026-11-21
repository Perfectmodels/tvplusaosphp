# TV+A OS — Harmonisation Émissions, Veilles et Intervenants

Cette livraison harmonise trois espaces fonctionnels existants sans modifier les
règles de permission, les routes métier ni le stockage des données.

## Émissions

- Catalogue : quatre indicateurs réels (total, actifs, programmés aujourd'hui,
  horaires à renseigner), filtres accessibles et états vides structurés.
- Fiche programme : les anciens boutons inactifs « Nouveau conducteur »
  et « Ouvrir » sont remplacés par des liens vers des routes existantes.
- Conducteurs associés : seuls les conducteurs d'émission sont affichés, avec
  code, nom, date et statut. Les actions respectent les permissions.
- Aucune action de création de conducteur si l'émission est inactive.
- Couleurs d'habillage non valides remplacées par le bleu TV+A OS par défaut.

## Veilles

- Liste : grille responsive, filtres dans un panneau homogène.
- La réinitialisation prend en compte pays, flux, statut et rubrique.
- Import d'URL : dialogue nommé, focus initial, Tab confiné, fermeture Échap,
  retour du focus au déclencheur.
- Sujet détaillé : onglets accessibles par flèches et Home/End ; édition avec
  champs étiquetés, responsive.
- Sources : validation navigateur en plus du serveur, labels et tableau
  accessible au clavier.

## Intervenants

- Répertoire : cartes plus lisibles (1 à 4 colonnes au lieu de 6), cibles
  tactiles et filtres nommés.
- Filtres rapides conservant les filtres détaillés déjà sélectionnés.
- Distinction explicite des statuts Occupé et Interdit.
- Le lien « Booker » devient « Gérer le parcours » et pointe vers le
  parcours existant du profil.
- Création / édition : récapitulatif des erreurs serveur, labels pour les
  champs d'identité et photo accessible.
- Fiche : retrait du faux « Exporter PDF » qui ne produisait aucun PDF.
  Régénération du QR via un formulaire POST réel, avec permission serveur.
  La consultation du badge QR est nommée explicitement.

## Régression et tests

Commande : php artisan test --filter=EditorialModuleUiTest.

Les contrôles couvrent la navigation du catalogue, les liens de conducteur,
le répertoire, le QR et les dialogues / onglets de veille.

Vérifier en navigateur : 375, 768, 1280 et 1920 px ; comptes lecteur et
éditeur ; erreur réseau, données vides, Tab, Maj+Tab, Échap ; validation des
formulaires ; lecture et régénération du QR.

La fusion GitHub main et le déploiement LWS sont deux opérations distinctes.
