# Tasks — Module Veille Sources (Implémentation)
> Dépendance: Approbation spec.md §1-8 par l'utilisateur.
> Durée estimée: 45-60 min · Smoke tests ~5 min.

---

## LÉGENDE STATUT
⬜ Pending · 🟧 En cours · ✅ Validé (test passé) · ❌ Échec (reproductible)

---

### TASK 1 — Migration ALTER TABLE ajouter status + frequency (Spécification §3 + AC-5)
| # | Action | Fichier / Outils | AC lié |
|---|--------|------------------|--------|
| 1.1 | Créer migration: `php artisan make:migration alter_source_veille_add_status_frequency_table --table=source_veille` | CLI artisan | AC-5.1 |
| 1.2 | up(): `$table->string('frequency', 20)->default('15min')->after('url');` + `$table->enum('status', ['active','error','disabled'])->default('active')->after('is_active');` | Nouveau fichier `database/migrations/*_alter_source_veille*.php` | AC-5.1 |
| 1.3 | down(): drop columns `status`, `frequency`. | même fichier | — |
| 1.4 | Migrer: `php artisan migrate` + vérifier colonnes via `DESCRIBE source_veille;` (tinker ou CLI mysql). Controller que 14 colonnes Sprint 0 **toujours présentes**. | CLI artisan + tinker | AC-5.1, AC-5.2 |

Statut: ⬜

---

### TASK 2 — Modèle SourceVeille: fillable + casts + hook legacy sync (Spécification §3.1 + RNF9)
| # | Action | Fichier | AC lié |
|---|--------|---------|--------|
| 2.1 | `$fillable[]`: ajouter `'status'`, `'frequency'`. | `app/Models/SourceVeille.php` §L15-L28 | RF3 |
| 2.2 | `$casts[]`: ajouter `'status' => 'string'`, `'frequency' => 'string'` (pas d'enum PHP pour éviter bug de seed TypeError pour l'instant). | `app/Models/SourceVeille.php` §L30-L35 | — |
| 2.3 | Hook `protected static function booted(): void` — synchronisation bidirectionnelle `status ⇔ is_active`:<br>• si `status` changé → set `is_active = ($status === 'active')`<br>• si `is_active` changé (legacy) → set `status = is_active ? 'active' : 'disabled'`<br>(Pattern exact `Emission.php` saving legacy sync). | `app/Models/SourceVeille.php` (nouvelle section après `subjects()` remixes()) | AC-5.2 |
| 2.4 | Tinker quick test: `$s = SourceVeille::make(['name'=>'t','url'=>'https://x']); $s->status='disabled'; $s->save(); var_dump($s->is_active, $s->status);` → attendu: `bool(false), string(8) "disabled"` | `php artisan tinker` | AC-5.2 |

Statut: ⬜

---

### TASK 3 — Policy + Enregistrement Auto-Discovery (Spécification RF6 + EmissionPattern)
| # | Action | Fichier | AC lié |
|---|--------|---------|--------|
| 3.1 | Créer `app/Policies/SourceVeillePolicy.php` pattern `EmissionPolicy`:<br>• `before(User $u, $ability): ?bool` — si rôle `Super Admin/admin/chef_edition → true`<br>• `viewAny(User): bool` → rôles journalist/present/habilleur/chef_plateau/receptionniste/lecture → true<br>• `view(User, SourceVeille)` = viewAny<br>• `create/update/delete(User, SourceVeille|null): bool → false` (fallback; before override admin). | Nouveau fichier | AC-4 |
| 3.2 | Vérifier Auto-Discovery Laravel 12 (dossier Policies). Sinon `AuthServiceProvider::registerPolicies()` explicit. | `bootstrap/providers.php` ou `app/Providers/*Auth*` | — |
| 3.3 | Tinker smoke Gate: `$u=User::find(1); $u->can('create', \App\Models\SourceVeille::class)` → true (admin id=1). | tinker | AC-4 |

Statut: ⬜

---

