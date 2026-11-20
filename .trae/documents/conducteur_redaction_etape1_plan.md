# Plan Implémentation — Étape 1 : Conducteur de Rédaction (CR) + Workflow 5 étapes

> Périmètre EXACT donné user le 05/09/2026 : CR 14 colonnes (Ordre, Début, Fin, Durée auto, Type liste, Titre, Intervenant liste, Fonction) + 5 colonnes Workflow interactif (Terrain / Montage / Habillage / Livraison / Diffusion). Bouton intelligent par cellule : clic Étape N = Terminé vert → active automatiquement Étape N+1 bleu. Badges Gris (attente) / Bleu (en cours) / Vert (terminé).

---

## 1. Research existant (conclu Analyse Module)

### Base actuelle
| Couche | Existant | État |
|---|---|---|
| **Models** | `app/Models/Conducteur.php` (L1-L51), `app/Models/Sequence.php` (L1-L68), `SequenceIntervenantPivot.php` | ❌ Fillable/casts legacy (10-15 champs). 0 Enum. 0 Accesseurs calcul auto heures. |
| **Migrations** | `000017_create_conducteurs` · `000018_create_sequences` · `000019_create_sequence_intervenants` · `000032_pivot_logistique` | ✅ Pivot logistique QR intervenant OK. ❌ Conducteurs sequences 20 cols manquantes. |
| **Controllers** | `ConducteurRedactionController.php` (7 lignes stub) | ❌ Rien implémenté. |
| **Routes** | `module_autres.php:L50-54` → 2 GET stubs. `api.php:L136-L143` → 1 POST sync stub | ❌ 0 CRUD. |
| **Vues** | `conducteur_redaction.blade.php` (10 lignes placeholder) | ❌ Prototype HTML source unique = `App/conducteur_redaction.html` (400+ lignes tableau 15 cols) |
| **Policies** | 6 policies existent (`VeilleRemixPolicy`…) | ❌ Manquent `ConducteurPolicy.php`, `SequencePolicy.php` |
| **JS stack** | Vite `app-CS7q2MEk.js` + Alpine 3.16 + TipTap 2.27 (Remix IA) | ✅ Bundle prêt. Juste ajouter composant Alpine `conducteurRedaction()`. |

### Interfaces utilisées (0 création module annexe)
- `emissions` table existe : FK `conducteur.emission_id`
- `intervenants` table + pivot logistique : liste déroulante multi intervenants + autofill `nom_prenom / fonction`
- `journalistes` table : FK `sequence.journaliste_responsable_id`

### Workflow 5 étapes — Décisions SPEC
```
Ordre workflow (1 seul sens imposé) :
  Terrain → Montage → Habillage → Livraison → Diffusion

Règles clic user:
  Étape N:
    • Si statut Attente (gris) ET Étape N-1 = Terminé OU N=1 → clic = passe Terminé vert.
    • Si statut En cours (bleu) → clic = passe Terminé vert.
    • Click → déclenche auto : Étape N+1 → En cours bleu (UNIQUEMENT si étape N+1 est actuellement gris).
    • Étape Diffusion terminée → case verte "Diffusion OK".
    • Rétro-click autorisé ? : OUI (Chef d'édition seulement, bouton clic droit / reset workflow). MVP simple = autorise reset tout le monde, on affinera permissions phase 2.

Couleurs Badges (SPEC user VERBATIM):
  🟦 BLEU   = Étape EN COURS (action possible, bouton cliquable)
  🟩 VERT   = Étape TERMINÉE (coche checkmark, non cliquable sauf reset)
  ⬜ GRIS   = Étape EN ATTENTE (désactivé, non cliquable, grisé)

Calcul Diffusion statut colonne 14 SPEC §3.2:
  MVP user explicitement WORKFLOW CLIC → MVP on laisse click user. Accessor auto sera ajouté phase suivante si demandé.
```

---

## 2. Fichiers concernés

