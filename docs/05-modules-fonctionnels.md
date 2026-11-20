# 05 — Modules fonctionnels

Ce document décrit les modules disponibles dans l'application, les écrans principaux et les actions typiques. Il complète le [modèle de données](07-modele-donnees.md) et la [matrice des rôles](06-roles-permissions.md).

## 1. Tableau de bord

Routes : `dashboard`, `dashboard.pointage`.

Le tableau de bord est adapté au rôle connecté via `RoleDashboardService`. Il affiche notamment :

- les indicateurs du jour (inscriptions en attente, conducteurs, présences, sujets) ;
- les accès rapides aux modules autorisés ;
- les tâches et notifications de l'espace métier.

Un conducteur d'émission dispose en plus d'une vue « pointage ».

## 2. Authentification et accès

Routes : `login`, `forgot-password`, `reset-password`, `verify-email`, `first-login-change-password`.

- Connexion avec protection anti force-brute (5 tentatives par IP et 10 par compte / 15 min).
- Les comptes créés par un administrateur doivent changer leur mot de passe à la première connexion (`EnsurePasswordIsChanged`).
- Journal de sécurité : connexions réussies/échouées, verrouillages, déconnexions, changements de mot de passe (canal `security`, 180 jours).
- Inscription publique du personnel : `/personnel/inscription`, formulaire + recherche de profil existant, demande enregistrée dans `inscription_personnel` sans création de compte.
- Les demandes sont traitées par les administrateurs dans `/personnel/inscriptions` (voir [11 — Sécurité](11-securite-audit.md) pour les contrôles ajoutés).

## 3. Conducteurs

Deux types coexistent :

### 3.1 Conducteur de rédaction (`type = redaction`)

- Préparation de la réunion de rédaction.
- Présences des journalistes (`conducteur_presences`), ponctualité.
- Ajout/édition de sujets (`sequences`) avec responsable, type et durée.
- Workflow : terrain → montage → habillage → livraison → diffusion.
- Règle : les contributeurs ne peuvent assigner que des journalistes **présents** à la réunion ; les responsables d'édition (admin, chef d'édition, etc.) peuvent assigner n'importe quel journaliste actif (voir [14 — Dépannage](14-depannage.md)).
- Transfert vers un conducteur d'émission.

### 3.2 Conducteur d'émission (`type = emission`)

- Timeline de plateau, séquences et invités.
- Deux vues principales :
  - **Chef d'édition** : `/conducteurs/chef-edition/{id}` — préparation, sujets, invités, débats ;
  - **Présentateur** : `/conducteurs/presentateur/...` — exécution, notes, chronomètre.