### TASK 4 — Routes manquantes + alias FR (Spécification RF5 + AC-1)
| # | Action | Fichier | AC lié |
|---|--------|---------|--------|
| 4.1 | Editer bloc VEILLE `routes/module_autres.php` L59-L64:<br>Ajouter `POST /sources` → store · `PUT/PATCH /sources/{sourceveille}` → update · `DELETE /sources/{sourceveille}` → destroy · `GET /sources/nouvelle` → alias FR index. | `routes/module_autres.php` | AC-1 route:list |
| 4.2 | `php artisan route:list | findstr veille.sources` → 5 routes listées (sources / sources.store / sources.update / sources.destroy + alias FR). | CLI | AC-1 |
| 4.3 | Vérifier implicit binding: nom wildcard `{sourceveille}` = param controller typé `update(Request, SourceVeille $sourceveille)`. Si mismatch utiliser `{source}` partout. | Conventions (snake-case params) | AC-1 update 200 |

Statut: ⬜

---

### TASK 5 — Controller CRUD: index + store + update + destroy (RF1-RF4 + Authorization)
| # | Action | Fichier | AC lié |
|---|--------|---------|--------|
| 5.1 | Rewriter `VeilleSourceController.php` vide → 4 méthodes + `authorize()` calls:<br>• `index(?SourceVeille $editing = null)`: `authorize('viewAny', SourceVeille::class)` + `$sources = SourceVeille::orderBy('id','desc')->get()` + `$online = $sources->where('status','active')->count()` → compact `sources, online, editing`<br>• `store(Request $request)`: `authorize('create', SourceVeille::class)` + `validated name/url` + `type auto-detect` + `frequency='15min'` + `status='active'` + save → `back()->with('status', ...)`<br>• `update(Request $r, SourceVeille $sourceveille)`: `authorize('update', $sourceveille)` + valid name/url/type?/frequency?/status? → sync save → `back()->with('status')`<br>• `destroy(SourceVeille $sourceveille)`: `authorize('delete', $sourceveille)` → `$sourceveille->delete()` → `back()->with('status')`. | `app/Http/Controllers/VeilleSourceController.php` | AC-1 HTTP 200/302 |
| 5.2 | **Important**: `index()` doit accepter paramètre optionnel `?edit=id` en query string ou param route pour mode edit pré-rempli. Décision UI simple: bouton edit tableau → `href="{{ route('veille.sources') }}?edit={{ $s->id }}"` → controller récupère la source en query: `$editing = request('edit') ? SourceVeille::find(request('edit')) : null`. (évite nouvelle route). | Controller + vue | RF3 |
| 5.3 | Validation rules Store: `name => required|string|max:200`, `url => required|url|max:1000|starts_with:https://,http://` · Update règles similaires + nullable type/frequency/status. | `$request->validate([...])` inline | AC-3 erreurs affichées |

Statut: ⬜

---

