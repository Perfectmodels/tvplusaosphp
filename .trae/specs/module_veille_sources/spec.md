# Spec — Module Veille (Partie 1) : Gestion des Sources de Veille
> Version 1.0 — 03 sept. 2026
> Scope: **CRUD UI Sources de Veille 1:1 prototype `App/source_vielle.html`** (hors scraping périodique / remix IA / export conducteur → phases suivantes).

---

## 1. Problème & Contexte

La page `/veille/sources` (Module Veille) existe Sprint 0 en tant que **placeholder incomplet** (vue `veille_sources.blade.php` pattern obsolète `@section title/page-actions` → risque double header; routes seulement `GET /sources`; pas de CRUD; pas de Policy; migration incomplète).

L'utilisateur demande:
> *« on crée des sources dans lesquels nous allons scrapper (voir le fichier html source_vielle): un formulaire avec deux champs (Nom de la source + URL du flux) puis en bas la liste des sources avec leur type, leur fréquence, leur statut et des actions Modifier / Supprimer. »*

La demande se limite à la **gestion administrative des sources** (interface CRUD). Scraping, remix IA, promotion vers conducteur → SPEC suivante.

### 1.1 Sources de vérité (non négociables, **1:1 reproduction obligatoire**)
| Source | Contenu |
|--------|---------|
| `App/source_vielle.html` L162-L247 | Structure UI exacte: formulaire flex-row 2 champs + pill header-table compteur + tableau 6 colonnes + badges Type/Statut + icônes Actions. |
| `SPEC_Module_Veille_IA.md` §3.1 « Table sources » | Schéma colonnes: name / type(enum) / url / frequency(enum) / category_id / last_scraped_at / status(enum: active,error,disabled) / timestamps. |
| `resources/views/emissions/index.blade.php` L1-L12 | **Pattern Blade FIGÉ**: `@extends` L1 + vars PHP `$pageTitle / $pageSubtitle / $showGlobalSearch / $headerActions[]` **avant** `@section('content')`. |
| `app/Policies/EmissionPolicy.php` | Pattern Policy: `before()` admin→true + `viewAny/view/create/update/delete`. |
| Rappel bug confirmé module Émissions 03/09 | **Syntaxe `@can` STRING interdite** (ex: `@can('create source')` → boutons invisible). **Syntaxe OBJET OBLIGATOIRE**: `@can('create', \App\Models\SourceVeille::class)` / `@can('update', $s)` / `@can('delete', $s)`. |

---

## 2. Acteurs & Permissions

| Rôle | Permissions (héritées Policy `before`) |
|------|----------------------------------------|
| **Super Admin / admin / chef_edition** | CRUD complet: voir / créer / modifier / supprimer / activer-désactiver. |
| **Journaliste / présentateur / habilleur / chef_plateau / lecture / receptionniste** | ViewAny + View (liste lecture seule) — create/update/delete → false par défaut (sauf override `before` si rôle élevé). |

Cohérence 100% avec `EmissionPolicy.php` existant.

---

## 3. Gaps Migration existante → Corriger par ALTER TABLE

La migration `2026_01_01_000013_create_source_veille.php` Sprint 0 contient 14 colonnes. **2 écarts majeurs vs SPEC §3.1**:

| Colonne SPEC §3.1 | Migration Sprint 0 | Solution |
|-------------------|--------------------|----------|
| `status ENUM('active','error','disabled')` | ❌ Absent · Remplacé par `is_active BOOLEAN` | ✅ **Ajouter colonne `status`** via nouvelle ALTER migration. Legacy sync hook: `status=='active' ⇔ is_active=true`. |
| `frequency ENUM('5min','15min','30min','1h','2daily','daily')` | ❌ Absent | ✅ **Ajouter colonne `frequency`** string(20) (ou ENUM) default `'15min'`. |
| `type ENUM('single_url','rss','listing')` SPEC | ✅ Existe `type VARCHAR(50) default 'rss'` | ✅ Garder string + map UI (RSS / Web). Prototype: Type = `RSS` (url finissant par `.xml` ou `/rss`) ou `Web`. |

**Colonnes Sprint 0 UTILES pour phases suivantes → CONSERVEES (aucune perte de données)**:
`rss_feed, country, language, category (string pas FK), priority (INT), scraper_class, config (JSON)`. Pas affichées dans UI Sources actuelle, réservées scraping / SPEC phase 2.

### 3.1 Modèle SourceVeille — Compléter casts
- Ajouter `frequency` string.
- Ajouter `status` string (ou cast enum si PHP backed enum créé → optionnel, string safe pour commencer).
- Hook `booted saving()` bidirectionnel sync legacy `is_active ⇔ status` (exact pattern Emission legacy sync).

---