### 🗄️ Backend Laravel (PHP)
| Fichier | Modification |
|---|---|
| **NOUVEAU** `app/Enums/TypeSequenceEnum.php` | 14 types user+SPEC : Generique, Intro, Sujet, Invite_plateau, Reportage, Debat, Chronique, Meteo, Agenda, Duplex, Alerte_info, Annonce, Publicite, Conclusion. |
| **NOUVEAU** `app/Enums/StatutConducteurEnum.php` | Brouillon, En_construction, A_valider, Valide, Transfere, En_direct, Cloture, Archive. |
| **NOUVEAU** `app/Enums/StatutEtapeWorkflowEnum.php` | En_attente, En_cours, Termine (3 valeurs badges couleur). |
| **NOUVEAU** `database/migrations/<date>_upgrade_conducteurs_step1.php` | Conducteur: +`parent_id`, `date_edition`, `presenter_on_air_id`, `owner_id`, `active_version`, `validated_by/at`, `statut` (enum). +FK cascade. |
| **NOUVEAU** `database/migrations/<date>_upgrade_sequences_step1.php` | Sequences: +`actual_duration_sec`, `heure_debut_calculee TIME`, `heure_fin_calculee TIME`, `nom_prenom_text`, `fonction_text`, `note_regie TEXT`, `angle_editorial TEXT`, `journaliste_responsable_id FK`, `statut_terrain`, `statut_montage`, `statut_habillage`, `statut_livraison`, `heure_livraison TIME`, `statut_diffusion`, `alerte_info BOOL`, `masque BOOL`, `user_last_modif_id FK`. |
| `app/Models/Conducteur.php` | Fillable/casts enum. `parent()`, `enfants()`, `owner()`, `livePresenter()`. Scope `byEmissionAndDate()`. Accessor `target_duree_minutes`. |
| `app/Models/Sequence.php` | ⭐ **Critique** : Nouveaux fillable/casts 5 étapes workflow (StatutEtapeWorkflowEnum). Relations `intervenants()`, `journalisteResponsable()`, `notesPresentateur()`. ⭐ **Mutator `position` updated** → recalcul auto `heure_debut_calculee` + `heure_fin_calculee`. Accessor `duree_minutes_sec` en `MM:SS`. |
| **NOUVEAU** `app/Policies/ConducteurPolicy.php` | Chef/Lector CRUD. Journaliste lecture. |
| **NOUVEAU** `app/Policies/SequencePolicy.php` | Chef toutes colonnes. Journaliste : `update` seulement ses sequences (journaliste_responsable_id). MVP simple. |
| `app/Http/Controllers/ConducteurRedactionController.php` | `index($conducteurId=?)` → charger conducteur par défaut le plus récent + sequences tri position. `create(Emission,dateEdition)`, `show`, `updateEnTete`. PATCH `reorderSequences()` (bulk positions JSON). |
| **NOUVEAU** `app/Http/Controllers/SequenceController.php` | Resource : `store(conducteur_id,…)` → auto init workflow : `Terrain=En_cours, les4Autres=En_attente` ⭐. `update(seq,… champs classiques)`. ⭐ **PATCH `sequences/{id}/workflow-next?etape=terrain`** : clic user → passer etape Terminé vert → étape suivante si existante = En cours bleu. Retour JSON {newStatusEtapeN, newStatusEtapeN+1, diffusionPret}. `destroy(seq)` → recalcul positions suivantes. |
| `routes/module_autres.php` | Route resource `conducteurs.sequences` (7). + Route PATCH `conducteurs/sequences/{id}/workflow-next` name=`sequences.workflow_next`. + Route PATCH `conducteurs/{id}/reorder` name=`conducteurs.reorder`. |