### TASK 6 — Vue Blade `veille/sources/index.blade.php` 1:1 prototype (RF1, RNF1/RNF2/RNF4/RNF8 — **TÂCHE LA PLUS CRITIQUE**)
| # | Action | Fichier | AC lié |
|---|--------|---------|--------|
| 6.1 | Créer dossier `resources/views/veille/sources/` + fichier `index.blade.php`. | Nouveau | — |
| 6.2 | **Pattern L1 obligatoire** (copier emissions/index L1-L12 head):<pre>@extends('layouts.app')<br>@php<br>  $pageTitle    = 'Gestion des Sources de Veille';<br>  $pageSubtitle = 'Configurez un nouveau flux de données automatisé pour le réseau de surveillance.';<br>  $showGlobalSearch = false;<br>  $headerActions = [];<br>@endphp<br>@section('content')</pre> | L1-L13 fichier | AC-2 pattern zéro `@section(title)` / double include |
| 6.3 | Bloc HAUT: Card formulaire — structure DOM + classes **1:1 `source_vielle.html` L165-L182** (form-card / rounded-xl / p-6 / border / `md:flex-row items-end gap-4` 3 blocs: Nom · URL · Bouton Ajouter). Remplacer Material Symbols icons → FontAwesome équivalents: `add` → `fa-solid fa-plus`. Toggle mode editing ($editing existe):<br>• name/url pre-remplis via `old()` + $editing fallback<br>• Si editing: boutons update `bg-primary-container Enregistrer modifications` + `href="{{ route('veille.sources') }}" btn-ghost Annuler`<br>• Si editing: champs suppl. visibles (select Type, select Fréquence, checkbox Actif) dans même card sous les 2 champs, grille 3 colonnes.<br>• Form action POST → `$editing ? PUT(route('veille.sources.update', $editing)) : POST(route('veille.sources.store'))` (pattern `form-emission.blade.php` $method/$route). | `veille/sources/index.blade.php` section FORM | AC-2, AC-3 form visible, AC-3 edit pré-rempli |
| 6.4 | Affichage erreurs validation: `@error('name') / @error('url')` text-xs `text-error` sous input. | Même vue | AC-3 erreurs affichées |
| 6.5 | Bloc BAS: Card table — header `bg-surface-container-lowest p-4 border-b flex justify-between items-center`: h3 `Liste des sources` + pill `<span class="bg-primary-fixed text-on-primary-fixed px-2 py-1 rounded text-xs font-label-md">{{ $online }} Online</span>`. Structure DOM prototype L186-L190. | Même vue TABLE HEADER | AC-3 pill compteur |
| 6.6 | TABLEAU 6 colonnes prototype L192-L244:<br>Colonne **Type** map: `$s->type=='rss' ? badge secondary-fixed RSS : badge tertiary-fixed Web`.<br>Colonne **Statut** map: `status=='active' → fa-check-circle text-primary · 'error' → fa-circle-exclamation text-error · default → fa-circle text-slate-400 Désactivé`. **Fréquence** format string 15min→"15 min", 1h→"1 h", 2daily→"2x/jour", daily→"1x/jour". | TABLE TBODY foreach | AC-3 badges / counts corrects 3 sources seed |
| 6.7 | **@can OBJET SEULEMENT**:<br>• Formulaire haut (bouton submit ou card entière) encapsulé dans `@can('create', \App\Models\SourceVeille::class) ... @endcan`<br>• Bouton ✏️ edit → `@can('update', $s) <a href="?edit={{$s->id}}" title="Modifier"><i class="fa-solid fa-pencil text-lg hover:text-primary transition-colors"></i></a> @endcan`<br>• Bouton 🗑️ delete → `@can('delete', $s) <form method="POST" action="{{ route('veille.sources.destroy', $s) }}" class="inline ml-2">@csrf @method('DELETE') <button type="submit" onsubmit="return confirm('Supprimer?')" title="Supprimer"><i class="fa-solid fa-trash-can text-lg hover:text-error transition-colors"></i></button> </form> @endcan`<br>⚠️ IMPORTANT: `confirm()` doit être sur `<button onclick="return confirm(...)"` ou `<form onsubmit="return confirm(...)"` — test navigateur. | TABLE TD Actions | AC-4 Zéro string @can |
| 6.8 | Flash `session('status')` → déjà inclus globalement via layouts/app.blade.php L66-L72. Pas de duplication. | (vérifier pas doublon) | — |
| 6.9 | **SUPPRIMER** ancienne vue `resources/views/veille_sources.blade.php` (obsolète pattern double header) → controller index renvoie maintenant `view('veille.sources.index')`. | Controller @index L11 return | AC-2 view:clear |

Statut: ⬜

---

