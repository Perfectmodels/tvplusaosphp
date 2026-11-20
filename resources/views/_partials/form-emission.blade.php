@php
$method = $emission->exists ? 'PUT' : 'POST';
$route  = $emission->exists ? route('emissions.update', $emission) : route('emissions.store');
$presentateursEnregistres = $emission->presentateurs->sortBy('pivot.ordre');
$presentateursMeta = $presentateursDispos->concat($presentateursEnregistres)->keyBy('id');
$presentateursActuels = session()->hasOldInput('_emission_form')
    ? collect(is_array(old('presentateurs')) ? old('presentateurs') : [])->filter(fn ($row) => is_array($row) && isset($row['journaliste_id']) && is_numeric($row['journaliste_id']) && $presentateursMeta->has((int) $row['journaliste_id']))->values()
    : $presentateursEnregistres->map(fn ($j) => ['journaliste_id' => $j->id, 'role' => $j->pivot->role, 'statut_affectation' => $j->pivot->statut_affectation, 'ordre' => $j->pivot->ordre])->values();
$joursKeys = array_keys($joursOptions);
$retourFormulaire = session()->hasOldInput('_emission_form');
$languesVals = old('langues', $retourFormulaire ? [] : ($emission->langues ?? ['Français']));
$joursVals = old('jours_diffusion', $retourFormulaire ? [] : ($emission->jours_diffusion ?? []));
$languesVals = is_array($languesVals) ? $languesVals : [];
$joursVals = is_array($joursVals) ? $joursVals : [];
@endphp
<form id="emission-form" action="{{ $route }}" method="POST" class="space-y-6" data-emission-form data-error-fields="{{ json_encode($errors->keys()) }}" novalidate>
    @csrf
    <input type="hidden" name="_emission_form" value="1">
    @if($method === 'PUT') @method('PUT') @endif

    <!-- WIZARD TABS (4 étapes confirmées: INFO / PRES / DIFFUSION / RESUME) -->
    <div class="card-surface">
        <div class="flex items-start flex-wrap" role="tablist" data-wizard-tabs>
            @foreach(['1'=>'Infos générales','2'=>'Présentateurs','3'=>'Paramètres diffusion','4'=>'Résumé & validation'] as $n=>$lib)
                <button type="button" data-step="{{ $n }}" @class(['wizard-tab flex flex-col items-center shrink-0 w-36 sm:w-40 group']) role="tab" aria-selected="{{ $n==='1'?'true':'false' }}" tabindex="{{ $n==='1'?'0':'-1' }}">
                    <span @class(['step-circle w-10 h-10 rounded-full border-2 flex items-center justify-center font-semibold text-sm bg-white transition group-aria-selected:bg-primary group-aria-selected:text-white group-aria-selected:border-primary border-slate-300 text-slate-500'])>{{ $n }}</span>
                    <span @class(['step-label mt-2 text-xs text-center leading-snug transition group-aria-selected:text-primary group-aria-selected:font-semibold text-slate-500'])>{{ $lib }}</span>
                </button>
                @if($n<4)<div class="step-connector flex-1 min-w-[1rem] h-0.5 bg-slate-200 mt-5"></div>@endif
            @endforeach
        </div>
    </div>

    <!-- STEP 1 : INFOS GENERALES -->
    <section data-panel="1" class="card-surface">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-primary-container text-primary flex items-center justify-center font-bold text-sm">1</div>
            <div>
                <h2 class="font-bold text-slate-900">Informations générales</h2>
                <p class="text-xs text-slate-500">Identité de base de l'émission</p>
            </div>
        </div>
        <div class="p-6 grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-1">
                <label class="block text-sm font-semibold text-slate-700 mb-2">Logo / Couleur de l'émission</label>
                <div class="border-2 border-dashed border-slate-300 rounded-xl p-6 bg-slate-50 text-center">
                    <div class="w-24 h-24 mx-auto rounded-2xl bg-gradient-to-br from-primary/10 to-primary/30 flex items-center justify-center text-primary text-4xl mb-3">
                        <i class="fa-solid fa-tv"></i>
                    </div>
                    <input type="hidden" name="couleur_habillage" value="{{ old('couleur_habillage', $emission->couleur_habillage ?? '#0054cdFF') }}">
                    <div class="flex justify-center gap-2 mt-3 flex-wrap">
                        @foreach(['#b91c1cFF','#1e3a8aFF','#f59e0bFF','#7c3aedFF','#059669FF','#0891b2FF','#0ea5e9FF','#4b5563FF'] as $c)
                            <button type="button" data-color="{{ $c }}" @class(['w-11 h-11 rounded-lg ring-2 ring-offset-2 transition hover:scale-105','ring-primary'=>Str::limit(old('couleur_habillage',$emission->couleur_habillage??'#0054cdFF'),7,'')===Str::limit($c,7,''),'ring-slate-200'=>Str::limit(old('couleur_habillage',$emission->couleur_habillage??'#0054cdFF'),7,'')!==Str::limit($c,7,'')]) style="background:{{ $c }}"></button>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="lg:col-span-2 space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Nom de l'émission <span class="text-error">*</span></label>
                    <input name="nom" type="text" placeholder="Ex: MID'INFOS" value="{{ old('nom', $emission->nom) }}" required maxlength="255"
                        class="input-field">
                    @error('nom')<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Sous-titre</label>
                    <input name="sous_titre" type="text" placeholder="Ex: Le journal de midi — Débrief & décryptage" value="{{ old('sous_titre', $emission->sous_titre) }}" maxlength="500"
                        class="input-field">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Description</label>
                    <textarea name="description" rows="4" placeholder="Concept éditorial, ligne directrice…" class="input-field resize-none">{{ old('description', $emission->description) }}</textarea>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Type d'émission <span class="text-error">*</span></label>
                        <select name="type" required class="input-field">
                            <option value="">Sélectionner un type</option>
                            @foreach($optionsTypes as $val=>$lab)
                                <option value="{{ $val }}" @selected(old('type',$emission->type?->value ?? $emission->type)===$val)>{{ $lab }}</option>
                            @endforeach
                        </select>
                        @error('type')<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Catégorie</label>
                        <select name="categorie" class="input-field">
                            @foreach(['Actualités','Politique','Économie','Sport','Culture & Arts','Société','International'] as $c)
                                <option value="{{ $c }}" @selected(old('categorie',$emission->categorie)===$c)>{{ $c }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Cible audience</label>
                        <select name="cible_audience" class="input-field">
                            @foreach(['Tout public','Grand public','Jeune public (15-25)','Senior (50+)','Professionnels / Décideurs'] as $c)
                                <option value="{{ $c }}" @selected(old('cible_audience',$emission->cible_audience)===$c)>{{ $c }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Langue(s)</label>
                        <select data-langue-select class="input-field mb-2" aria-label="Ajouter une langue de diffusion">
                            <option value="">Choisir une langue à ajouter…</option>
                            @foreach(\App\Enums\LangueEnum::cases() as $langue)<option value="{{ $langue->label() }}">{{ $langue->label() }}</option>@endforeach
                        </select>
                        <div class="flex flex-wrap gap-2 p-2 rounded-lg border border-slate-200 bg-white" data-langues-wrap>
                            @foreach($languesVals as $l)
                                @continue(trim($l)==='')
                                <span class="badge-blue text-xs px-2.5 py-1 rounded-md font-medium inline-flex items-center gap-1">{{ $l }}<button type="button" data-langue-remove class="opacity-60 hover:opacity-100" aria-label="Retirer"><i class="fa-solid fa-xmark text-xs"></i></button><input type="hidden" name="langues[]" value="{{ $l }}"></span>
                            @endforeach
                            <input type="text" data-langue-add placeholder="+ Ajouter une langue" class="flex-1 min-w-[120px] outline-none text-xs bg-transparent text-slate-600 placeholder-slate-400">
                        </div>
                        @error('langues')<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- STEP 2 : PRESENTATEURS -->
    <section data-panel="2" class="card-surface hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-primary-container text-primary flex items-center justify-center font-bold text-sm">2</div>
            <div>
                <h2 class="font-bold text-slate-900">Présentateurs & animateurs</h2>
                <p class="text-xs text-slate-500">Affectez les journalistes et définissez leur rôle</p>
            </div>
        </div>
        <div class="p-6 space-y-4">
            <div>
                <label for="emission-chef-edition" class="block text-sm font-semibold text-slate-700 mb-1.5">Chef d’édition responsable</label>
                <select id="emission-chef-edition" name="chef_edition_id" class="input-field">
                    <option value="">À affecter ultérieurement</option>
                    @foreach($chefsEdition as $chef)
                        <option value="{{ $chef->id }}" @selected((string) old('chef_edition_id', $emission->chef_edition_id) === (string) $chef->id)>{{ $chef->name }}{{ $chef->is_active ? '' : ' (compte inactif)' }}</option>
                    @endforeach
                </select>
                @error('chef_edition_id')<p class="mt-1 text-xs text-error">{{ $message }}</p>@enderror
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Ajouter un journaliste</label>
                    <select id="add-pres-jid" class="input-field">
                        <option value="">Sélectionner…</option>
                        @foreach($presentateursDispos as $j)
                            <option value="{{ $j->id }}">{{ $j->nom_complet }} — {{ $j->pole?->label() ?? '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Rôle</label>
                    <select id="add-pres-role" class="input-field">
                        @foreach($optionsRolesPresent as $v=>$l)
                            <option value="{{ $v }}" @selected($v===\App\Enums\RolePresentateurEmissionEnum::Principal->value)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Statut affectation</label>
                    <select id="add-pres-statut" class="input-field">
                        <option value="Confirme">Confirmé</option>
                        <option value="En_attente">En attente</option>
                        <option value="Refuse">Refusé</option>
                    </select>
                </div>
            </div>
            <button type="button" id="btn-add-presentateur" class="px-4 py-2 rounded-lg border border-dashed border-slate-300 text-slate-600 hover:border-primary hover:text-primary hover:bg-primary/5 text-sm font-medium w-full flex items-center justify-center gap-2">
                <i class="fa-solid fa-user-plus"></i> Ajouter à la liste des présentateurs
            </button>
            @error('presentateurs')<p class="text-error text-xs">{{ $message }}</p>@enderror

            <div class="rounded-xl border border-slate-200 overflow-hidden">
                <div class="table-header px-5 py-3 grid grid-cols-1 sm:grid-cols-12 gap-2 sm:gap-4 items-center">
                    <div class="sm:col-span-5">Journaliste</div>
                    <div class="sm:col-span-3">Rôle</div>
                    <div class="sm:col-span-3">Statut</div>
                    <div class="sm:col-span-1 text-right">Actions</div>
                </div>
                <div id="presentateurs-list" class="divide-y divide-slate-100" data-actual-count="{{ $presentateursActuels->count() }}">
                    @if($presentateursActuels->count()===0)
                        <div id="pres-empty" class="px-5 py-8 text-center text-slate-400 text-sm">Aucun présentateur affecté. Ajoutez-en depuis la liste ci-dessus.</div>
                    @endif
                    @foreach($presentateursActuels as $row)
                        @php
                            $j = $presentateursMeta->get((int) $row['journaliste_id']);
                            $role = is_string($row['role'] ?? null) ? $row['role'] : '';
                            $statut = is_string($row['statut_affectation'] ?? null) ? $row['statut_affectation'] : 'En_attente';
                            $ordre = is_numeric($row['ordre'] ?? null) ? $row['ordre'] : $loop->iteration;
                        @endphp
                        <div class="px-5 py-3.5 grid grid-cols-1 sm:grid-cols-12 gap-2 sm:gap-4 items-center hover:bg-slate-50" data-pres-row>
                            <div class="sm:col-span-5 flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full bg-gradient-to-br from-slate-500 to-slate-700 flex items-center justify-center text-white font-semibold text-xs">{{ $j->initiales }}</div>
                                <div>
                                    <div class="text-sm font-semibold text-slate-800">{{ $j->nom_complet }}</div>
                                    <div class="text-xs text-slate-500 truncate max-w-xs">{{ $j->email }}</div>
                                </div>
                                <input type="hidden" name="presentateurs[{{ $loop->index }}][journaliste_id]" value="{{ $j->id }}">
                                <input type="hidden" class="pres-ordre" name="presentateurs[{{ $loop->index }}][ordre]" value="{{ $ordre }}">
                            </div>
                            <div class="sm:col-span-3">
                                <select name="presentateurs[{{ $loop->index }}][role]" class="input-field !py-1.5 text-xs">
                                    @foreach($optionsRolesPresent as $v=>$l)
                                        <option value="{{ $v }}" @selected($role===$v)>{{ $l }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="sm:col-span-3">
                                <select name="presentateurs[{{ $loop->index }}][statut_affectation]" class="input-field !py-1.5 text-xs">
                                    <option value="Confirme" @selected($statut==='Confirme')>Confirmé</option>
                                    <option value="En_attente" @selected($statut==='En_attente')>En attente</option>
                                    <option value="Refuse" @selected($statut==='Refuse')>Refusé</option>
                                </select>
                            </div>
                            <div class="sm:col-span-1 text-right flex justify-end gap-1">
                                <button type="button" data-pres-remove class="w-11 h-11 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-error flex items-center justify-center"><i class="fa-solid fa-trash text-xs"></i></button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <!-- STEP 3 : PARAMETRES DIFFUSION -->
    <section data-panel="3" class="card-surface hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-primary-container text-primary flex items-center justify-center font-bold text-sm">3</div>
            <div>
                <h2 class="font-bold text-slate-900">Paramètres de diffusion</h2>
                <p class="text-xs text-slate-500">Horaires, jours et format technique</p>
            </div>
        </div>
        <div class="p-6 space-y-5">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Heure début <span class="text-error">*</span></label>
                    <input name="heure_debut" type="time" value="{{ old('heure_debut', $emission->heure_debut?->format('H:i') ?? '') }}" required class="input-field">
                    @error('heure_debut')<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Heure fin <span class="text-error">*</span></label>
                    <input name="heure_fin" type="time" value="{{ old('heure_fin', $emission->heure_fin?->format('H:i') ?? '') }}" required class="input-field">
                    @error('heure_fin')<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Durée cible (min) <span class="text-error">*</span></label>
                    <input name="duree_minutes" type="number" readonly aria-describedby="emission-duration-help" min="1" max="480" value="{{ old('duree_minutes', $emission->duree_minutes ?? '') }}" required class="input-field">
                    <p id="emission-duration-help" class="mt-1 text-xs text-slate-500">Calculée à partir des horaires, y compris après minuit. Maximum : 8 heures.</p>
                    @error('duree_minutes')<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Fuseau horaire</label>
                    <select name="fuseau_horaire" class="input-field">
                        @foreach(['Africa/Libreville'=>'UTC +01:00 · Libreville (WAT)','UTC'=>'UTC +00:00 · Dakar (GMT)','Europe/Paris'=>'UTC +01:00 · Paris (CET)','Africa/Cairo'=>'UTC +02:00 · Le Caire (EET)'] as $v=>$l)
                            <option value="{{ $v }}" @selected(old('fuseau_horaire',$emission->fuseau_horaire)===$v)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Jours de diffusion</label>
                <div class="flex flex-wrap gap-2">
                    @foreach($joursOptions as $key=>$lib)
                        <label class="cursor-pointer">
                            <input type="checkbox" name="jours_diffusion[]" value="{{ $key }}" class="peer sr-only" @checked(in_array($key,$joursVals))>
                            <span class="inline-flex items-center px-4 py-2 rounded-lg border-2 border-slate-200 text-sm font-medium text-slate-600 peer-checked:border-primary peer-checked:bg-primary peer-checked:text-white transition">{{ $lib }}</span>
                        </label>
                    @endforeach
                </div>
                @error('jours_diffusion')<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Date de lancement</label>
                    <input name="date_lancement" type="date" value="{{ old('date_lancement', $emission->date_lancement?->format('Y-m-d')) }}" class="input-field">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Date fin / Archivage</label>
                    <input name="date_fin" type="date" value="{{ old('date_fin', $emission->date_fin?->format('Y-m-d')) }}" class="input-field">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Format image</label>
                    <select name="format_image" class="input-field">
                        @foreach(['16:9'=>'16:9 · Widescreen standard','4:3'=>'4:3 · Classique','9:16'=>'9:16 · Portrait mobile'] as $v=>$l)
                            <option value="{{ $v }}" @selected(old('format_image',$emission->format_image)===$v)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Qualité</label>
                    <select name="qualite" class="input-field">
                        @foreach(['HD 1080p','4K UHD','SD 720p'] as $q)
                            <option value="{{ $q }}" @selected(old('qualite',$emission->qualite)===$q)>{{ $q }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Statut</label>
                    <select name="statut" class="input-field">
                        @foreach($optionsStatuts as $v=>$l)
                            <option value="{{ $v }}" @selected(old('statut',($emission->statut instanceof \App\Enums\StatutEmissionEnum ? $emission->statut->value : $emission->statut))===$v)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Police des titres</label>
                    <select name="police_titres" class="input-field">
                        @foreach(['Inter'=>'Inter — Sans-serif moderne','Montserrat'=>'Montserrat — Géométrique','Roboto'=>'Roboto — Classique','Oswald'=>'Oswald — Condensé impact','Playfair Display'=>'Playfair Display — Sérif'] as $v=>$l)
                            <option value="{{ $v }}" @selected(old('police_titres',$emission->police_titres)===$v)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="flex items-center gap-6 pt-2">

                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="hidden" name="is_active" value="0">
                    <input name="is_active" type="checkbox" class="peer sr-only" @checked(old('is_active',$emission->is_active??true)) value="1">
                    <span class="w-11 h-6 rounded-full bg-slate-300 transition peer-checked:bg-emerald-500 relative after:absolute after:w-5 after:h-5 after:bg-white after:rounded-full after:left-0.5 after:top-0.5 after:transition peer-checked:after:translate-x-5"></span>
                    <span class="text-sm font-medium text-slate-700">Active</span>
                </label>
            </div>
        </div>
    </section>

    <!-- STEP 4 : RESUME -->
    <section data-panel="4" class="card-surface hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-primary-container text-primary flex items-center justify-center font-bold text-sm">4</div>
            <div>
                <h2 class="font-bold text-slate-900">Résumé & validation</h2>
                <p class="text-xs text-slate-500">Vérifiez les informations avant de valider l'émission</p>
            </div>
        </div>
        <div class="p-6">
            <div class="bg-gradient-to-br from-primary/5 to-white border border-primary/10 rounded-xl p-5 flex items-start gap-4">
                @php
                    $coul = old('couleur_habillage',$emission->couleur_habillage ?? '#0054cdFF');
                    $coul7 = Str::limit($coul,7,'');
                    $ini = Str::upper(mb_substr(preg_replace('/[^A-Za-z0-9]/','',old('nom',$emission->nom??'EM')),0,2));
                    $jDiff = old('jours_diffusion',$emission->jours_diffusion ?? []);
                    $joursJolis = collect($jDiff)->map(fn($k)=>$joursOptions[$k]??$k)->join(' · ');
                @endphp
                <div class="w-16 h-16 rounded-xl flex items-center justify-center text-white text-3xl font-black shadow-inner shrink-0" style="background:linear-gradient(135deg,{{ $coul7 }},{{ $coul7 }}dd);">
                    {{ $ini }}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2 mb-1">
                        @if(old('type',$emission->type?->value ?? ''))
                            {!! \App\Enums\TypeEmissionEnum::tryFrom(old('type',$emission->type?->value ?? ''))?->badge() ?? '' !!}
                        @endif
                        @if(old('statut',$emission->statut?->value ?? ''))
                            {!! \App\Enums\StatutEmissionEnum::tryFrom(old('statut',$emission->statut?->value ?? ''))?->badge() ?? '' !!}
                        @endif
                        <h3 class="font-bold text-slate-900 text-lg" data-resume-nom>{{ old('nom',$emission->nom) }}</h3>
                    </div>
                    <p class="text-sm text-slate-600 mb-3" data-resume-sub>
                        <span data-resume-subtitle>{{ old('sous_titre',$emission->sous_titre) }}</span> · <span data-resume-duree>{{ old('duree_minutes',$emission->duree_minutes ?? '') }} min</span> · <span data-resume-jours>{{ $joursJolis }}</span> · <span data-resume-pres-count>{{ $presentateursActuels->count() }} présentateur(s)</span>
                    </p>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs">
                        <div class="bg-white rounded-lg border border-slate-200 p-2.5"><div class="text-slate-400 text-xs sm:text-xs uppercase tracking-wider">Horaire</div><div class="font-semibold text-slate-800 mt-0.5" data-resume-horaire>{{ old('heure_debut',$emission->heure_debut?->format('H:i') ?? '') }} → {{ old('heure_fin',$emission->heure_fin?->format('H:i') ?? '') }}</div></div>
                        <div class="bg-white rounded-lg border border-slate-200 p-2.5"><div class="text-slate-400 text-xs sm:text-xs uppercase tracking-wider">Jours</div><div class="font-semibold text-slate-800 mt-0.5">{{ Str::limit($joursJolis, 20, '…') }}</div></div>
                        <div class="bg-white rounded-lg border border-slate-200 p-2.5"><div class="text-slate-400 text-xs sm:text-xs uppercase tracking-wider">Format</div><div class="font-semibold text-slate-800 mt-0.5">{{ old('format_image',$emission->format_image ?? '16:9') }} {{ old('qualite',$emission->qualite ?? 'HD 1080p') }}</div></div>
                        <div class="bg-white rounded-lg border border-slate-200 p-2.5"><div class="text-slate-400 text-xs sm:text-xs uppercase tracking-wider">Couleur</div><div class="font-semibold text-slate-800 mt-0.5 flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full inline-block" style="background:{{ $coul7 }}"></span>{{ $coul7 }}</div></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- NAV BUTTONS -->
    <div class="sticky bottom-0 -mx-4 sm:-mx-6 lg:-mx-8 -mb-4 sm:-mb-6 lg:-mb-8 px-4 sm:px-6 lg:px-8 py-5 bg-white border-t border-slate-200 flex items-center justify-between gap-3 z-10">
        <button type="button" data-prev class="btn-ghost disabled:opacity-40 disabled:pointer-events-none flex items-center gap-2" disabled><i class="fa-solid fa-arrow-left"></i> Précédent</button>
        <div class="flex items-center gap-3 flex-wrap justify-end">
            <a href="{{ route('emissions.index') }}" class="btn-outline text-error border-error/20 hover:bg-error/5">Annuler</a>
            <button type="button" data-next class="btn-primary flex items-center gap-2">Suivant <i class="fa-solid fa-arrow-right"></i></button>
            <button type="submit" data-submit class="btn-success hidden items-center gap-2"><i class="fa-solid fa-check"></i> {{ $emission->exists ? 'Enregistrer les modifications' : 'Créer l\'émission' }}</button>
        </div>
    </div>
</form>