- Calcul automatique des heures de début/fin et de la durée totale (`ConducteurCalculatorService`).
- Verrouillage du conducteur après validation (`date_locked_at`).
- Chronomètre antenne (démarrer/pause/reset) et compte à rebours.
- Chat interne entre les rôles du conducteur.
- **Présentateur modifiable après création** : un responsable peut changer le présentateur depuis la barre d'en-tête du conducteur (choix parmi les intervenants actifs ou les journalistes).
- **Durée cible** : le formulaire de création propose 10 min à 6 h ; la validation accepte jusqu'à 21 600 secondes.
- Les flèches haut/bas de la colonne « Ordre » déplacent un sujet et **enregistrent immédiatement** le nouvel ordre (plus besoin d'entrer dans le mode « Réorganiser »).
- La **vue Présentateur** reprend ces deux contrôles : sélecteur de présentateur dans l'en-tête et flèches de réordonnancement sur chaque carte (et sur la table des grands écrans). Les états du chrono utilisent des icônes cohérentes avec le reste de l'interface.
- Une **vue unique** est désormais utilisée par tous les rôles (admin, chef d'édition, présentateur) : celle du Chef d'édition, enrichie du panneau antenne. Les routes `conducteurs.presentateur.show` et `conducteurs.emission.show` rendent la même interface. Les actions sont filtrées par les policies : le présentateur voit le chrono et ses notes, l'admin voit la totalité des actions.
- La vue Chef d'édition intègre un **panneau « Antenne & notes présentateur »** : chrono de l'émission (démarrer / pause / reset), progression, séquence en cours et notes présentateur enregistrables via l'endpoint existant. Toutes les actions admin restent disponibles au-dessus.
- Le bouton **« Discussion interne »** est présent dans la barre d'actions, en plus du lanceur flottant ; sur mobile, le lanceur flottant est remonté pour ne pas être masqué par l'assistant IA.
- Les notes présentateur sont **enregistrées automatiquement** (800 ms après la dernière frappe, et au changement de champ) avec un retour « Notes enregistrées » ou un message d'erreur. Un bouton **« Afficher le prompteur »** ouvre un petit écran séparé ; le prompteur s'ouvre aussi automatiquement au démarrage du chrono, avec vitesse réglable (5–90 px/s), pause et fermeture.
- La sélection groupée a été **retirée de la vue conducteur** (plus de barre « Tout sélectionner », ni cases correspondantes). Le composant Alpine du chrono est enregistré globalement pour que les boutons Démarrer / Pause / Reset s'affichent.
- En haut de la vue, **QR code public / messages**, **Antenne & notes présentateur** et **Sondage du débat** sont regroupés dans le même bloc d'interaction (2 colonnes, 3 sur très grand écran), avant le tableau. La page défile normalement (plus de conteneur à hauteur fixe) et la modale de création de séquence est resserrée (`max-w-lg`, une seule colonne).
- Pour les émissions **Débat**, un panneau **« Sondage du débat »** permet de créer le sondage, l'ouvrir ou le clore, afficher/télécharger le QR code, ouvrir la page de vote et suivre les résultats. Le QR est généré par `/sondages/{id}/qr.svg` et le vote public par `/s/{token}`.

## 4. Émissions

Routes : `emissions.*`.

- Catalogue des émissions avec type (JT, magazine, débat, etc.), catégorie, statut, horaires, langues, cible.
- Fiche détaillée : équipe (chef d'édition, présentateurs), conducteurs, séquences.
- Statuts : brouillon, planifiée, en cours, diffusée, archivée, annulée.
- Le type `Débat` ouvre la fonctionnalité de sondage en direct.
- QR code public d'interaction (`/i/emission/{token}`) permettant au public d'envoyer des questions.

## 5. Séquences

Routes : `conducteurs.sequences.*`, `sequences.*`, `redaction.sujet.*`, `presentateur.sequence.*`.

- Création d'une séquence : titre, type, durée, position, responsable, invités.
- Types notables : `SUJET`, `REPORTAGE`, `INTERVIEW`, `DÉBAT`, `INVITÉ PLATEAU`, etc.
- Règle débat : 2 à 4 intervenants avec rôle `debatteur`.
- Ordre des intervenants via la table pivot `sequence_intervenants` (`position`, `role_dans_sequence`, `ordre_passage`).
- Durée estimée d'un sujet : jusqu'à 600 minutes (10 h) côté formulaire et validation.
- Workflow par colonnes et notes présentateur/privées.

## 6. Présences et conférences

Routes : `redaction.presence.*`, `conferences.*`.

- Pointage des journalistes à la réunion (ajout, heure d'arrivée, suppression).
- Reconciliation en lot, marquage de la ponctualité.
- Un journaliste peut marquer sa propre présence via une policy dédiée.
- Conférences : decisions et suivi (`Conference`, `ConferenceDecision`).

## 7. Intervenants et logistique

Routes : `intervenants.*`, `parcours-intervenants.*`, `scanner-qr.*`.

- Fiches intervenants : identité, civilité, fonction, contact, photo, disponibilité.
- Génération automatique d'un QR code signé à la création (`QrCodeService`, JWT HS256).
- Invitation publique : `/i/{token}` ; check-in : `/i/checkin/{token}.png`.
- Scanner QR interne : validation avec `hash_equals`, journalisation des tentatives (IP, UA).
- Suivi logistique : arrivée, maquillage, plateau, diffusion, fin de passage, no-show.
- Parcours du jour, affectation aux émissions.

## 8. Journalistes

Routes : `journalistes.*`.

- Annuaire interne, fiches, fonction (enum `FonctionJournalisteEnum`, dont `jri`), pôle, statut.
- Création du compte utilisateur associé (`JournalisteAccountService`), matricule (`MatriculeEmployeService`).
- Portraits via MediaLibrary, validation des fichiers (`PortraitService`, `MediaValidation`).

## 9. Veille

Routes : `veille.*`.

- Sources (RSS/HTML), sujets de veille, remixes IA.
- Automatisation par commandes planifiées (`GabonVeilleAgent`, `VeilleAutomationService`, `ScraperService`).
- Contrôle SSRF : seules les URLs publiques sont autorisées (`UrlPublique`).
- API interne protégée par secret : `/api/internal/veille`, `/api/internal/readiness`.

## 10. Espace métier partagé

Routes : `workspace.*`, `espace-metier/*`.

- Dossiers partagés (`WorkspaceDossier`), tâches, commentaires, pièces jointes, notifications, activités.
- Permissions fines : consultation (propre dossier / tous), gestion des membres, commentaire, création de tâches.
- Intégré aux conducteurs et aux publications digitales.

## 11. Publications numériques

Routes : `digital.*`.

- Cycle : brouillon → à valider → correction demandée → validé → planifié → publié.
- Plateformes : site, Facebook, Instagram, YouTube, TikTok, X, WhatsApp.

## 12. Éditorial

Routes : `sujets-editoriaux.*`, `evenements-editoriaux.*`.

- Sujets éditoriaux et événements, vérifications, liens avec les espaces partagés.

## 13. Utilisateurs et permissions

Routes : `users.*`, `utilisateurs-permissions.*`, `personnel.*`.

- CRUD utilisateurs protégé par `UserPolicy` : rôles assignables, activation, réinitialisation du mot de passe.
- Matrice des permissions modifiable par les administrateurs dans `config/role_access.php` et l'interface dédiée.
- Suppression en masse avec `Gate::authorize()` par enregistrement.
- Inscriptions du personnel à valider.

## 14. Interactions publiques et sondage

Routes publiques : `invitations.*`, `emission-interactions.*`, `sondage.*`.

- Pages invitées et d'interaction avec en-têtes `noindex`.
- Sondage en direct pour les émissions de type débat : voir [09 — Sondage direct](09-sondage-direct.md).

## 15. Alertes info globales et bulles pop-up

Contrairement aux anciennes alertes liées à une séquence, une alerte info est désormais un objet autonome créé par l'administration :

- formulaire « Diffuser une alerte » dans la barre d'actions du conducteur (admin/superadmin) ;
- champs : titre, message, niveau (information / avertissement / urgence), date d'expiration facultative ;
- chaque alerte s'affiche sur **toutes les pages internes** sous forme de **bulle pop-up fixe en haut à droite**, avec un **clignotement rouge persistant** ;
- les administrateurs peuvent retirer une alerte (passage à `actif = false`) ; chaque utilisateur peut aussi masquer une bulle localement sur la page courante ;
- les alertes expirées ne sont plus affichées.

La pile de notifications est rendue par `resources/views/_partials/notifications-stack.blade.php`, incluse dans `layouts/app.blade.php`. Elle se positionne automatiquement sous la barre supérieure (hauteur mesurée par `ResizeObserver`).

Sur la vue conducteur (`conducteur_redaction.blade.php`), `resources/views/_partials/bulles-conducteur.blade.php` ajoute deux autres bulles dans la même pile :

- **Message en cours** : séquence active du conducteur, synchronisée avec le prompteur via l'événement `tv-sequence-active` ; bordure et point rouges clignotants quand le chrono est lancé ;
- **Message du public** : le dernier message reçu via le QR public s'affiche en pop-up (polling de 10 s sur `conducteurs.emission.interactions.index`), avec compteur de nouveaux messages, bouton « Voir les messages » et fermeture automatique après 22 s.

### 15.1 Bulles sur toutes les autres sections

`resources/views/_partials/notifications-globales.blade.php`, inclus dans `layouts/app.blade.php`, étend ces bulles à **toutes les pages internes** :

- **Message en cours global** : l'endpoint `GET /notifications/etat` (`NotificationController@etat`) renvoie le conducteur d'émission en direct ou en pause du jour, sa séquence active et l'URL du conducteur. La bulle cliquable est affichée sur le tableau de bord, les listes, la veille, les journalistes, les intervenants, l'administration, etc.
- **Messages du public globaux** : le même endpoint renvoie de façon incrémentale (`?after=ID`) les nouveaux messages du public, tous conducteurs confondus. La bulle apparaît sur n'importe quelle page avec le nom de l'émission et un bouton qui ouvre le conducteur concerné sur `#tv-qr-public-card`.
- Sur la page conducteur, les bulles locales prennent le relais : le script global détecte `window.__tvBullesConducteur` et ne démarre pas, pour éviter tout doublon.
- L'endpoint est protégé par `auth`, `verified` et la permission `conducteurs.view` (`Gate::allows('viewAny', Conducteur::class)`).

Table : `alerte_infos`. Migration : `2026_11_20_191249_create_alerte_infos_table.php`. La table a été créée en production le 20/11/2026 et la migration enregistrée dans la table `migrations`.

## 16. PWA

Le manifest, le service worker et la page hors-ligne sont décrits dans [10 — PWA](10-pwa.md).
