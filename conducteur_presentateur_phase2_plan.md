# Plan Implémentation PHASE 2 — CONDUCTEUR PRÉSENTATEUR (CP)
**Spécifications validées via prototype `App/conducteur_presentateur.html`**
**Principe fondamental : Conducteur Rédaction (CR) = SSOT. CP = VUE LECTURE + NOTES ÉDITION SEULEMENT (pas de CRUD lignes).**

---

## 📌 Objectif métier exact (user VERBATIM)
> Permettre de conduire une émission, programmer chaque partie du programme avec les colonnes **Ordre · Heure début · Heure fin · Durée · Type · Titre/sujet**. Avec à droite une section **résumé + prise de notes du présentateur** (WYSIWYG TipTap). **Mode PLEIN ÉCRAN Notes Panneau Droit** (zoom fullscreen).

Exemples séquences : Générique, Introduction (présentateur), Reportage, Débat, Chronique, Conclusion.

---

## 🗄️ Étape 1 — Migration SQL `upgrade_step1_cp`
**1 seule migration 2026_09_06_000003** → modifier 2 tables :

### 1.1 Table `conducteurs` (+ 2 colonnes)
| Colonne | Type | Default | Note |
|---|---|---|---|
| `presentateur_id` | BIGINT UNSIGNED NULL → FK `intervenants(id)` / ou `users(id)` ? → **Proposition: FK `intervenants(id)` ON DELETE SET NULL** | NULL | Sous-header "Conduit par Awa Diallo" |
| `active_sequence_id` | BIGINT UNSIGNED NULL → FK sequences(id) ON DELETE SET NULL | NULL | Ligne ACTIVE (#5 bleue prototype L263). Sync avec `sequences.statut_presentation` |

### 1.2 Table `sequences` (+ 2 colonnes)
| Colonne | Type | Default | Note |
|---|---|---|---|
| `notes_presentateur` | MEDIUMTEXT NULL | NULL | HTML TipTap sauvegardé éditeur panneau droit |
| `statut_presentation` | ENUM (`Avenir`, `EnCours`, `Termine`) default = `Avenir` | Avenir | Contrôle l'état ligne ACTIVE (EnCours → barre bleue gauche) |

---

## 🧠 Étape 2 — Enums + Models (mise à jour incrémentale, 90% déjà prêt)

### 2.1 Nouvel Enum : `StatutPresentationSequenceEnum.php`
3 cas : `Avenir` (gris normal) / `EnCours` (barre bleue gauche active) / `Termine` (text-muted opacity-70).
→ badge helpers (label, class, icon).

### 2.2 Model `Conducteur.php` : Ajouter
```php
protected $fillable .= ', presentateur_id, active_sequence_id';
protected $casts = [
   'presentateur_id' => 'integer',
   'active_sequence_id' => 'integer',
];
// Relations
public function presentateur(): BelongsTo { return $this->belongsTo(Intervenant::class, 'presentateur_id'); }
public function sequenceActive(): BelongsTo { return $this->belongsTo(Sequence::class, 'active_sequence_id'); }
```

### 2.3 Model `Sequence.php` : Ajouter
```php
protected $fillable .= ', notes_presentateur, statut_presentation';
protected $casts = [
   'notes_presentateur' => 'string',  // HTML brut (TipTap->getHTML)
   'statut_presentation' => StatutPresentationSequenceEnum::class,
];
// Nouvel Accesseur HTML preview Notes (pour listes) : getNotesPreviewAttribute → strip_tags()->limit(120)
// Nouvel Scope scopePresentationOrder() : orderBy('position', 'ASC')
```

---

## 🛡️ Étape 3 — Policies (Permissions granularisées)
**`SequencePolicy.php`** → ajouter 2 méthodes (100% Spatie Permissions + rôle Présentateur) :
```php
// Autoriser édition NOTES présentateur
public function updateNotesPresentateur(User $user, Sequence $sequence): bool
{
    // ✅ Chef édition, Administrateur, → OK
    // ✅ PRÉSENTATEUR ASSIGNÉ au conducteur → OK
    // ❌ Journaliste / Habilleur → 403
    return $user->hasAnyRole(['admin','chef_redaction','chef_edition'])
        || ($user->intervenant_id === $sequence->conducteur->presentateur_id);
}

// Autoriser ACTIVER une séquence (barre bleue gauche statut EnCours)
public function activerSequence(User $user, Sequence $sequence): bool {
    return $user->hasAnyRole(['admin','chef_redaction','presentateur']);
}
```

---

## 🧭 Étape 4 — Routes (3 routes nouvelles, resource conducteurs déjà existant)
Ajouter dans **`routes/module_autres.php`** :
```php
// 1. Page INDEX Conducteur Présentateur (entrée menu navbar)
Route::get('/conducteurs/presentateur', [ConducteurPresentateurController::class, 'index'])
    ->name('conducteurs.presentateur.index')
    ->middleware(['auth','verified']);

// 2. Page SHOW = interface 2 panneaux (objet conducteur id passé)
Route::get('/conducteurs/presentateur/{conducteur}', [ConducteurPresentateurController::class, 'show'])
    ->name('conducteurs.presentateur.show')
    ->middleware(['auth','verified']);

// 3. API ENDPOINT : Save Notes Presentateur (xhr PATCH, autorisé updateNotesPresentateur)
Route::patch('/sequences/{sequence}/notes-presentateur', [SequenceController::class, 'updateNotesPresentateur'])
    ->name('sequences.notes-presentateur.update')
    ->middleware(['auth','verified']);

// 4. API ENDPOINT : Activer Séquence (statut_presentation → EnCours, active_sequence_id conducteur)
Route::patch('/conducteurs/{conducteur}/sequence-active/{sequence}', [ConducteurPresentateurController::class, 'activerSequence'])
    ->name('conducteurs.presentateur.activer-sequence')
    ->middleware(['auth','verified']);
```

---

## 🎛️ Étape 5 — Controllers (2 controllers)
### 5.1 **NOUVEAU** `ConducteurPresentateurController.php`
```php
class ConducteurPresentateurController extends Controller
{
    // INDEX : listing conducteurs (même CR + présentateur_id + boutons "Ouvrir")
    public function index(Request $request) {
        // View: conducteur_presentateur_index.blade.php (listing)
    }

    // SHOW : Interfacce 2 panneaux (LE COEUR DU MODULE)
    public function show(Request $request, Conducteur $conducteur) {
        // EAGER LOAD : sequences (orderBy position), presentateur, emission
        $sequences = $conducteur->sequences()->orderBy('position')->get();

        // Passer ENUM HTML BADGE Type pré-rendus côté blade (éviter bug @json truncate)
        // Passer presentateur nom complet AppBar "Conduit par {Awa Diallo}"
        // Passer notes_presentateur HTML déjà là si existant
        return view('conducteur_presentateur', compact('conducteur', 'sequences'));
    }

    // ✅ API : Activer séquence (Appelé xhr fetch Alpine quand click ligne)
    public function activerSequence(Request $request, Conducteur $conducteur, Sequence $sequence): JsonResponse
    {
        Gate::authorize('activerSequence', $sequence);
        // 1. Reset toutes séquences ENCOURS → Avenir
        $conducteur->sequences()->where('statut_presentation', StatutPresentationSequenceEnum::EnCours)
            ->update(['statut_presentation' => StatutPresentationSequenceEnum::Avenir]);
        // 2. Séquence courante → EnCours
        $sequence->update(['statut_presentation' => StatutPresentationSequenceEnum::EnCours]);
        // 3. Maj FK conducteur active_sequence_id
        $conducteur->update(['active_sequence_id' => $sequence->id]);
        // 4. Recalcul progression
        return response()->json(['ok' => true, 'message' => "Séquence #{$sequence->position} activée."]);
    }
}
```

### 5.2 Update `SequenceController.php` (+ méthode)
```php
public function updateNotesPresentateur(Request $request, Sequence $sequence): JsonResponse
{
    $valid = $request->validate([
        'notes_presentateur' => ['nullable', 'string'],  // HTML TipTap accepté (fait front)
    ]);
    $this->authorize('updateNotesPresentateur', $sequence);
    $sequence->update(['notes_presentateur' => $valid['notes_presentateur'] ?? null]);
    return response()->json(['ok' => true, 'message' => 'Notes présentateur enregistrées.']);
}
```

---

## 🎨 Étape 6 — Vue Blade `conducteur_presentateur.blade.php`
**LAYOUT 2 PANNEAUX FLEX 100vh** (comme prototype L167 à L390). Structure exacte :
```blade
<x-app-layout>
{{-- APPBAR MID'INFOS "Conduit par X" + Recherche + Date --}}
<x-slot name="header">...</x-slot>

{{-- BODY PRINCIPAL FLEX w-screen h-[calc(100vh-var(--navbarH))] overflow-hidden --}}
<div id="cp-layout" class="flex w-full h-[calc(100vh-64px)] overflow-hidden">

  {{-- ⬅️ PANEL GAUCHE FLEX-1 (tableau scrollable) --}}
  <div id="cp-left" class="flex-1 flex flex-col overflow-hidden">
    {{-- TABLEAU 6 COL exact prototype : Ordre | Début | Fin | Durée | Type | Titre --}}
    {{-- CLICK LIGNE -> @click="selectSequence(seq)" (Alpine) --}}
    {{-- CLASSE LIGNE ACTIVE : si stat=EnCours → bg-primary-fixed/30 + border-l-4 border-primary-container --}}
    {{-- FOOTER sticky : "Durée totale de l'émission : XX:XX" --}}
  </div>

  {{-- ➡️ PANEL DROIT w-[500px] FIXE (Shadow, Notes) --}}
  <div id="cp-right-panel"
       class="transition-[width,height,inset] duration-300 w-[500px] shrink-0
              border-l border-outline/10 flex flex-col bg-surface-dim
              data-[mode=fullscreen]:fixed data-[mode=fullscreen]:inset-0 data-[mode=fullscreen]:w-full data-[mode=fullscreen]:z-50">

    {{-- BLOC TOP : × Fermer + ⛶ Bouton FULLSCREEN (toggle data-mode fullscreen) --}}
    <div class="flex justify-between p-4 border-b border-outline/10">
      <button data-action="close-right">×</button>
      <button @click="toggleFullscreen" data-action="fullscreen">⛶ Fullscreen</button>
    </div>

    {{-- BLOC MIDDLE scrollable : Header TypeBadge + Titre + Angle + Journaliste + Durée META --}}
    <div class="flex-1 overflow-y-auto">...</div>

    {{-- BLOC BOTTOM : 📝 NOTES TIPTAP MINI + BOUTON ENREGISTRER --}}
    <div class="border-t border-outline/10 p-4">
        {{-- Toolbar TipTap 6 outils (Gras/Italique/Puces/Num/Link/Separateur) --}}
        <div id="tiptap-notes-toolbar"></div>
        {{-- Editor Mount #tiptap-notes-mount --}}
        <div id="tiptap-notes-mount" wire:ignore></div>
        {{-- Bouton Enregistrer w-full bg-primary-container --}}
        <button @click="saveNotes">💾 Enregistrer notes</button>
        <p class="text-xs text-on-surface-variant mt-2">Modifiable à tout moment.</p>
    </div>
  </div>
</div>
</x-app-layout>
```

---

## ⚡ Étape 7 — JS Alpine Component `conducteurPresentateur.js`
Nouveau fichier `resources/js/conducteur-presentateur.js` (300 lignes estimé) :
```js
import Alpine from 'alpinejs';
import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/link';

export default function conducteurPresentateur() {
  return {
    // State
    activeSequenceId: null,   // Sync blade PHP conducteur.active_sequence_id
    panelMode: 'normal',      // 'normal' (500px) | 'fullscreen' (fixed inset-0)
    notesHtml: '',            // Bind HTML TipTap -> backend
    notesDirty: false,        // Sauvegarde auto 8s si dirty (confort user)
    isSaving: false,

    // 1. Init
    init(payload) { /* load sequencesJSON + csrf + routes */ },

    // 2. Sélection / activation ligne
    selectSequence(seq) {
      this.activeSequenceId = seq.id;
      this.notesHtml = seq.notes_presentateur || '';
      // ✅ OPTION : Appeler API activerSequence() (MAJ barre bleue gauche BDD)
      // fetch PATCH /conducteurs/{id}/sequence-active/{seq.id}
    },

    // 3. Toggle Fullscreen Panneau Notes
    toggleFullscreen() {
      this.panelMode = (this.panelMode === 'normal') ? 'fullscreen' : 'normal';
      // Sync DOM dataset : cp-right-panel.dataset.mode = this.panelMode
    },

    // 4. TipTap Mini Notes (StarterKit Bold/Italic/List + Link, 6 outils prototype)
    setupTipTap() { ... },

    // 5. Enregistrer Notes (manuel ou auto)
    async saveNotes() {
      this.isSaving = true;
      await fetch(`/sequences/${this.activeSequenceId}/notes-presentateur`, {
        method: 'PATCH',
        headers: {X-CSRF, Accept:json, Content-Type:json, X-Requested-With: XMLHttpRequest},
        body: JSON.stringify({ notes_presentateur: this.editor.getHTML() })
      });
      this.notesDirty = false;
      this.isSaving = false;
    },

    // 6. Sauvegarde auto (debounce 8 secondes)
    observeDirty() { if (dirty) debounce save }
  };
}
```

### Enregistrement app.js
```js
// app.js
import './conducteur-presentateur';
// Puis toujours Alpine.start() en DERNIER (ne pas bouger !)
```

---

## ✅ Étape 8 — Vérifications & Smoke Test UI (5 scénarios)
| Test | Résultat attendu |
|---|---|
| 1. **Affichage** CP show/1 → Tableau 6 cols, 5 séquences démo présentes | ✅ Type, heures, durée 100% |
| 2. **Click Séquence #3 Reportage** → Panneau droit rempli, **ligne #3 BLEUE gauche bg-primary** | ✅ statut_presentation → EnCours |
| 3. **Éditeur TipTap Notes mini** → Tape texte, Gras, liste → bouton Enregistrer → OK 200 + reload page conserve HTML | ✅ notes_presentateur BDD bien stocké |
| 4. **Plein écran** → bouton ⛶ → panneau droit passe width=100vw fixed | ✅ Transition CSS, click × revenir normal |
| 5. **Permissions** → Utilisateur Journaliste, ouvre CP → bouton Enregistrer grisé (policy 403) → Admin → OK | ✅ Backend + UI disabled |

---

## 🧹 Étape 9 — Cleanup / Misc
1. Menu Sidebar navbar → lien **"Conducteurs → Vue Présentateur"** (à côté Vue Rédaction)
2. PHP Lint / `php artisan migrate --force`
3. `npm run build` → bundle nouveau

---

## ⏱️ Estimation temps
| Étape | Durée |
|---|---|
| Étape 1 Migration SQL | 5 min |
| Étape 2 Enum + Model Update | 10 min |
| Étape 3 Policies | 5 min |
| Étape 4 Routes + Controller CRUD | 20 min |
| Étape 6 Blade 2 panneaux | 30 min |
| Étape 7 JS Alpine + TipTap Mini | 30 min |
| Étape 8 Smoke Tests | 15 min |
| **Total estimé** | **~2h15 (1 session)** |

---

## 🚦 STATUT : **PRÊT À IMPLÉMENTER**
✅ Décisions prises, tout a été vérifié prototype source unique `App/conducteur_presentateur.html`.  
🔴 **ATTENTE APPROBATION USER AVANT TOUTE MODIFICATION CODE.**

*Approbation = répondre :"OK" ou "Go Phase 2" → je démarre l'implémentation.*
