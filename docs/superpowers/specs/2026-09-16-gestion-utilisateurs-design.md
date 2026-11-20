# Spec — Système de Gestion des Utilisateurs TV+A OS

> **Date:** 2026-09-16
> **Owner:** Admin TV+A OS
> **Statut:** ⚙️ Approuvé utilisateur — Prêt pour implémentation
> **Relations:** Laravel 11 Breeze + Spatie Permission + Journalistes RH existants

---

## 1. But (User Need Verbatim)

« Ajouter un système de gestion des utilisateurs : créer un utilisateur de l'outil avec **DEUX possibilités** :
1. **Se baser sur la liste des journalistes** → si on en sélectionne un, **prérenseigner les champs** avec ses informations, puis choisir **un rôle** parmi 5, définir un mot de passe (temporaire).
2. **Créer directement** → si celui-ci n'est pas journaliste, on renseigne les champs manuellement, on choisit **un rôle** parmi 5, on définit un mot de passe (temporaire).
Dans **les deux cas : première connexion → on INVITE l'utilisateur à CHANGER son mot de passe (obligatoire avant toute navigation). »**

---

## 2. Architecture & Contraintes Tech (NON NÉGOCIABLES)

| Contrainte | Valeur | Source |
|---|---|---|
| **Framework Auth** | Laravel 11 Breeze (User Authenticatable implémente MustVerifyEmail) | [User.php](file:///c:/Users/DELL/Desktop/tvplusos/conducteurApp/app/Models/User.php#L14) |
| **Rôles & Permissions** | **Spatie Permission 6** (trait `HasRoles` sur User + modèle `Role`/`Permission` custom + pivot `model_has_roles`) | [User.php](file:///c:/Users/DELL/Desktop/tvplusos/conducteurApp/app/Models/User.php#L16) |
| **Liste rôles UI assignation** (EXHAUSTIF, 5 exacts, ENUM hardcodé) | `admin` · `journaliste` · `chef_redaction` · `chef_edition` · `directeur_general` — **pas plus, pas moins** | Verbatim user |
| **Rôles qui existent DÉJÀ en base** | `admin`, `journaliste`, `chef_edition`, `presentateur`, `habilleur`, `lecture`, `receptionniste`, `chef_plateau` | [DatabaseSeeder.php](file:///c:/Users/DELL/Desktop/tvplusos/conducteurApp/database/seeders/DatabaseSeeder.php#L19-L26) |
| **Rôles MANQUANTS à créer** | `chef_redaction` + `directeur_general` (2 à ajouter au seeder) | Verbatim user |
| **Droits créer/modifier/supprimer user** | **`admin` UNIQUEMENT OU `directeur_general` UNIQUEMENT** (2 rôles) — tous les autres ont accès en lecture seulement si besoin (par défaut NON, index seulement si Admin/DG) | Recommandation 2-A approuvée |
| **Relation User ↔ Journaliste** | **1 User ↔ 0 ou 1 Journaliste MAX** (jamais plusieurs). FK support = `journalistes.user_id` (existant, nullable, BelongsTo Journaliste → User). L'inverse côté User = `HasOne Journaliste`. | Recommandation Q1 approuvée |
| **Mode A sélection Journaliste** | Dans la liste déroulante → **seulement les journalistes dont `user_id IS NULL`** (pas de compte déjà) | Recommandation 4-A approuvée |
| **Mot de passe initial (temporaire)** | **Généré automatiquement 12 caractères [A-Za-z0-9!@#$%]** — bouton 🎲 dans champ readonly + bouton 📋 "Copier dans presse-papier" (Clipboard API). Pas de saisie manuelle. | Recommandation 3-B approuvée |
| **Mot de passe user** | Hashé `Hash::make()` (Laravel cast `password => hashed` existe déjà) | [User.php](file:///c:/Users/DELL/Desktop/tvplusos/conducteurApp/app/Models/User.php#L39) |
| **Flag première connexion** | Colonne **`password_changed_at` (TIMESTAMP nullable)** sur `users`. Si `NULL` → DOIT changer mdp avant toute navigation. | Standard industrie |
| **Middleware 1ère connexion** | Global sur **TOUTES les routes `auth` web SAUF** : `/logout` · `/first-login-change-password` · `/forgot-password` · `/reset-password` · routes verify. Si `password_changed_at === null` → 302 `/first-login-change-password`. | Middleware dédié `EnsurePasswordIsChanged` |
| **Mode A — Prérenseignements Journaliste → User** (auto dans le formulaire, pas editable côté formulaire une fois le journaliste choisi ? — Non: editable pour correction, mais prérempli) | Prérenseigner editable : `name` (si `journaliste` = concat civilite+prenom+nom ? **NON** → User `name` = `"$prenom $nom"` sans civilité), `email` (journaliste.email), `phone` (journaliste.phone / journaliste.telephone), `profile_photo_path` (journaliste.photoUrl → Optionnel : si Spatie Media portrait, l'utiliser). Champs civilité/nom/prenom NE SONT PAS dans users legacy → on prérenseigne name, email, phone seulement (colonne existantes). | Correspondance colonnes |
| **Mode A — Update du journaliste après création du user** | `Journaliste.user_id = $newUser->id` (dans la **même DB transaction** que le INSERT users → rollback si erreur). | Intégrité BDD |
| **Email unique** | Validation `unique:users,email` dans `CreateUserRequest` | Contrainte SQL existante `users.email UNIQUE` |
| **UI Design** | Identique à Conducteurs Rédaction / Journalistes : Tailwind M3 palette (`bg-surface`, `bg-primary-container`, `text-error` pour actif) + Alpine.js + Material Symbols Outlined + SweetAlert2 TvGlobals pour confirm delete. | Conventions UI projet |

---

## 3. Périmètre Fonctionnel (In Scope / Out of Scope)

### ✅ IN SCOPE

- Page **Liste utilisateurs** `/users` → tableau, filtre role/actif/type (Journaliste lié / Standalone), actions Éditer / Supprimer / Voir
- Page **Créer utilisateur** `/users/create` → **Formulaire 2 onglets (Tabs Alpine)** :
  - 🟦 Onglet 1 : « Depuis un journaliste » (Mode A)
  - ⚪ Onglet 2 : « Manuellement » (Mode B)
- Page **Éditer utilisateur** `/users/{user}/edit` → éditer rôle, nom, email, téléphone, is_active + possibilité **Réinitialiser mot de passe** (bouton "Forcer changement mdp" = set `password_changed_at = null` + générer nouveau mdp temporaire + copier)
- Page **1ère connexion — Changer mot de passe** `/first-login-change-password` → formulaire bloquant 2 champs : `Nouveau mot de passe` / `Confirmation mot de passe` (min 8 caractères). Une fois validé : `Hash::make(mdp)` → `password_changed_at = now()` → 302 `/dashboard`
- **Bouton "Désactiver le compte"** (toggle `is_active` bool déjà existant). Compte inactif ne peut pas se connecter (LoginRequest : ajouter règle `where is_active = true`).
- Sidebar : Nouvelle section **« Administrer »** → lien **« Gestion des utilisateurs »** (icône `manage_accounts`)
- Seeder DatabaseSeeder : Mise à jour rôles manquants + création d'un **1 compte Directeur Général démo** (email `dg@tvplusos.tv`)

### ❌ HORS SCOPE (on ne fait PAS — futur possible)

- ~~Envoi d'email d'invitation/SMTP pour prévenir user créé~~ → Pas de config MAIL pour l'instant (admin donne le mdp temporaire manuellement)
- ~~Historique / logs des actions création users~~ → Hors scope
- ~~Plusieurs rôles par utilisateur~~ → User = 1 rôle exact (Spatie le supporte mais UI ne permet que l'assignation d'1 rôle : `assignRole()` → retirera les autres)
- ~~Avatar upload sur création user~~ → Photo = héritée du journaliste (Mode A) ou ui-avatars.com fallback (Mode B)
- ~~Suppression en cascade du Journaliste quand on supprime son User~~ → Non ! Supprimer un User = seulement `journalistes.user_id = NULL`. On ne supprime JAMAIS le Journaliste RH quand on supprime son compte connexion.

---

## 4. Spécifications Données (Colonnes table users — Modifications)

| Colonne | Type | Actuelle ? | Nouvelle valeur (défaut) | Raison |
|---|---|---|---|---|
| `password_changed_at` | `timestamp nullable` | ❌ | `NULL` | Flag 1ère connexion |
| `journaliste_id` | `bigint unsigned nullable FK journalistes.id` | ❌ | `NULL` | Miroir inverse pour faciliter les jointures. **IMPORTANT : redondant avec `journalistes.user_id`, mais utile pour requêtes SQL « Donne-moi le user de ce journaliste ». Mettre à jour les DEUX côtés en DB Transaction dans Mode A.** |
| `created_by` | `bigint unsigned nullable FK users.id` | ❌ | `NULL` | Audit : Qui a créé cet utilisateur ? (admin ou DG) |

**Migration à créer** : `2026_09_16_000007_upgrade_users_gestion_table.php` (après la 000006).

---

## 5. Rôles Spatie (liste COMPLÈTE finale dans UI assignation)

⚠️ **Interface UI propose SEULEMENT ces 5 rôles** (même si d'autres existent en base). Rôles non listés (`presentateur`, `habilleur`, etc.) ne sont PAS des rôles "connexion compte standard" — ils sont managés différemment :

| Nom rôle Spatie | Label affiché UI | Level | Qui peut l'assigner ? |
|---|---|---|---|
| `admin` | 🛡️ Administrateur | 99 | Admin uniquement |
| `directeur_general` | 👔 Directeur Général | 95 | Admin uniquement |
| `chef_redaction` | ✒️ Chef de Rédaction | 70 | Admin + DG |
| `chef_edition` | 📋 Chef d'Édition | 60 | Admin + DG |
| `journaliste` | 🎙️ Journaliste | 30 | Admin + DG |

Dans **DatabaseSeeder**, on ajoutera :
```php
Role::firstOrCreate(['name' => 'chef_redaction', 'guard_name' => 'web']);
Role::firstOrCreate(['name' => 'directeur_general', 'guard_name' => 'web']);
```
Puis un compte user démo DG : `dg@tvplusos.tv / password`, rôle DG.

---

## 6. Workflow Création Mode A · Depuis un Journaliste (DÉTAIL ÉTAPE PAR ÉTAPE)

```
1. Admin/DG arrive sur /users/create → onglet 1 "Depuis un journaliste" sélectionné
2. Liste déroulante (Combobox Alpine type ahead recherche) :
   ▸ Filtre : journalistes WHERE user_id IS NULL AND is_active = true
   ▸ Affichage : "MATRICULE · Prénom NOM (Fonction — Pole)"
   ▸ Icône portrait mini (24px rounded)
3. Sélection d'un journaliste (click) :
   ▸ Alpine.js $set('form.mode_a', { id, nom, prenom, email, phone, photo_url })
   ▸ Champs auto-prérenseignés EDITABLES :
     • Name = prenom + " " + nom
     • Email = email (si vide → placeholder "prenom.nom@tvplusos.tv" + suggerer)
     • Phone = phone
     • Photo = afficher miniature photo_url (profil_photo_path = photo_url si renseigné)
   ▸ Sélecteur Rôle 5 options (requis)
   ▸ Champ "Mot de passe initial" readonly : bouton 🎲 "Générer"
     (par défaut : 12 car aléatoire, affiché, bouton 📋 Copier)
4. Submit :
   DB::transaction(function() {
     a) User::create([name, email, password => Hash::make(temp), phone, profile_photo_path,
                     role => nom_role, is_active => true,
                     password_changed_at => null,
                     journaliste_id => journaliste->id,
                     created_by => auth()->id()])
     b) $user->assignRole($request->roleName);
     c) $journaliste->update(['user_id' => $user->id]);
   });
5. Redirect -> /users avec Toast SweetAlert2 :
   "✅ Utilisateur {name} créé.
   📧 Email : {email}
   🔑 Mot de passe temporaire : {temp_mdp}
   (⚠️ À communiquer à l'utilisateur — il devra le changer à sa 1ère connexion)"
```

---

## 7. Workflow Création Mode B · Manuel (DÉTAIL)

```
1. Admin/DG → onglet 2 "Manuellement"
2. Champs REQUIS :
   - Civilité (Monsieur/Madame/Mademoiselle) — NON stocké en base users, juste pour affichage name (Optionnel ? Non, demandé user : nom+prénom, on peut mettre Civilité en préfixe name ? → Non: name = Prénom NOM sans civilité, civilité pas dans table users legacy = on ignore ce champ pour Mode B, on met juste Nom / Prénom séparés dans formulaire → name = Prénom NOM concat)
   - Prénom (requis string min 2)
   - Nom (requis string min 2)
   - Email (requis unique email)
   - Téléphone (nullable)
   - Rôle (requis : 5 options)
3. Même champ mot de passe readonly généré auto (12 car + bouton copier)
4. Submit (DB transaction simple, pas d'update journaliste) :
   a) User::create([name, email, password => Hash::make(temp), phone, role, is_active => true, password_changed_at => null, journaliste_id => null, created_by => auth()->id()])
   b) $user->assignRole($request->roleName);
5. Redirect /users avec meme Toast
```

---

## 8. Workflow 1ère Connexion (Middleware)

```
1. Nouveau User se connecte via /login → mot de passe temporaire OK → Auth::attempt() OK
2. Middleware `EnsurePasswordIsChanged` s'exécute SUR TOUTES routes `auth` SAUF exceptions :
   ▸ $user = auth()->user()
   ▸ IF ($user->password_changed_at === NULL && route actuelle pas dans exceptions)
     → REDIRECT 302 /first-login-change-password
3. Page /first-login-change-password (PAS dans layouts.app — layouts.guest ? NON : auth middleware, donc layouts.app SIMPLIFIÉ sans sidebar OU sidebar avec liens désactivés + bandeau rouge "⚠️ Vous devez changer votre mot de passe avant de continuer") :
   ▸ Héro icône lock_reset (double cercle 4 points NAVY, même design Forgot NAVY AUTH)
   ▸ Champ 1 : Nouveau mot de passe (icône lock + œil toggle visibilité) — requis, min:8, mixed case, chiffres
   ▸ Champ 2 : Confirmer mot de passe — requis, same:password
   ▸ Bouton CTA NAVY "Mettre à jour le mot de passe →"
4. Submit → validator → User.update(['password' => Hash::make(new), 'password_changed_at' => now()])
5. → Toast succès + Redirect /dashboard
6. Toutes navigations suivantes OK (password_changed_at NOT NULL → middleware passe)
```

---

## 9. Liste des Livrables (Checklist Finale)

- [ ] Migration `users` 3 colonnes (password_changed_at / journaliste_id / created_by)
- [ ] DatabaseSeeder update — 2 nouveaux rôles + compte DG
- [ ] Model `User.php` : ajout HasOne journaliste(), cast password_changed_at, fillable nouvelles colonnes
- [ ] UserPolicy.php : abilities viewAny/view/create/update/delete pour Admin/DG seulement
- [ ] Middleware `EnsurePasswordIsChanged` + enregistrement dans Kernel `$middlewareAliases` + `web` middlewareGroup (après auth)
- [ ] `CreateUserRequest` (validation Mode A + Mode B rules) + `UpdateUserRequest`
- [ ] `ResetFirstPasswordController` (page + store first login changement)
- [ ] `UsersController` (resource CRUD : index/create/store/edit/update/destroy)
- [ ] Routes users.* resource + first-login-change-password GET+POST
- [ ] Vue `users/index.blade.php` (datatable liste)
- [ ] Vue `users/create.blade.php` (2 onglets Mode A/B Alpine)
- [ ] Vue `users/edit.blade.php` (édition + bouton reset mdp)
- [ ] Vue `auth/first-login-change-password.blade.php` (NAVY design AUTH)
- [ ] Sidebar : Nouvelle section « Administrer » → lien « Gestion des utilisateurs » (icône manage_accounts)
- [ ] (Bonus) LoginRequest → valider `is_active = true` en plus (refuser users désactivés)
- [ ] Tests: php artisan migrate + php artisan db:seed OK, 2 users démo créés (admin + dg), php -l tous fichiers OK, view:clear

---

## 10. Spécification User Stories / Tests d'acceptation

### 🧪 User Story 1
**Étant donné** Admin connecté · **Quand** je vais dans `/users/create` · **Et** je sélectionne un journaliste dans l'onglet A · **Et** je choisis rôle "chef_redaction" · **Et** je submit · **Alors** :
- Un user est créé avec email du journaliste
- Le `journaliste.user_id` = $user->id
- `password_changed_at = null`
- Spatie rôle `chef_redaction` assigné
- Toast affiche le mdp temporaire copiable

### 🧪 User Story 2
**Étant donné** Admin créé user DG via mode B · **Quand** user DG se connecte 1ère fois avec mdp temporaire · **Alors** il est REDIRIGÉ de force vers `/first-login-change-password` et ne peut pas aller sur `/dashboard` tant qu'il n'a pas changé son mdp. Après update → `password_changed_at = now()` et accès dashboard libre.

### 🧪 User Story 3
**Étant donné** User Journaliste avec `is_active = false` · **Quand** il tente de se connecter avec bon login/mdp · **Alors** échec connexion "Compte désactivé. Contactez votre administrateur."

### 🧪 User Story 4
**Étant donné** Journaliste avec déjà un `user_id` (compte existe) · **Quand** Admin ouvre formulaire Mode A · **Alors** Ce journaliste n'est PAS dans la liste déroulante.