## 4. Besoin Fonctionnel (RF — Exigences)

### RF1 — Page unique index conforme prototype (1:1)
Route `GET /veille/sources` (`name: veille.sources`). Une **SEULE vue** (comme prototype):
- Bloc HAUT: Card formulaire d'ajout (`h2` "Formulaire d'ajout") 2 colonnes `md:flex-row`:
  - Colonne Nom (type text placeholder `ex: Reuters Technology`)
  - Colonne URL (type url placeholder `https://...`)
  - Bouton primaire `Ajouter la source` + icône `+` (FontAwesome `fa-plus` ou `fa-solid fa-plus`).
- Bloc BAS: Card "Liste des sources" avec header contenant:
  - Titre h3 `Liste des sources`
  - **Pill compteur**: `bg-primary-fixed text-on-primary-fixed px-2 py-1 rounded text-xs` format `"{count} Online"` (count = nb sources where status=active).
- Tableau 6 colonnes (cf prototype L192-L244):
  1. **Nom** (font-medium bold)
  2. **URL** (`max-w-[200px] truncate`, lien externe `target="_blank"` optionnel)
  3. **Type**: Badge `RSS` → `bg-secondary-fixed text-on-secondary-fixed` · Badge `Web` → `bg-tertiary-fixed text-on-tertiary-fixed`
  4. **Fréquence**: `5 min / 15 min / 30 min / 1 h / 2x/jour / 1x/jour`
  5. **Statut**: Flex gap-1 icon + label —
     - Actif: icon `fa-check-circle text-primary` · label `Actif`
     - Erreur: icon `fa-circle-exclamation text-error` · label `Erreur`
     - Désactivé: icon `fa-circle text-slate-400` · label `Désactivé`
  6. **Actions** (align right, 2 icônes):
     - ✏️ Edit → pré-remplit formulaire HAUT en mode Update (toggle)
     - 🗑️ Delete → form POST `@method DELETE` + `onsubmit confirm("Supprimer cette source ? Action irréversible.")`

### RF2 — Mode CREATE (POST)
- Clic bouton `Ajouter la source` → POST `route('veille.sources.store')`.
- Validation: `name: required|string|max:200` · `url: required|url|max:1000`.
- **Auto-détection Type**: si URL contient `.xml` ou `/rss` → `type = 'rss'` sinon `'web'` (conforme prototype).
- Défauts à la création: `status='active'`, `frequency='15min'`, `is_active=true`.
- Flash status: `->with('status', "Source « $name » ajoutée.")` → affiché via `_partials/flash-status.blade.php` / layouts.

### RF3 — Mode UPDATE (clic icône ✏️ dans table)
- Clic edit → même vue index, formulaire haut **pré-rempli** (name + url) + bouton devient `Enregistrer les modifications` + bouton ghost secondaire `Annuler` (reset form).
- Champs edit supplémentaires (affichés seulement en mode update, conformes SPEC §3.1):
  - Select Type: RSS / Web (override auto-détection)
  - Select Fréquence: 5 min / 15 min / 30 min / 1 h / 2x/jour / 1x/jour
  - Switch / Checkbox Statut: Actif / Désactivé
- Submit → `PUT /veille/sources/{sourceveille}` implicit binding.
- Flash status: `Source modifiée`.

### RF4 — Mode DELETE
- Icône 🗑️ → form POST @method DELETE → controller @destroy.
- Soft delete? **NON** → `forceDelete` simple (sources ne sont pas FK fortes vers sujets: `source_id nullable` OK cascade null).
- Flash status: `Source supprimée`.

### RF5 — Route manquantes + alias FR
Compléter bloc VEILLE dans `routes/module_autres.php` (pattern Émissions):
```php
Route::prefix('veille')->name('veille.')->group(function () {
    // ... existants GET /sujets, GET /{id?}/detail
    Route::get('/sources', [VeilleSourceController::class, 'index'])->name('sources');
    Route::get('/sources/nouvelle', [VeilleSourceController::class, 'index']); // alias FR
    Route::post('/sources', [VeilleSourceController::class, 'store'])->name('sources.store');
    Route::match(['put','patch'], '/sources/{sourceveille}', [VeilleSourceController::class, 'update'])->name('sources.update');
    Route::delete('/sources/{sourceveille}', [VeilleSourceController::class, 'destroy'])->name('sources.destroy');
});
```
⚠️ wildcard `{sourceveille}` → doit correspondre au paramètre typé du controller: `update(Request $r, SourceVeille $sourceveille)`.

### RF6 — Policy `SourceVeillePolicy.php` + enregistrement auto-discovery Laravel 12
Copier pattern EmissionPolicy → `viewAny` journaliste OK, `before()` admin/chef_edition true, else fallback false.

