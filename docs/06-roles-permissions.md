# 06 — Rôles et permissions

La source de vérité est `config/workspaces.php` (liste des rôles) et `config/role_access.php` (matrice permissions ↔ rôles), complétée par les `Policies` dans `app/Policies/`.

## 1. Rôles disponibles

| Clé | Libellé |
|---|---|
| `chef_edition` | Chef d'édition |
| `chef_redaction` | Rédacteur en chef |
| `journaliste` | Journaliste |
| `realisateur` | Réalisateur |
| `community_manager` | Community Manager |
| `correspondant` | Correspondant |
| `presentateur` | Présentateur |
| `monteur` | Monteur |
| `documentaliste` | Documentaliste |
| `responsable_administratif` | Responsable administratif |
| `directeur_general` | Direction générale |
| `admin` | Administrateur |
| `superadmin` | Superadministrateur |
| `chef_plateau` | Chef de plateau |
| `habilleur` | Habilleur |
| `receptionniste` | Réceptionniste |
| `lecture` | Lecture seule |

Les rôles sont gérés avec Spatie Permission (guard `web`). Plusieurs rôles peuvent être attribués à un même utilisateur.

## 2. Groupes de rôles utilisés dans la matrice

`config/role_access.php` définit des raccourcis :

| Variable | Rôles |
|---|---|
| `$leaders` | admin, superadmin, directeur_general, chef_edition, chef_redaction |
| `$adminAndEdition` | admin, superadmin, chef_edition |
| `$journalisteRoles` | journaliste, correspondant |
| `$editorialContributors` | leaders + journaliste + correspondant |
| `$conductorReaders` | leaders + journaliste, presentateur, habilleur, chef_plateau, réceptionniste, lecture, correspondant, realisateur, monteur, responsable_administratif |
| `$allRoles` | tous les rôles de `config/workspaces.php` |

## 3. Domaines de permissions

| Domaine | Exemples de permissions |
|---|---|
| Espace métier | `workspace.view`, `workspace.view_all`, `workspace.manage_members`, `workspace.comment`, `workspace.create_tasks` |
| Conducteurs | `conducteurs.view`, `.create`, `.edit_all`, `.edit_own`, `.validate`, `.transfer`, `.unlock`, `.delete`, `.reorder`, `.pilot_air` |
| Présences | `presence.view`, `presence.manage`, `presence.mark_self` |
| Séquences | `sequences.view`, `.create_editorial`, `.edit_all`, `.edit_own`, `.delete_*`, `.reorder_*`, `.workflow.*`, `.presenter_notes`, `.private_notes` |
| Émissions | `emissions.view`, `.create`, `.update`, `.delete` |
| Journalistes | `journalistes.view`, `.create`, `.update`, `.delete` |
| Intervenants | `intervenants.view`, `.create`, `.update`, `.delete`, logistique |
| Utilisateurs | `users.view`, `.create`, `.update`, `.delete`, `.assign_roles`, `.reset_password` |
| Activité | `activity_logs.view` |
| Veille | `sources_veille.view`, `.scrape`, `remixes_veille.view` |
| Digital | `digital.*`, `digital.approve` |
| Sondage | création/ouverture/clôture via `SondagePolicy` |

La table complète est dans `config/role_access.php` ; l'interface « Utilisateurs & Permissions » permet de la modifier sans éditer le fichier.

## 4. Règles transverses

### 4.1 Admin / superadmin

Plusieurs policies implémentent une méthode `before()` qui autorise systématiquement `admin` et `superadmin` (ex. `ConducteurPolicy`, `SequencePolicy`). Ils héritent également de toutes les permissions de la matrice.

### 4.2 Propriété

Certaines actions sont limitées au créateur ou au responsable :

- `ConducteurPolicy::update()` : `edit_all` ou `edit_own` + `owner_id` égal à l'utilisateur ;
- `SequencePolicy` : `edit_own` + journaliste lié à la séquence (`User::journalisteLie()`) ;
- `SondagePolicy` : admin/superadmin, ou chef d'édition affecté via `emission.chef_edition_id`.

### 4.3 Verrouillage

Lorsqu'un conducteur est verrouillé (`date_locked_at`), la plupart des modifications sont refusées, sauf permissions explicites (`edit_all`, `unlock`).

### 4.4 Présence à la réunion

Les contributeurs (journaliste, correspondant) ne peuvent assigner qu'un journaliste présent dans `conducteur_presences`. Les responsables d'édition sont exemptés de cette règle (décision du 20/11/2026).

### 4.5 Sondage en direct

- Création/ouverture/clôture : admin, superadmin, chef d'édition affecté à l'émission.
- Vote : public anonyme, aucune permission requise, jeton QR + anti-double-vote.
- Voir [09 — Sondage direct](09-sondage-direct.md).

## 5. Middleware de contrôle

| Alias | Classe | Usage |
|---|---|---|
| `auth` | Authenticate | Connexion requise |
| `verified` | EnsureEmailIsVerified | E-mail vérifié |
| `password.changed` | EnsurePasswordIsChanged | Mot de passe initial changé |
| `role` | Spatie RoleMiddleware | `role:admin|superadmin` |
| `permission` | Spatie PermissionMiddleware | Contrôle fin |
| `throttle` | Laravel | Limitation de débit |

Les routes d'administration se trouvent surtout dans `routes/module_autres.php` et `routes/module_users.php` ; les routes publiques dans `routes/auth.php`, `module_invitation_publique.php` et `module_sondage.php`.

## 6. Bonnes pratiques

- Ajouter une action → créer/compléter une Policy, puis un test Feature qui vérifie le 403.
- Ne jamais confondre validation et autorisation.
- Tester les combinaisons de rôles avec `tests/Feature/RoleAccessMatrixTest.php`.
- Pour un nouveau rôle : l'ajouter dans `config/workspaces.php` puis dans les groupes de `config/role_access.php` et exécuter le seeder de permissions.
