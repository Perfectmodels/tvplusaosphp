@php
    // flash-status est déjà affiché par layouts/app.blade.php : ne pas
    // l'inclure ici, sinon chaque message d'erreur apparaît DEUX fois.
    use App\Enums\CiviliteEnum;
    use App\Enums\FonctionJournalisteEnum;
    use App\Enums\PoleRubriqueEnum;
    use App\Enums\StatutJournalisteEnum;

    $isEdit = ($mode ?? '') === 'edit';
    $formAction = $isEdit
        ? route('journalistes.update', $journaliste)
        : route('journalistes.store');
    $methodField = $isEdit ? method_field('PUT') : '';

    $j = $journaliste;
    if (!($j instanceof \App\Models\Journaliste)) {
        $j = new \App\Models\Journaliste();
    }

    $photoFallback = 'https://ui-avatars.com/api/?name='.urlencode($j->nomComplet ?? 'Nouveau').'&background=001a41&color=ffffff&size=256';
    $photoDisplay = $j->photoUrl ?: $photoFallback;

    $specialitesVal = old('specialites_text', $j->specialites ? implode("\n", $j->specialites) : '');
@endphp

<div class="max-w-6xl mx-auto space-y-8">
    <div class="bg-surface-container-low border border-outline-variant rounded-xl overflow-hidden">
        <div class="p-6 border-b border-outline-variant bg-surface-container flex flex-col items-start gap-4 sm:flex-row sm:items-center" data-portrait data-portrait-fallback="{{ $photoFallback }}">
            <div class="w-20 h-20 rounded-xl bg-surface-container-highest border-2 border-dashed border-outline flex items-center justify-center overflow-hidden flex-shrink-0">
                <img id="photo-preview" data-portrait-preview src="{{ $photoDisplay }}" class="w-full h-full object-cover" alt="Photo de profil" onerror="this.src='{{ $photoFallback }}'">
            </div>
            <div class="min-w-0 w-full flex-1">
                <p class="font-semibold text-primary text-base mb-1">Photo de profil</p>
                <p class="text-xs text-secondary mb-3 max-w-md">JPG, PNG ou WebP — 2 Mo maximum. Une image carrée de 400×400 px est recommandée. Sans photo, les initiales sont affichées.</p>
                <div class="flex flex-wrap items-center gap-2">
                    <div class="min-w-0 w-full flex-1 max-w-sm">
                        <input type="url" name="photo" form="journaliste-main-form" data-portrait-url id="photo-url-input" value="{{ old('photo', $j->photo) }}" placeholder="URL de la photo (https://...)" class="form-input text-xs">
                    </div>
                    <span class="text-xs text-secondary">ou</span>
                    <label class="px-4 py-2.5 min-h-[44px] bg-surface-container-lowest border border-outline-variant rounded-lg text-secondary hover:bg-surface-container transition-colors font-medium text-xs cursor-pointer inline-flex items-center gap-1.5">
                        <i class="fa-solid fa-upload"></i> Fichier local
                        <input type="file" name="photo_file" form="journaliste-main-form" data-portrait-file accept="image/jpeg,image/png,image/webp" class="sr-only">
                    </label>
                </div>
                <label class="mt-3 flex items-center gap-2 text-sm"><input type="checkbox" name="remove_photo" form="journaliste-main-form" value="1" data-portrait-remove @checked(old('remove_photo', false))> Retirer la photo</label>
                <p data-portrait-status class="mt-2 text-sm text-error" role="status"></p>
            </div>
        </div>
    </div>

    <form action="{{ $formAction }}" method="POST" enctype="multipart/form-data" id="journaliste-main-form" class="space-y-8" novalidate>
        @csrf
        {!! $methodField !!}

        <div class="bg-surface-container-low border border-outline-variant rounded-xl overflow-hidden">
            <div class="p-6 border-b border-outline-variant">
                <h3 class="section-title flex items-center gap-2 !mb-0 !pb-3 !border-0">
                    <i class="fa-regular fa-id-card"></i> Identité
                </h3>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                <div>
                    <label class="form-label">Civilité</label>
                    <select name="civilite" class="form-input">
                        <option value="">Sélectionner</option>
                        @foreach(CiviliteEnum::cases() as $c)
                            <option value="{{ $c->value }}" @selected(old('civilite', optional($j->civilite)->value) === $c->value)>{{ preg_replace('/^[^\s]+\s+/u', '', $c->label()) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Nom<span class="req">*</span></label>
                    <input required type="text" name="nom" value="{{ old('nom', $j->nom) }}" class="form-input" placeholder="Ex : Ndiaye">
                </div>
                <div>
                    <label class="form-label">Prénom<span class="req">*</span></label>
                    <input required type="text" name="prenom" value="{{ old('prenom', $j->prenom) }}" class="form-input" placeholder="Ex : Fatoumata">
                </div>
                <div>
                    <label class="form-label">Date de naissance</label>
                    <input type="date" name="date_naissance" value="{{ old('date_naissance', $j->date_naissance?->format('Y-m-d')) }}" class="form-input">
                </div>
                <div>
                    <label class="form-label">Lieu de naissance</label>
                    <input type="text" name="lieu_naissance" value="{{ old('lieu_naissance', $j->lieu_naissance) }}" class="form-input" placeholder="Ex : Libreville, Gabon">
                </div>
                <div>
                    <label class="form-label">Matricule</label>
                    <div class="relative">
                        <input type="text" name="matricule" value="{{ old('matricule', $j->matricule) }}" class="form-input bg-surface-container text-primary font-mono font-semibold" placeholder="{{ $isEdit ? '' : 'Généré automatiquement' }}">
                        @if(!$isEdit)
                            <i class="fa-solid fa-wand-magic-sparkles absolute right-3 top-1/2 -translate-y-1/2 text-primary/60 text-xs"></i>
                        @endif
                    </div>
                    <p class="text-xs text-secondary mt-1"><i class="fa-solid fa-circle-info mr-1"></i>Matricule employé unique : J-Année-N°. Laissez vide pour le générer à l’enregistrement.</p>
                </div>
            </div>
        </div>

        <div class="bg-surface-container-low border border-outline-variant rounded-xl overflow-hidden">
            <div class="p-6 border-b border-outline-variant">
                <h3 class="section-title flex items-center gap-2 !mb-0 !pb-3 !border-0">
                    <i class="fa-solid fa-briefcase"></i> Poste &amp; Statut
                </h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    <div>
                        <label class="form-label">Fonction<span class="req">*</span></label>
                        <select required name="fonction" class="form-input">
                            <option value="">Sélectionner une fonction</option>
                            @foreach(FonctionJournalisteEnum::cases() as $e)
                                <option value="{{ $e->value }}" @selected(old('fonction', optional($j->fonction)->value) === $e->value)>{{ preg_replace('/^[^\s]+\s+/u', '', $e->label()) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Pôle (Rubrique)</label>
                        <select name="pole" class="form-input">
                            <option value="">Sélectionner un pôle</option>
                            @foreach(PoleRubriqueEnum::cases() as $e)
                                <option value="{{ $e->value }}" @selected(old('pole', optional($j->pole)->value) === $e->value)>{{ preg_replace('/^[^\s]+\s+/u', '', $e->label()) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Statut</label>
                        <select name="statut" class="form-input">
                            @foreach(StatutJournalisteEnum::cases() as $e)
                                <option value="{{ $e->value }}" @selected(old('statut', optional($j->statut)->value) === $e->value)>{{ preg_replace('/^[^\s]+\s+/u', '', $e->label()) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Date d'embauche</label>
                        <input type="date" name="date_embauche" value="{{ old('date_embauche', $j->date_embauche?->format('Y-m-d')) }}" class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Fiche et accès actifs</label>
                        <input type="hidden" name="is_active" value="0">
                        <label class="flex items-center gap-2 min-h-[44px]">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $j->is_active ?? true)) class="w-4 h-4 text-primary rounded border-outline-variant focus:ring-primary/20">
                            <span class="text-sm font-medium">Actif</span>
                        </label>
                    </div>
                    <div>
                        <label class="form-label">Présentateur</label>
                        <label class="flex items-center gap-2 min-h-[44px]">
                            <input type="hidden" name="is_presentateur" value="0">
                            <input type="checkbox" name="is_presentateur" value="1" @checked(old('is_presentateur', $j->is_presentateur)) class="w-4 h-4 text-primary rounded border-outline-variant focus:ring-primary/20">
                            <span class="text-sm font-medium">🎙️ Est présentateur</span>
                        </label>
                    </div>
                </div>
                <div class="mt-5">
                    <label class="form-label">Spécialités</label>
                    <div class="grid md:grid-cols-2 gap-3">
                        <select data-add-to-lines="specialites-textarea" class="form-input md:col-span-2" aria-label="Ajouter une spécialité">
                            <option value="">Choisir une spécialité à ajouter…</option>
                            @foreach(($optionsSpecialites ?? []) as $specialite)<option value="{{ $specialite }}">{{ $specialite }}</option>@endforeach
                        </select>
                        <textarea name="specialites_text" id="specialites-textarea" rows="4" class="form-input font-mono text-xs" placeholder="Une spécialité par ligne. Vous pouvez aussi saisir une spécialité personnalisée.">{{ $specialitesVal }}</textarea>
                        <div id="tags-preview" class="border border-outline-variant rounded-lg p-3 bg-surface-container-lowest min-h-[104px] flex flex-wrap gap-2 items-start content-start">
                        </div>
                    </div>
                    <p class="text-xs text-secondary mt-1.5">Choisissez une spécialité dans la liste ou saisissez une spécialité libre, une par ligne.</p>
                </div>
            </div>
        </div>

        <div class="bg-surface-container-low border border-outline-variant rounded-xl overflow-hidden">
            <div class="p-6 border-b border-outline-variant">
                <h3 class="section-title flex items-center gap-2 !mb-0 !pb-3 !border-0">
                    <i class="fa-solid fa-address-book"></i> Coordonnées
                </h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="form-label">Email professionnel<span class="req">*</span></label>
                        <div class="relative">
                            <input required type="email" name="email" value="{{ old('email', $j->email) }}" class="form-input pl-9" placeholder="prenom.nom@tvplusos.ga">
                            <i class="fa-solid fa-envelope absolute left-3 top-1/2 -translate-y-1/2 text-secondary/60 text-sm"></i>
                        </div>
                    </div>
                    <div>
                        <label class="form-label">Téléphone</label>
                        <div class="relative">
                            <input type="tel" name="telephone" value="{{ old('telephone', $j->telephone) }}" class="form-input pl-9" placeholder="+241 07 .. .. .. ..">
                            <i class="fa-solid fa-phone absolute left-3 top-1/2 -translate-y-1/2 text-secondary/60 text-sm"></i>
                        </div>
                    </div>
                    <div class="md:col-span-2">
                        <label class="form-label">Adresse postale</label>
                        <div class="relative">
                            <input type="text" name="adresse" value="{{ old('adresse', $j->adresse) }}" class="form-input pl-9" placeholder="Immeuble, rue, commune, ville, pays">
                            <i class="fa-solid fa-location-dot absolute left-3 top-1/2 -translate-y-1/2 text-secondary/60 text-sm"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-surface-container-low border border-outline-variant rounded-xl overflow-hidden">
            <div class="p-6 border-b border-outline-variant">
                <h3 class="section-title flex items-center gap-2 !mb-0 !pb-3 !border-0">
                    <i class="fa-solid fa-user-shield"></i> Compte utilisateur
                </h3>
            </div>
            <div class="p-6 space-y-4">
                @if($isEdit && $j->user)
                    <p class="font-semibold">Compte lié : {{ $j->user->email }}</p>
                    <p class="text-sm text-secondary">Le nom, l’adresse e-mail, le téléphone et l’activation seront mis à jour avec cette fiche. Le mot de passe et les rôles restent ceux du compte.</p>
                    @can('viewAny', \App\Models\User::class)
                        <a href="{{ route('users.edit', $j->user) }}" class="btn btn-secondary">Gérer les accès du compte</a>
                    @endcan
                @elseif($isEdit)
                    <input type="hidden" name="creer_compte" value="0">
                    <label class="flex items-start gap-3">
                        <input type="checkbox" name="creer_compte" value="1" @checked(old('creer_compte', true)) class="mt-1">
                        <span>Créer le compte utilisateur à l’enregistrement</span>
                    </label>
                    <p class="text-sm text-secondary">L’adresse e-mail professionnelle servira à se connecter. Un mot de passe temporaire sera affiché après l’enregistrement.</p>
                @else
                    <p class="font-semibold">Le compte utilisateur sera créé avec la fiche journaliste.</p>
                    <p class="text-sm text-secondary">Connexion avec l’adresse e-mail professionnelle, rôle Journaliste et mot de passe temporaire à changer à la première connexion. Le matricule employé sera visible sur la fiche.</p>
                    @can('create', \App\Models\User::class)
                        <label for="journaliste-user-id" class="form-label">Compte existant à rattacher (facultatif)</label>
                        <select id="journaliste-user-id" name="user_id" class="form-input">
                            <option value="">Créer un nouveau compte</option>
                            @foreach($comptesDisponibles as $u)
                                <option value="{{ $u->id }}" data-email="{{ $u->email }}" @selected(old('user_id') == $u->id)>{{ $u->name }} — {{ $u->email }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-secondary">Seuls les comptes sans fiche journaliste sont proposés. Leur mot de passe et leurs rôles sont conservés.</p>
                    @endcan
                @endif
            </div>
        </div>

        <div class="bg-surface-container-low border border-outline-variant rounded-xl overflow-hidden">
            <div class="p-6 border-b border-outline-variant">
                <h3 class="section-title flex items-center gap-2 !mb-0 !pb-3 !border-0">
                    <i class="fa-solid fa-note-sticky"></i> Notes administratives
                </h3>
            </div>
            <div class="p-6">
                <textarea rows="5" name="notes_admin" class="form-input resize-y" placeholder="Informations internes, remarques RH, consignes particulières, historique... Ex : Suivre formation habillage septembre 2026. Disponibilité renforcée sur élections.">{{ old('notes_admin', $j->notes_admin) }}</textarea>
                <div class="flex items-center justify-between mt-3 text-xs text-secondary">
                    <p><i class="fa-solid fa-lock mr-1"></i>Visible uniquement par la Direction, RH et Chefs d'édition</p>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-3 pt-2 pb-10">
            <a href="{{ $isEdit ? route('journalistes.show', $j) : route('journalistes.index') }}" class="flex items-center gap-2 px-5 py-2.5 min-h-[44px] border border-outline rounded-lg text-secondary hover:bg-surface-container transition-colors font-medium">
                <i class="fa-solid fa-xmark"></i> Annuler
            </a>
            <button type="reset" class="flex items-center gap-2 px-5 py-2.5 min-h-[44px] border border-outline-variant rounded-lg bg-surface-container-lowest hover:bg-surface-container transition-colors font-medium">
                <i class="fa-solid fa-rotate-left"></i> Réinitialiser
            </button>
            <button type="submit" class="flex items-center gap-2 px-6 py-2.5 min-h-[44px] bg-primary text-on-primary rounded-lg hover:bg-primary-container transition-colors font-medium shadow-sm">
                <i class="fa-solid fa-check"></i> {{ $isEdit ? 'Enregistrer les modifications' : 'Créer le journaliste et son compte' }}
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const account = document.getElementById('journaliste-user-id');
    account?.addEventListener('change', () => {
        const email = account.selectedOptions[0]?.dataset.email;
        if (email) document.querySelector('#journaliste-main-form input[name="email"]').value = email;
    });
    const ta = document.getElementById('specialites-textarea');
    const preview = document.getElementById('tags-preview');

    function refreshTags() {
        if (!ta || !preview) return;
        const tags = ta.value.split('\n').map(l => l.trim()).filter(Boolean);
        preview.replaceChildren();
        if (!tags.length) {
            const empty = document.createElement('span');
            empty.className = 'text-xs text-secondary italic py-0.5';
            empty.textContent = 'Les spécialités saisies apparaîtront ici.';
            preview.appendChild(empty);
        }
        tags.forEach(tag => {
            const span = document.createElement('span');
            span.className = 'tag';
            span.textContent = tag;
            preview.appendChild(span);
        });
    }
    refreshTags();
    ta?.addEventListener('input', refreshTags);

    const form = document.getElementById('journaliste-main-form');
    form?.addEventListener('submit', () => {
        document.querySelectorAll('input[name="specialites[]"]').forEach(i => i.remove());
        const tags = ta.value.split('\n').map(l => l.trim()).filter(Boolean);
        tags.forEach(t => {
            const inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'specialites[]';
            inp.value = t;
            form.appendChild(inp);
        });
    });
});
</script>