### RF7 — Seed Sources témoins 3 exemples prototype
Seed `SourceVeilleSeeder` (ou DatabaseSeeder) → insérer 3 sources **identiques au prototype**:
1. BBC World News · `https://feeds.bbci.co.uk/news/world/rss.xml` · type=rss · frequency=15min · status=active
2. The Verge Tech · `https://www.theverge.com/tech` · type=web · frequency=1h (60min) · status=active
3. Le Monde - Pixels · `https://www.lemonde.fr/pixels/rss_full.xml` · type=rss · frequency=5min · status=**error** (pour afficher badge erreur)

Seed injecté dans `DatabaseSeeder::run()` si absent.

---

## 5. Besoin Non Fonctionnel (RNF)

| ID | Exigence | Justification |
|----|----------|---------------|
| RNF1 | **AUCUNE invention UI**: classes Tailwind + structure DOM = copy/paste prototype `source_vielle.html` L165-L247. Mêmes couleurs Material3 tokens (primary-container, secondary-fixed, tertiary-fixed, surface, form-card shadow). | Contrainte principale user: *« on n'invente rien on reproduit simplement nos pages html existantes »*. |
| RNF2 | Pattern Blade UNIQUEMENT vars PHP avant `@section`. **INTERDIT** `@section('title') / @section('page-title') / @section('page-actions')` / `@include('_partials.page-header')` (risque double header, bug confirmé module Émissions 29/08). | Règle layout figée projet. |
| RNF3 | **Zéro lien mort `#`** dans la page (sidebar déjà HTTP 200). Les boutons du headerActions si ajoutés → route réelle ou # si réservée à phase ultérieure avec commentaire TODO. | Constraint globale sidebar. |
| RNF4 | `$showGlobalSearch = false` dans cette page (recherche globale header masquée car pas dans prototype; prototype source_vielle n'a PAS de barre search). | Conforme header catalogue émissions 29/08. |
| RNF5 | **@can syntaxe OBJET SEULEMENT** 100% occurrences: form visible, boutons edit/delete, headerActions. Interdit string. | Bug confirmé boutons invisibles; lesson learned. |
| RNF6 | Delete protection `onsubmit confirm()` inline sur form HTML, pas JS séparé. | Pattern actuel modules Émissions. |
| RNF7 | HTTP 200 sur `/veille/sources` après implémentation. Routes GET/POST/PUT/DELETE → `php artisan route:list` + smoke CLI 3/3 (create/update/delete). | Standard qualité. |
| RNF8 | Icônes UI: **FontAwesome 6** (contenu vues Laravel: `fa-pencil`, `fa-trash-can`, `fa-check-circle`, `fa-circle-exclamation`). Prototype utilise Material Symbols → alignement sur FontAwesome pour cohérence (les 2 CDNs inclus, mais FontAwesome dominant projet). | Cohérence inter-modules. |
| RNF9 | Legacy sync `is_active ⇔ status` via hook `SourceVeille::booted saving()` → aucune donnée orpheline, compatibilité descendante si anciens appels `is_active`. | Cohérence BDD. |

---

## 6. Architecture (map fichiers impactés)

| # | Chemin | Nature | Changement |
|---|--------|--------|------------|
| 1 | `database/migrations/2026_09_XX_0000XX_alter_source_veille_add_status_frequency.php` | Nouveau | ALTER TABLE ajoute `status ENUM(..) default 'active'`, `frequency VARCHAR(20) default '15min'`. |
| 2 | `app/Models/SourceVeille.php` | Modifier | Compléter `$fillable[]` + `$casts[]` + hook `booted` saving legacy sync. |
| 3 | `app/Policies/VeilleSourcePolicy.php` (ou `SourceVeillePolicy.php`) | Nouveau | Pattern EmissionPolicy: `before()` + CRUD perms. |
| 4 | `app/Http/Controllers/VeilleSourceController.php` | Modifier | `index()` avec compact sources + editingSource optionnel · `store(Request)` · `update(Request, SourceVeille)` · `destroy(SourceVeille)` + tous `authorize()`. |
| 5 | `routes/module_autres.php` L59-L64 | Modifier | Ajouter POST store / PUT update / DELETE destroy + alias FR. |
| 6 | `resources/views/veille/sources/index.blade.php` | Nouveau | Remplacé vieille `veille_sources.blade.php` → 1:1 prototype. |
| 7 | `resources/views/veille_sources.blade.php` | Supprimer | Obsolète, double header pattern + placeholder. Controller renomme vers `veille.sources.index`. |
| 8 | `database/seeders/SourceVeilleSeeder.php` (ou DatabaseSeeder run()) | Nouveau / Modifier | 3 sources témoins prototype BBC/Verge/Monde. |

---

## 7. Critères d'Acceptation (Règle + Rubrique)

### Règle d'AC générale
La livraison est acceptée SI **TOUTES** les assertions suivantes passent (aucune tolérance sur RNF1/RNF2/RNF5):

#### Bloc AC-1 — Routes + HTTP
- [ ] `GET /veille/sources` HTTP 200.
- [ ] `POST /veille/sources` HTTP 302 (valid + invalide 2 cas).
- [ ] `PUT /veille/sources/{id}` HTTP 302 (id existe).
- [ ] `DELETE /veille/sources/{id}` HTTP 302.
- [ ] `php artisan route:list` → 4 routes `veille.sources*` listées.

#### Bloc AC-2 — Pattern Blade (RNF2 / RNF4)
- [ ] L1 du nouveau `veille/sources/index.blade.php` = `@extends('layouts.app')` zéro octet avant.
- [ ] **AUCUNE** directive `@section('title') / @section('page-title') / @section('page-actions')` dans le fichier.
- [ ] **AUCUN** `@include('_partials.page-header')` dans le fichier.
- [ ] Variables PHP présentes avant `@section`:
  ```php
  @php $pageTitle = 'Gestion des Sources de Veille';
       $pageSubtitle = 'Configurez un nouveau flux de données automatisé pour le réseau de surveillance.';
       $showGlobalSearch = false;
       $headerActions = [];
  @endphp
  ```
- [ ] `view:clear` + refresh MCP → header UNIQUE (pas de superposition).

#### Bloc AC-3 — CRUD fonctionnel (RF1-RF4)
- [ ] Formulaire visible (Nom + URL + bouton Ajouter).
- [ ] Validation erreurs affichées (nom vide / URL invalide).
- [ ] 3 sources seed affichées dans tableau (BBC / Verge / Le Monde).
- [ ] Ligne Le Monde affiche badge Statut **Erreur** (text-error icon).
- [ ] Lignes BBC + Verge affichent **Actif** (text-primary icon).
- [ ] Type BBC → badge RSS (secondary-fixed). Type Verge → badge Web (tertiary-fixed).
- [ ] Pill header → `3 Online` (si 3 actifs par défaut).
- [ ] Clic edit → formulaire pré-rempli + boutons update visible.
- [ ] Update save → DB colonnes `name / type / url / frequency / status / is_active` correctement set.
- [ ] Delete confirm → `count(*)` DB diminue de 1.

#### Bloc AC-4 — @can (RNF5 — ZÉRO tolérance string)
Grep `resources/views/veille/sources/index.blade.php` regex:
```bash
rg -n "@can\(['\"]\w+\s+\w+" resources/views/veille/sources/index.blade.php
```
- [ ] **0 match** (aucune syntaxe string). Toutes les occurrences:
  ```php
  @can('create', \App\Models\SourceVeille::class)  {{-- form visible --}}
  @can('update', $source)  {{-- bouton edit --}}
  @can('delete', $source)  {{-- bouton delete --}}
  ```

#### Bloc AC-5 — Migration legacy sync (§3.1)
- [ ] `php artisan migrate` exit0, pas de drop colonnes existantes (14 colonnes Sprint 0 préservées).
- [ ] Hook saving: si `status = 'active'` alors `is_active = true` (réciproque aussi).

#### Bloc AC-6 — Smoke CLI (indépendant HTTP sessions)
Script smoke `_smoke_veille_source_crud.php` (puis auto-supprimé):
1. Create source: `$s = SourceVeille::create(['name'=>'SmokeRSS','type'=>'rss','url'=>'https://example.com/feed.xml'])` → count N+1.
2. Update: `$s->update(['name'=>'SmokeRSS-MAJ','frequency'=>'5min','status'=>'disabled'])` → `is_active === false` (legacy sync).
3. Delete: `$s->delete()` → count retour à N.
- [ ] Smoke 3/3 PASS.

---

## 8. Hors Scope (pour éviter le scope creep)
❌ **NON implémentés cette implémentation** (suivants SPEC phases 2→5):
- Scraping réel HTTP GET / parsing DOM / extraction articles.
- Job `ScrapeVeilleSourcesJob` implémentation (Schedule déjà déclaré, code Job restera stub jusqu'à phase 2).
- Page de détail Sujet (`/veille/{id}/detail` déjà route mais stub).
- Remix IA + panneau assistant LLM (`VeilleAIService.php` déjà existant, inutilisé ici).
- Promotion vers conducteur / séquences.
- API REST `/api/veille/sources` (inutile pour UI Blade Server-Side actuel).
- Dashboard KPIs Sources / sujets importés count.
- Switch Actif/Désactivé inline (checkbox formulaire edit SEULEMENT; pas de toggle row).