### 🖼️ Frontend (Blade + Alpine + Vite JS)
| Fichier | Modification |
|---|---|
| `resources/views/conducteur_redaction.blade.php` | ⭐ Intégration HTML [conducteur_redaction.html](file:///c:/Users/DELL/Desktop/tvplusos/App/conducteur_redaction.html) entier : Header stats, Footer durée totale. Tableau 14 cols user VERBATIM. Composant Alpine `x-data="conducteurRedaction()"`. Chaque ligne = `x-for` sur sequences. Boutons workflow par cellule = classe dynamique `bg-blue-100/800` (en cours) / `bg-green-100/800 checkmark` (terminé) / `bg-gray-100/500 disabled` (attente). Click → appel PATCH `workflow-next`, refresh Ligne seulement. |
| `resources/js/conducteur-redaction.js` | ⭐ Composant `Alpine.data('conducteurRedaction')`. Propriétés: conducteur obj, sequences=[{…}], dureeTotaleSec. Méthodes: `init(conducteurJson, sequencesJson)`. ⭐ `clicWorkflow(sequenceId, etape)` → fetch PATCH, met à jour seulement sequences[i].statut_<etape_suivante>. `recalculerHeuresTotales()` (quand position change): loop cumul duree→debut/fin auto. ⭐ Event `end DragDrop reorder` → envoi PATCH reorder, `recalculerHeuresTotales()`. |
| `resources/js/app.js` | Ajout `import './conducteur-redaction'`. |
| `vite.config.js` (si besoin) | Rien. Entry unique app.js déjà. |

---

## 3. Étapes d'implémentation (ordre strict dépendances)

### Phase A — BDD + Modèles Backend (0 dépendances frontend)
1. **Créer 3 classes Enum** (`TypeSequenceEnum`, `StatutConducteurEnum`, `StatutEtapeWorkflowEnum`) → Backed string, méthode `label()` français + `badgeClass()` couleur CSS.
2. **Migration upgrade_conducteurs_step1** → Ajouter 8 colonnes. Vérifier Schema::hasColumn safe (dev only).
3. **Migration upgrade_sequences_step1** → Ajouter 17 colonnes + 2 FK. Important : `statut_*` default value = `StatutEtapeWorkflowEnum::En_attente->value`.
4. **Mettre à jour `Conducteur.php`** Model: fillable casts enum + 5 relations.
5. **Mettre à jour `Sequence.php`** Model: ⭐ casts 5 colonnes workflow StatutEtapeWorkflowEnum enum. ⭐ **Event updating static**: quand `position` ou `duration_seconds` change → marquer un dirty flag. ⭐ **saved static** : si dirty flag → Appel d'un Service `ConducteurCalculatorService::recalculerHeures(conducteur_id)` (toutes les séquences recomputent heure_debut_calculee + heure_fin_calculee + duree totale conducteur).
6. ⭐ NOUVEAU **`app/Services/ConducteurCalculatorService.php`** : fonction pure stateless. Entrée tableau sequences triées par position + heure_debut_emission (HH:MM:SS). Retour tableau séquences maj (heure_debut_calculee, heure_fin_calculee). ⭐ Critique pour workflow étape 4.
7. **Créer 2 Policies** (Conducteur + Sequence) → MVP minimal Chef peut tout, Journaliste peut modifier ses sequences assignées, autres rôles read.

### Phase B — Routes + Controllers + JSON endpoints
8. **`ConducteurRedactionController` plein** — Méthodes index/show/create/reorder. `index()` : Si no `$id` → sélectionner `Conducteur::latest('date_edition')->firstOrCreate` pour émission par défaut (ex: MID'INFOS id=1). Retour view + `$conducteur` + `$sequences` + `$emissionsSelect` + `$intervenantsSelect` (tous actifs).
9. **Nouveau `SequenceController` resource 7 méthodes** — `store()` : ⭐ init workflow `['statut_terrain'=>En_cours, 4 autres=>En_attente]`, auto append `ordre=max(position)+1`. Appel `recalculerHeures`. `destroy()` : puis recalcul positions `gap` (toutes suivantes position -1). ⭐ **`PATCH workflow_next(Request $r, Sequence $seq)`** — validation `etape in [terrain, montage, habillage, livraison, diffusion]`. Règles métier exactes :
   ```
   array_order = ['terrain','montage','habillage','livraison','diffusion']
   idx = position de etape dans array_order
   Si etape=terrain OR sequences[idx-1]_statut == Terminé OU sequences[idx]_statut = En_cours:
       sequences[idx]_statut = Terminé
       SI idx+1 < 5 ET sequences[idx+1]_statut = En_attente:
           sequences[idx+1]_statut = En_cours   ← AUTO ACTIVE NEXT BLEU
   save
   return JSON ['seq_data_maj']
   ```
10. **Mettre à jour routes module_autres.php** — Toutes resource + endpoints workflow_next et reorder. `middleware(['auth'])` déjà appliqué groupe.

### Phase C — Frontend Blade + Alpine Component
11. **Blade `conducteur_redaction.blade.php`** — Intégrer HTML prototype [conducteur_redaction.html](file:///c:/Users/DELL/Desktop/tvplusos/App/conducteur_redaction.html). Header stats bar (nom emission / heure / durée cible / progression). Boucle Blade `@foreach($sequences as $s)` → 14 colonnes exactement: Ordre·Début·Fin·Durée·Type·Titre ou Sujet·Intervenant·Fonction·🟦Terrain·🟩Montage·Habillage·Livraison·Diffusion·Actions. ⭐ Colonne Type : badge `{!! s->type_enum->badgeHtml() !!}`. ⭐ Colonnes workflow 5 : bouton `<button @click="clicWorkflow({{$s->id}}, 'terrain')" :class="classBtnWorkflow({{$s->id}}, 'terrain')">` → avec icône checkmark ⭕ si terminé.
12. **Nouveau `conducteur-redaction.js` Alpine component** — `clicWorkflow(id,etape)` : `fetch('/conducteurs/sequences/'+id+'/workflow-next', {method:'PATCH', body:{etape}}) → json → update local state sequences[id][statut_+etape] + éventuellement next`. **Rien reload page** (SPA-feel).
13. **Calcul frontend local `durée` colonne** (pour feedback user immédiat): `fin - debut` affiché MM:SS. Submit modif durée → déclenche PATCH + `recalculerHeuresTotales`.
14. Ajout composant **SortableJS DnD** réordonner position lignes → `@end` drag : `PATCH conducteurs/{id}/reorder {newOrderIds: [1,2,3,4]}` → controller met à jour position → retourne json sequences mises à jour (heures). Update DOM local sans reload.
15. `app.js import` + `npm run build` bundle.

---

## 4. Dépendances et Considérations

| Point | Choix |
|---|---|
| **SortableJS** | Ajouter package NPM `sortablejs@^1.15` (déjà souvent utilisé pour DnD simple Alpine) |
| **Backed Enums PHP 8.3** | Préféré aux string enums Laravel : validation auto typehint. Méthodes `badgeHtml()` retourne `<span class="badge badge-blue">Nom</span>` => 0 CSS duplication. |
| **Service Recalcul** | **Obli** car 3 couches le déclenchent (Sequence saved, Reorder bulk, Suppression). Centralisé => 0 bug déphasage heures. |
| **Workflow reset** | MVP pas besoin (utilisateur click 5 fois dans ordre OK). Reset sera ajouté si besoin Phase 2 bouton "Réinitialiser étapes séquence". |
| **Permissions MVP** | On respecte User roles déjà présents (Chef d'édition, Journaliste). Affichage/masquage UI colonne via @can. Les endpoints PATCH controller → on valide Gate Policy. |
| **Intervenants multi** | MVP étape 1 → simplification : 1 seul Intervenant (colonne 1 seul select). Multi sera Phase suivante si besoin. MVP réduit scope. |
| **⚠️ IMPORTANT SÉCURITÉ BULLET** : user a fourni workflow simplifié 5 étapes CLIC. SPEC §3.2 dit "Diffusion Calculé auto". MVP étape 1 on SUIT user explicitement (workflow clic). Accessor calcul automatique sera ajouté Phase suivante si user confirme. On ne mélange pas 2 logiques antagonistes ici. |

---

## 5. Validation après implémentation

Tests **obligatoires** pour cloturer Étape 1 :

- ✅ `php artisan migrate` exit 0 → 3 nouvelles tables ajoutées / 2 upgradées sans erreur.
- ✅ `php artisan route:list | grep conducteur` → 10 routes au moins listées.
- ✅ `php -l` sur tous nouveaux fichiers PHP (0 erreur syntaxe).
- ✅ `npm run build` → exit 0 bundle JS.
- ✅ **Smoke test Navigateur Auth Admin** → GET `/conducteurs/redaction`:
  1. Page charge. 14 colonnes entêtes visibles (Ordre à Actions).
  2. Clic bouton ➕ Ajouter séquence → Créer 5 séquences test : Générique, Titres, Reportage, Duplex, Conclusion.
  3. ⭐ **Workflow Click Test Critique** : Sur Reportage seq #3
     - Colonne Terrain = BLEU (en_cours auto init) → clic → passe VERT ✅. **Observation immédiate** : Colonne Montage devient BLEU automatiquement.
     - Colonne Montage → clic → VERT ✅ → Auto Habillage BLEU.
     - Itérer jusqu'à Diffusion clic → VERT → 5 badges verts alignés.
  4. ⭐ **Heures auto recalcul test** : Modifier position 2 → 5 (drag and drop en fin). Observer colonnes Début/Fin recalculs 0 erreur. Durée totale footer incrément.
  5. Clic Supprimer séquence #2 → Lignes suivantes position diminuée. Heures recalculées.
  6. 0 console.error JS / 0 Laravel log.INFO ERROR.
- ✅ Tests Tinker optional : récupérer Sequence#X, `$s->statut_terrain` retourne Enum value "termine". `ConducteurCalculatorService::recalculerHeures` → `heure_debut_calculee` correctes.

---

## 6. Risques et Gestion

| Risque | Probabilité | Impact | Mitigation |
|---|---|---|---|
| Migration `upgrade_sequences_step1` échoue (longue, +2 FK) | 🟡 Moyen | 🟠 Bloquant | Toujours `Schema::hasColumn` before add. Rollback transaction wrapped. Toujours tester clone DB avant prod. |
| ProseMirror/TipTap (pas TipTap ici, Alpine plain). Workflow buttons -> event bubbling Alpine @click.stop. | 🟢 Faible | 🟡 UI bug | `@click.stop.prevent` sur chaque bouton workflow. |
| Service Recalcul → position séquences non contiguës (bug gaps après suppression) | 🟡 Moyen | 🟠 Heures incorrectes | Controller destroy avant recalcul → Boucle foreach `$index+1` reset strictement 1..N. |
| Heure début émission NULL → recalcul impossible. | 🟡 Moyen | 🟡 N/A | Controller index: Default heure_debut = emission.heure_debut = 12:00:00 sinon. Flash warning utilisateur. |
| SortableJS non installé → DnD ne marche pas. | 🟢 Faible | 🟡 UI dégradée | `npm i sortablejs` dans le plan. Fallback UI boutons flèches monter/descendre ligne si DnD pas dispo. |