### TASK 7 — Seed 3 sources témoins prototype (RF7)
| # | Action | Fichier | AC lié |
|---|--------|---------|--------|
| 7.1 | Vérifier `database/seeders/` → si `SourceVeilleSeeder.php` absent: `php artisan make:seeder SourceVeilleSeeder`. | Database/Seeders | — |
| 7.2 | Insérer 3 sources (status='error' pour Le Monde):<pre>SourceVeille::create(['name'=>'BBC World News','type'=>'rss','url'=>'https://feeds.bbci.co.uk/news/world/rss.xml','frequency'=>'15min','status'=>'active']);<br>SourceVeille::create(['name'=>'The Verge Tech','type'=>'web','url'=>'https://www.theverge.com/tech','frequency'=>'1h','status'=>'active']);<br>SourceVeille::create(['name'=>'Le Monde - Pixels','type'=>'rss','url'=>'https://www.lemonde.fr/pixels/rss_full.xml','frequency'=>'5min','status'=>'error']);</pre> | Seeder run() | AC-3 3 lignes affichées · badges corrects |
| 7.3 | Enregistrer seeder dans `DatabaseSeeder::run()`: `$this->call(SourceVeilleSeeder::class);` (ou si déjà appelé → éviter doublon). | DatabaseSeeder | — |
| 7.4 | `php artisan db:seed --class=SourceVeilleSeeder` + tinker `SourceVeille::count()` ≥ 3. | CLI / Tinker | — |

Statut: ⬜

---

### TASK 8 — Smoke Tests + Snapshot (Qualité)
| # | Action | Outils | AC lié |
|---|--------|--------|--------|
| 8.1 | Smoke CLI script `_smoke_veille_source_crud.php` (pattern Émissions 03/09 smoke): bootstrap Laravel → create → update → delete. Vérifications: count variation + legacy sync is_active. Exit0 3/3 puis **supprimer le script**. | PHP CLI direct | AC-6 3/3 PASS |
| 8.2 | `php artisan view:clear` · `php artisan route:clear`. | CLI | AC-2 cache clean |
| 8.3 | MCP Browser login admin → navigate `/veille/sources` → snapshot DOM → attendre ~200 refs + formulaire + tableau 3 lignes. Vérifier: pill "2 Online" (si Le Monde error=non active) OU "3 Online" (spécification AC-3 §'3 actifs par défaut → à clarifier: status=error n'est PAS actif, donc pill 2 Online). | Integrated Browser + snapshot | AC-1 HTTP 200 · AC-3 visuel |
| 8.4 | Smoke UI: submit form vide → erreurs affichées. Submit source valide "Smoke UI" → flash success + ligne ajoutée. Edit "Smoke UI" → changer fréquence 1h → save → refresh valeur OK. Delete → confirm → ligne disparaît. | MCP click/type | AC-3 CRUD 3/3 |
| 8.5 | Grep anti-string @can (AC-4):<pre>rg -n "@can\(['\"]\w+\s+\w+" resources/views/veille/sources/index.blade.php app/Http/Controllers/VeilleSourceController.php</pre> | CLI ripgrep | AC-4 0 match |
| 8.6 | GetDiagnostics VSCode + `php artisan lint` si disponible. Aucune erreur PHP. | IDE Diagnostics · `php -l` sur fichiers modifiés | — |
| 8.7 | [Facultatif] Screenshot final `veille_sources_crud_ok.png` comme preuve visuelle user. | MCP screenshot | — |

Statut: ⬜

---

## Ordre d'exécution recommandé (dépendances)
```
T1 (Migration) → T2 (Modèle Hook) → T3 (Policy) → T4 (Routes)
                                                   ↓
                         T5 (Controller) ←———————┘
                              ↓
                    T6 (Vue 1:1 prototype + suppr vieille vue)
                              ↓
                         T7 (Seed)
                              ↓
                    T8 (Smoke CLI + MCP UI + Diagnostic + Grep)
```

## Checklist de fin d'implémentation (avant notification user)
- [ ] Toutes les tasks ✅ (aucun ❌ non documenté)
- [ ] Aucun script `_smoke_*.php` / `_tmp*.php` laissé dans la racine (nettoyé)
- [ ] `php artisan view:clear + route:clear` effectués
- [ ] Snapshot MCP Browser final stocké comme preuve
- [ ] Grep anti @can string → 0 match
- [ ] Doublon MID'INFOS module Émissions → persisté (hors scope, à clarifier user séparément)
