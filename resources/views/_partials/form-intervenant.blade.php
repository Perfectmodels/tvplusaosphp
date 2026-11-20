@php
    use App\Enums\CiviliteEnum;
    use App\Enums\TypeIntervenantEnum;
    use App\Enums\StatutDispoIntervenantEnum;
    use App\Enums\LangueEnum;
    use Illuminate\Support\Str;

    $isEdit = ($mode ?? '') === 'edit';
    $formAction = $isEdit ? route('intervenants.update', $intervenant) : route('intervenants.store');

    $retourFormulaire = old('_person_form') === '1' || old('_person_form') === 1;
    $domainesArr = old('domaines', $retourFormulaire ? [] : ($intervenant->domaines ?? []));
    $languesArr = old('langues', $retourFormulaire ? [] : ($intervenant->langues ?? []));

    $domainesArr = is_array($domainesArr) ? $domainesArr : [];
    $languesArr = is_array($languesArr) ? $languesArr : [];

    $randId = 'iform-' . Str::random(6);
@endphp
<div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
    <form id="{{ $randId }}-main-form" action="{{ $formAction }}" method="POST" enctype="multipart/form-data" class="space-y-10">
        @csrf
        <input type="hidden" name="_person_form" value="1">
        @if($isEdit) @method('PUT') @endif

        <!-- ===================== SECTION IDENTITÉ (prototype nouve_intervenant.html) ===================== -->
        <div class="bg-surface rounded-xl shadow-sm border border-outline-variant p-6">
            <div class="flex flex-col lg:flex-row gap-8">
                <!-- PHOTO dashed uploader -->
                <div class="w-full lg:w-52 shrink-0" data-portrait data-portrait-fallback="{{ 'https://ui-avatars.com/api/?name='.urlencode($intervenant->initiales ?: 'IV').'&background=0054cd&color=ffffff' }}">
                    <div id="{{ $randId }}-photo-box" class="border-2 border-dashed border-outline-variant rounded-xl h-64 flex flex-col items-center justify-center text-center cursor-pointer hover:bg-surface-container-low transition-colors group relative overflow-hidden">
                        <img id="{{ $randId }}-photo-preview" data-portrait-preview src="{{ $intervenant->photoUrl }}"
                             class="absolute inset-0 w-full h-full object-cover {{ $intervenant->photo || $intervenant->photoUrl ? '' : 'hidden' }}"
                             onerror="this.onerror=null;this.classList.add('hidden');">
                        <div id="{{ $randId }}-photo-empty" data-portrait-empty class="relative z-10 flex flex-col items-center {{ $intervenant->photo || $intervenant->photoUrl ? 'opacity-0 pointer-events-none absolute' : '' }}">
                            <div class="w-12 h-12 rounded-full border-2 border-secondary text-secondary flex items-center justify-center mb-3 group-hover:scale-110 transition-transform relative">
                                <span class="material-symbols-outlined text-xl">person</span>
                                <div class="absolute -bottom-1 -right-1 w-5 h-5 bg-white rounded-full flex items-center justify-center shadow-sm border border-outline-variant">
                                    <span class="material-symbols-outlined text-xs text-secondary">add</span>
                                </div>
                            </div>
                            <span class="text-sm font-semibold text-secondary">Ajouter une photo</span>
                            <span class="text-xs text-on-surface-variant mt-1 px-4">JPG, PNG ou WebP — max 2 Mo</span>
                        </div>
                        <input type="file" name="photo_file" data-portrait-file id="{{ $randId }}-photo-file" accept="image/jpeg,image/png,image/webp" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-20">
                    </div>
                    <div class="mt-3">
                        <label class="form-label">ou URL de la photo</label>
                        <input type="url" name="photo" data-portrait-url id="{{ $randId }}-photo-url" value="{{ old('photo', $intervenant->photo) }}"
                               placeholder="https://..." class="form-input text-xs">
                    </div>
                    <label class="mt-3 flex items-center gap-2 text-sm"><input type="checkbox" name="remove_photo" value="1" data-portrait-remove @checked(old('remove_photo', false))> Retirer la photo</label>
                    <p data-portrait-status class="mt-2 text-sm text-error" role="status"></p>
                </div>
                <!-- Champs infos base (2 cols × 3 rows comme prototype) -->
                <div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5">
                    <div>
                        <label class="form-label">Civilité</label>
                        <select name="civilite" class="form-input pr-10">
                            <option value="">—</option>
                            @foreach(CiviliteEnum::cases() as $c)
                                <option value="{{ $c->value }}" @selected(old('civilite', optional($intervenant->civilite)->value) === $c->value)>{{ $c->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div></div>
                    <div>
                        <label class="form-label">Nom <span class="req">*</span></label>
                        <input required type="text" name="nom" value="{{ old('nom', $intervenant->nom) }}" placeholder="Entrez le nom" class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Prénom <span class="req">*</span></label>
                        <input required type="text" name="prenom" value="{{ old('prenom', $intervenant->prenom) }}" placeholder="Entrez le prénom" class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Fonction / Titre</label>
                        <input type="text" name="fonction" list="{{ $randId }}-options-fonction" value="{{ old('fonction', $intervenant->fonction) }}" placeholder="Sélectionner ou saisir une fonction" class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Organisme / Institution</label>
                        <input type="text" name="organisme" list="{{ $randId }}-options-organisme" value="{{ old('organisme', $intervenant->organisme) }}" placeholder="Sélectionner ou saisir un organisme" class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Poste (si interne)</label>
                        <input type="text" name="poste" list="{{ $randId }}-options-poste" value="{{ old('poste', $intervenant->poste) }}" class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Type d'intervenant</label>
                        <select name="type" class="form-input pr-10">
                            <option value="">Sélectionnez...</option>
                            @foreach(TypeIntervenantEnum::cases() as $e)
                                <option value="{{ $e->value }}" @selected(old('type', optional($intervenant->type)->value) === $e->value)>{{ $e->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label for="{{ $randId }}-domain-select" class="form-label">Domaines d’expertise</label>
                        <select id="{{ $randId }}-domain-select" class="form-input mb-2">
                            <option value="">Choisir un domaine à ajouter…</option>
                            @foreach(($suggestions['domaines'] ?? []) as $domaine)
                                <option value="{{ $domaine }}">{{ $domaine }}</option>
                            @endforeach
                        </select>
                        <div class="border border-outline-variant rounded-xl p-1.5 flex flex-wrap items-center bg-surface-container-lowest min-h-[48px] relative focus-within:ring-2 focus-within:ring-primary-container/20 focus-within:border-primary-container">
                            @foreach($domainesArr as $idx => $tag)
                                @continue(trim($tag) === '')
                                <span class="tag inline-flex items-center gap-1 px-2.5 py-1 bg-secondary-container/10 text-secondary rounded text-xs border border-secondary-container/20 m-0.5" data-idx="{{ $idx }}">
                                    <span class="dom-name">{{ $tag }}</span>
                                    <button type="button" class="close-tag hover:text-error transition-colors" data-idx="{{ $idx }}">&times;</button>
                                </span>
                            @endforeach
                            <input type="text" class="dom-input flex-1 outline-none text-sm p-1.5 bg-transparent min-w-[140px]" placeholder="Ex: Gouvernance, Environnement... (appuyez sur Entrée)">
                        </div>
                        <input type="hidden" name="domaines_json" id="{{ $randId }}-domaines-json" value="{{ json_encode($domainesArr) }}">
                    </div>
                </div>
            </div>
        </div>

        <!-- ===================== SECTION COORDONNÉES (prototype section-title) ===================== -->
        <div class="bg-surface rounded-xl shadow-sm border border-outline-variant p-6">
            <div class="section-title flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">contact_phone</span>
                COORDONNÉES
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5">
                <div>
                    <label class="form-label">Téléphone principal</label>
                    <input type="tel" name="telephone_1" value="{{ old('telephone_1', $intervenant->telephone_1) }}" placeholder="+241 07…" maxlength="30" class="form-input">
                    <p class="mt-1 text-xs text-on-surface-variant">Pour un numéro hors du Gabon, indiquez son indicatif international.</p>
                </div>
                <div>
                    <label class="form-label">Téléphone secondaire (WhatsApp)</label>
                    <input type="tel" name="telephone_2" value="{{ old('telephone_2', $intervenant->telephone_2) }}" placeholder="06 .. .. .. .." class="form-input">
                </div>
                <div>
                    <label class="form-label">E-mail</label>
                    <input type="email" name="email" value="{{ old('email', $intervenant->email) }}" placeholder="prenom.nom@exemple.com" class="form-input">
                </div>
                <div>
                    <label class="form-label">Pays de résidence</label>
                    <input type="text" name="pays_residence" list="{{ $randId }}-options-pays_residence" value="{{ old('pays_residence', $intervenant->pays_residence ?? 'Gabon') }}" placeholder="Sélectionner ou saisir un pays" class="form-input">
                </div>
                <div>
                    <label class="form-label">Ville de résidence</label>
                    <input type="text" name="ville_residence" list="{{ $randId }}-options-ville_residence" value="{{ old('ville_residence', $intervenant->ville_residence) }}" placeholder="Sélectionner ou saisir une ville" class="form-input">
                </div>
                <div>
                    <label class="form-label">Adresse physique</label>
                    <input type="text" name="adresse_physique" value="{{ old('adresse_physique', $intervenant->adresse_physique) }}" placeholder="Quartier, rue..." class="form-input">
                </div>
                <div class="md:col-span-2">
                    <label class="form-label">Langues parlées (plusieurs possibles)</label>
                    <div class="border border-outline-variant rounded-xl p-2 flex flex-wrap items-center gap-1.5 bg-surface-container-lowest">
                        @foreach(LangueEnum::cases() as $lg)
                            <label class="inline-flex items-center gap-1.5 px-3 py-2 min-h-[44px] rounded-lg border border-outline-variant/60 text-sm text-on-surface-variant bg-surface hover:bg-surface-container cursor-pointer transition-colors has-[:checked]:bg-secondary-container/10 has-[:checked]:text-secondary has-[:checked]:border-secondary/40 has-[:checked]:font-semibold">
                                <input type="checkbox" name="langues[]" value="{{ $lg->value }}" class="rounded-sm text-secondary"
                                       @checked(is_array($languesArr) && in_array($lg->value, $languesArr, true))>
                                {{ $lg->label() }}
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <!-- ===================== SECTION PROFIL COMPLÉMENTAIRE (champs DB non-prototype mais nécessaires) ===================== -->
        <div class="bg-surface rounded-xl shadow-sm border border-outline-variant p-6">
            <div class="section-title flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">badge</span>
                PROFIL COMPLÉMENTAIRE
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-5">
                <div>
                    <label class="form-label">Date de naissance</label>
                    <input type="date" name="date_naissance" max="{{ today()->toDateString() }}" value="{{ old('date_naissance', $intervenant->date_naissance?->format('Y-m-d')) }}" class="form-input">
                </div>
                <div>
                    <label class="form-label">Lieu de naissance</label>
                    <input type="text" name="lieu_naissance" value="{{ old('lieu_naissance', $intervenant->lieu_naissance) }}" class="form-input">
                </div>
                <div>
                    <label class="form-label">Pièce d'identité</label>
                    <input type="text" name="piece_identite" value="{{ old('piece_identite', $intervenant->piece_identite) }}" placeholder="CNI, passeport..." class="form-input">
                </div>
                <div>
                    <label class="form-label">Disponibilité</label>
                    <select name="disponibilite" class="form-input pr-10">
                        @foreach(StatutDispoIntervenantEnum::cases() as $e)
                            <option value="{{ $e->value }}" @selected(old('disponibilite', optional($intervenant->disponibilite)->value) === $e->value)>{{ preg_replace('/^[^\s]+\s+/u', '', $e->label()) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Disponibilité active</label>
                    <div class="flex items-center min-h-[44px] gap-3 px-3 border border-outline-variant rounded-xl bg-surface-container-lowest">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" id="{{ $randId }}-isactive" class="w-4 h-4 text-secondary rounded"
                               @checked(old('is_active', $intervenant->is_active ?? true))>
                        <label for="{{ $randId }}-isactive" class="text-sm text-on-surface cursor-pointer">Actif (non blacklisté)</label>
                    </div>
                </div>
                <div></div>
                <div class="md:col-span-3">
                    <label class="form-label">Présentation courte (on-air · lue à l'antenne)</label>
                    <textarea name="presentation_courte" rows="2" maxlength="1000" class="form-input" placeholder="2–3 phrases pour présenter l'invité avant son arrivée à l'antenne">{{ old('presentation_courte', $intervenant->presentation_courte) }}</textarea>
                </div>
                <div class="md:col-span-3">
                    <label class="form-label">Biographie longue</label>
                    <textarea name="biographie_longue" rows="4" class="form-input" placeholder="Parcours, études, publications, récompenses...">{{ old('biographie_longue', $intervenant->biographie_longue) }}</textarea>
                </div>
            </div>
        </div>

        <!-- ===================== SECTION LOGISTIQUE PLATEAU ===================== -->
        <div class="bg-surface rounded-xl shadow-sm border border-outline-variant p-6">
            <div class="section-title flex items-center gap-2">
                <span class="material-symbols-outlined text-sm text-purple-500">restaurant</span>
                LOGISTIQUE &amp; PLATEAU
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5">
                <div>
                    <label class="form-label">Taille habillement</label>
                    <input type="text" name="taille_habillement" value="{{ old('taille_habillement', $intervenant->taille_habillement) }}" placeholder="H-M, F-38, S, L, XL..." class="form-input">
                </div>
                <div>
                    <label class="form-label">Régime alimentaire</label>
                    <input type="text" name="regime_alimentaire" value="{{ old('regime_alimentaire', $intervenant->regime_alimentaire) }}" placeholder="Halal, végétarien, sans gluten..." class="form-input">
                </div>
                <div class="md:col-span-2">
                    <label class="form-label text-error flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-sm">report</span>
                        Sujets sensibles (à éviter en direct)
                    </label>
                    <textarea name="sujets_sensibles" rows="2" class="form-input border-error-container" placeholder="Vie privée, dossiers judiciaires, etc.">{{ old('sujets_sensibles', $intervenant->sujets_sensibles) }}</textarea>
                </div>
            </div>
        </div>

        <!-- ===================== SECTION NOTES (prototype) ===================== -->
        <div class="bg-surface rounded-xl shadow-sm border border-outline-variant p-6">
            <div class="section-title flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">sticky_note_2</span>
                NOTES ADMINISTRATIVES
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5">
                <div>
                    <label class="form-label">Notes internes (équipe)</label>
                    <textarea name="notes" id="{{ $randId }}-notes" rows="5" maxlength="500" placeholder="Informations utiles, préférences, points d'attention..." class="form-input">{{ old('notes', $intervenant->notes) }}</textarea>
                    <div class="text-right text-xs text-on-surface-variant mt-1"><span id="{{ $randId }}-notes-count">0</span> / 500</div>
                </div>
                <div>
                    <label class="form-label">Observations admin</label>
                    <textarea name="observations_admin" rows="5" class="form-input bg-primary-container/5 border-primary-container/30" placeholder="Observations de l'administration...">{{ old('observations_admin', $intervenant->observations_admin) }}</textarea>
                </div>
            </div>
        </div>

        <!-- ===================== FOOTER BUTTONS ===================== -->
        <div class="sticky bottom-0 z-30 -mx-4 sm:-mx-6 lg:-mx-8 px-4 sm:px-6 lg:px-8 pt-4 pb-6 bg-gradient-to-t from-[#F4F7F9] via-[#F4F7F9] to-[#F4F7F9]/90 backdrop-blur-sm">
            <div class="flex flex-col sm:flex-row gap-3 sm:justify-between items-center max-w-7xl mx-auto bg-surface border border-outline-variant rounded-xl px-5 py-3 shadow-sm">
                <div class="flex items-center gap-2 text-xs text-on-surface-variant">
                    <span class="material-symbols-outlined text-sm">info</span>
                    Les champs marqués <span class="req ml-0.5 mr-1">*</span> sont obligatoires
                </div>
                <div class="flex gap-2 sm:gap-3 flex-wrap justify-end">
                    <a href="{{ $isEdit ? route('intervenants.show', $intervenant) : route('intervenants.index') }}"
                       class="inline-flex items-center gap-1.5 px-4 py-2.5 min-h-[44px] text-sm text-on-surface-variant hover:text-primary bg-surface-container rounded-lg hover:bg-surface-container-high transition-colors">
                        <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                        Annuler
                    </a>
                    <button type="reset" class="inline-flex items-center gap-1.5 px-4 py-2.5 min-h-[44px] text-sm text-on-surface border border-outline-variant rounded-lg hover:bg-surface-container transition-colors">
                        <span class="material-symbols-outlined text-[16px]">restart_alt</span>
                        Réinitialiser
                    </button>
                    <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 min-h-[44px] bg-primary-container text-white rounded-lg shadow-sm hover:bg-secondary transition-colors font-semibold text-sm">
                        <span class="material-symbols-outlined text-[18px]">
                            {{ $isEdit ? 'save' : 'person_add' }}
                        </span>
                        {{ $isEdit ? 'Enregistrer les modifications' : 'Créer l\'intervenant · QR code auto' }}
                    </button>
                </div>
            </div>
        </div>
        @foreach(($suggestions ?? []) as $champ => $valeurs)
            @continue($champ === 'domaines')
            <datalist id="{{ $randId }}-options-{{ $champ }}">
                @foreach($valeurs as $valeur)<option value="{{ $valeur }}"></option>@endforeach
            </datalist>
        @endforeach
    </form>
</div>

<script>
(function(){
    const rid = '{{ $randId }}';
    // Domaines pills input
    const domWrapper = document.querySelector('#'+rid+'-main-form .dom-input').closest('.border');
    const domInput = domWrapper.querySelector('.dom-input');
    const domJson = document.getElementById(rid+'-domaines-json');
    let domaines = JSON.parse(domJson.value || '[]');
    const domainesInitiaux = [...domaines];
    const domainSelect = document.getElementById(rid+'-domain-select');
    function renderTags() {
        domWrapper.querySelectorAll('.tag').forEach(t => t.remove());
        domaines.filter(d => d && String(d).trim()).forEach((t, i) => {
            const span = document.createElement('span');
            span.className = 'tag inline-flex items-center gap-1 px-2.5 py-1 bg-secondary-container/10 text-secondary rounded text-xs border border-secondary-container/20 m-0.5';
            span.innerHTML = `<span class="dom-name"></span><button type="button" class="close-tag hover:text-error transition-colors">&times;</button>`;
            span.querySelector('.dom-name').textContent = t;
            span.querySelector('.close-tag').addEventListener('click', () => { domaines.splice(i, 1); domJson.value = JSON.stringify(domaines); renderTags(); });
            domWrapper.insertBefore(span, domInput);
        });
    }
    renderTags();
    domainSelect.addEventListener('change', () => { if (domainSelect.value) addDomain(domainSelect.value); domainSelect.value = ''; });
    function addDomain(tag) {
        tag = String(tag || '').trim();
        if (!tag) return;
        if (domaines.some(value => String(value).toLocaleLowerCase() === tag.toLocaleLowerCase())) return;
        domaines.push(tag);
        domJson.value = JSON.stringify(domaines);
        renderTags();
    }
    domInput.addEventListener('keydown', e => {
        if (e.key === 'Enter' || e.key === ',' || e.key === ';') {
            e.preventDefault();
            addDomain(domInput.value.split(/[,;]/)[0]);
            domInput.value = '';
        } else if (e.key === 'Backspace' && !domInput.value && domaines.length) {
            domaines.pop(); domJson.value = JSON.stringify(domaines); renderTags();
        }
    });
    domInput.addEventListener('blur', () => { if (domInput.value) { addDomain(domInput.value); domInput.value = ''; }});
    domWrapper.querySelectorAll('.close-tag').forEach(b => b.removeEventListener?.('click', null));

    // Notes count
    const notes = document.getElementById(rid+'-notes');
    const notesCount = document.getElementById(rid+'-notes-count');
    if (notes && notesCount) {
        notes.addEventListener('input', () => notesCount.textContent = notes.value.length);
        notesCount.textContent = notes.value.length;
    }

    document.getElementById(rid+'-main-form').addEventListener('reset', () => setTimeout(() => {
        domaines = [...domainesInitiaux];
        domJson.value = JSON.stringify(domaines);
        renderTags();
        if (notes && notesCount) notesCount.textContent = notes.value.length;
    }, 0));

    // Submit : domaines → hidden[] inputs (compat Store/Update Requests)
    document.getElementById(rid+'-main-form').addEventListener('submit', function() {
        if (domInput.value.trim()) addDomain(domInput.value);
        this.querySelectorAll('input[name="domaines[]"]').forEach(i => i.remove());
        domaines.filter(d => d && String(d).trim()).forEach(d => {
            const inp = document.createElement('input');
            inp.type = 'hidden'; inp.name = 'domaines[]'; inp.value = d;
            this.appendChild(inp);
        });
    });
})();
</script>
