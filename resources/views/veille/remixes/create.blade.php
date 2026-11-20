@extends('layouts.app')
@php
$pageTitle = $remix ? 'Modifier un remix IA' : 'Remixer IA un sujet de veille';
$pageSubtitle = $subject->title;
$showGlobalSearch = false;
$backRoute = route('veille.sujets.show', $subject);
$headerActions = [
    ['label'=>'Retour au sujet','icon'=>'fa-solid fa-arrow-left','href'=>$backRoute,'variant'=>'outlined','attrs'=>''],
    ['label'=>'💾 Sauvegarder','icon'=>'fa-solid fa-floppy-disk','href'=>'#','variant'=>'secondary','attrs'=>'id="btn-save-draft" title="Sauvegarder le brouillon"'],
    ['label'=>'✅ Valider et publier','icon'=>'fa-solid fa-circle-check','href'=>'#','variant'=>'primary','attrs'=>'id="btn-validate" title="Passer en statut Validé"'],
];
@endphp
@section('content')
<div class="space-y-6" x-data="veilleRemixEditor()">

<div class="rounded-2xl bg-surface-container-low border border-outline-variant p-5 md:p-6">
    <div class="flex flex-col lg:flex-row gap-5 lg:gap-6">

        <div class="w-full lg:w-2/3 space-y-5">
            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-primary-container px-3 py-1 text-xs font-bold tracking-widest text-on-primary-container uppercase">
                    <i class="fa-solid fa-wand-magic-sparkles"></i> Remix IA
                </span>
                <span id="remix-status-badge" class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold tracking-wider uppercase
                    {{ $remix ? (match($remix->status){ 'validated'=>'bg-tertiary-container text-on-tertiary-container', 'published'=>'bg-secondary-container text-on-secondary-container', default=>'bg-surface-container-highest text-on-surface-variant'}) : 'bg-surface-container-highest text-on-surface-variant' }}">
                    <i class="fa-regular fa-file-lines"></i>
                    <span id="remix-status-text">{{ $remix ? (match($remix->status){ 'validated'=>'Validé', 'published'=>'Publié', default=>'Brouillon'}) : 'Brouillon non sauvegardé' }}</span>
                </span>
                <span id="last-saved" class="sm:ml-auto text-xs text-on-surface-variant font-medium">
                    @if($remix) Dernière sauvegarde {{ $remix->updated_at->setTimezone('Africa/Libreville')->format('d/m H:i') }} @endif
                </span>
            </div>

            <div>
                <label class="block text-xs font-bold tracking-widest uppercase text-on-surface-variant mb-1.5">Titre du remix</label>
                <input id="remix-title" type="text" required minlength="2" maxlength="255"
                    value="{{ $remix ? e($remix->title) : e($subject->title) . ' — Remix ' . ($types[$type]['label'] ?? 'journalistique') }}"
                    class="w-full rounded-xl border border-outline bg-surface-container-lowest px-4 py-3 text-lg font-semibold text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/60">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-bold tracking-widest uppercase text-on-surface-variant mb-1.5">Type de remix</label>
                    <select id="remix-type" class="w-full rounded-xl border border-outline bg-surface-container-lowest px-3 py-2.5 text-sm font-medium text-on-surface">
                        @foreach($types as $key=>$def)
                        <option value="{{ $key }}" @selected(($remix?->type ?? $type) === $key)>{{ $def['label'] }} · {{ $def['words'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold tracking-widest uppercase text-on-surface-variant mb-1.5">Ton / Registre</label>
                    <select id="remix-tone" class="w-full rounded-xl border border-outline bg-surface-container-lowest px-3 py-2.5 text-sm font-medium text-on-surface">
                        @foreach($tones as $key=>$label)
                        <option value="{{ $key }}" @selected(($remix?->tone ?? 'journaliste') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold tracking-widest uppercase text-on-surface-variant mb-1.5">Langue</label>
                    <select id="remix-lang" class="w-full rounded-xl border border-outline bg-surface-container-lowest px-3 py-2.5 text-sm font-medium text-on-surface">
                        <option value="fr" @selected(($remix?->language ?? 'fr') === 'fr')>🇫🇷 Français</option>
                        <option value="en" @selected(($remix?->language ?? 'fr') === 'en')>🇬🇧 English</option>
                    </select>
                </div>
            </div>

            <div class="rounded-2xl bg-secondary-container/40 border border-secondary-container p-4 space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs font-bold tracking-widest uppercase text-on-surface-variant">
                        <i class="fa-solid fa-bolt mr-2"></i> Barre d'actions IA
                    </div>
                    <div id="ai-status" class="text-xs text-on-surface-variant font-medium">
                        <i class="fa-regular fa-circle-check mr-1.5"></i> Groq llama-3.1 70B · prêt
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    @foreach($aiActions as $key=>$act)
                    <button type="button"
                        data-ai-action="{{ $key }}"
                        class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold border transition
                        {{ $act['primary'] ?? false
                            ? 'bg-primary text-on-primary border-primary shadow-sm hover:bg-primary/90'
                            : 'bg-surface-container-low border-outline text-on-surface hover:bg-surface-container' }}">
                        <i class="{{ $act['icon'] }}"></i> {{ $act['label'] }}
                    </button>
                    @endforeach
                </div>

                <div id="stream-progress" class="hidden items-center gap-2 p-3 rounded-xl bg-tertiary-container/60 border border-tertiary-container">
                    <i class="fa-solid fa-circle-notch fa-spin text-tertiary"></i>
                    <span class="text-sm text-on-tertiary-container font-medium">Génération IA en cours… (écriture en direct)</span>
                    <span class="ml-auto text-xs font-mono text-on-tertiary-container/80" id="stream-count">0 mots</span>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold tracking-widest uppercase text-on-surface-variant mb-1.5">
                    Contenu · Éditeur WYSIWYG
                </label>
                <div id="tiptap-editor" class="min-h-[420px] max-h-[650px] overflow-y-auto rounded-2xl border border-outline bg-surface-container-lowest p-4 focus-within:ring-2 focus-within:ring-primary/40">
                    {{ $remix ? $remix->content : '' }}
                </div>
            </div>

            <div class="rounded-xl bg-surface-container-highest border border-outline-variant p-4">
                <div class="flex items-center justify-between gap-3 mb-2.5">
                    <div class="text-xs font-bold tracking-widest uppercase text-on-surface-variant">
                        <i class="fa-regular fa-clock-rotate-left mr-2"></i> Versions générées (historique)
                    </div>
                    <span id="versions-count" class="text-xs font-medium text-on-surface-variant">
                        {{ $remix && isset($remix->metadata['versions']) ? count($remix->metadata['versions']) : 0 }} version(s)
                    </span>
                </div>
                <div id="versions-list" class="flex flex-wrap gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-surface-container px-3 py-1.5 text-xs text-on-surface-variant italic">
                        Aucune version sauvegardée
                    </span>
                </div>
            </div>
        </div>

        <aside class="w-full lg:w-1/3 space-y-5">
            <div class="rounded-2xl bg-tertiary-container/40 border border-tertiary-container p-5">
                <h3 class="font-bold text-on-surface mb-2 text-sm tracking-widest uppercase">
                    <i class="fa-regular fa-newspaper mr-2"></i> Document source
                </h3>
                <p class="text-sm font-semibold text-on-tertiary-container mb-3 leading-snug">{{ $subject->title }}</p>
                @if($subject->summary)
                <div class="max-h-40 overflow-y-auto rounded-lg bg-tertiary-container/50 p-3 text-sm text-on-tertiary-container leading-6">
                    {{ $subject->summary }}
                </div>
                @endif
                <div class="mt-4 pt-4 border-t border-outline-variant/40 text-xs text-on-surface-variant space-y-1.5">
                    @if($subject->source)
                    <div><span class="font-bold uppercase tracking-wider">Source :</span> {{ $subject->source->name ?? '—' }}</div>
                    @endif
                    @if($subject->category)
                    <div><span class="font-bold uppercase tracking-wider">Rubrique :</span> {{ $subject->category->label() }}</div>
                    @endif
                    @if($subject->published_at)
                    <div><span class="font-bold uppercase tracking-wider">Publié le :</span> {{ $subject->published_at->format('d/m/Y H:i') }}</div>
                    @endif
                    @if($subject->url)
                    <div class="pt-2">
                        <a href="{{ $subject->url }}" target="_blank" rel="noopener"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-tertiary px-3 py-1.5 text-on-tertiary font-semibold text-xs hover:bg-tertiary/80 transition">
                            <i class="fa-solid fa-arrow-up-right-from-square"></i> Lire l'article original
                        </a>
                    </div>
                    @endif
                </div>
            </div>

            <div class="rounded-2xl bg-surface-container-highest border border-outline-variant p-5">
                <h3 class="font-bold text-on-surface mb-3 text-sm tracking-widest uppercase">
                    <i class="fa-solid fa-book mr-2"></i> Conseils de rédaction
                </h3>
                <ul class="text-xs text-on-surface-variant space-y-2 leading-5">
                    <li class="flex gap-2"><i class="fa-solid fa-check text-primary mt-0.5"></i> <span>Clique sur <b>Générer</b> pour un premier jet basé sur le document source.</span></li>
                    <li class="flex gap-2"><i class="fa-solid fa-check text-primary mt-0.5"></i> <span><b>Développer</b> ajoute 2x plus de contexte factuel.</span></li>
                    <li class="flex gap-2"><i class="fa-solid fa-check text-primary mt-0.5"></i> <span><b>Changer ton</b> = Scoop TV · Formel · Documentaire…</span></li>
                    <li class="flex gap-2"><i class="fa-solid fa-check text-primary mt-0.5"></i> <span>Sauvegarde automatique toutes les 10s.</span></li>
                    <li class="flex gap-2"><i class="fa-solid fa-check text-primary mt-0.5"></i> <span>Valider pour ajouter ce remix au sujet.</span></li>
                </ul>
            </div>
        </aside>

    </div>
</div>

<input type="hidden" id="subject-id" value="{{ $subject->id }}">
<input type="hidden" id="remix-id"  value="{{ $remix?->id }}">
<input type="hidden" id="save-token" value="{{ csrf_token() }}">
<input type="hidden" id="ai-stream-url" value="{{ route('veille.remixes.ai_stream') }}">
<input type="hidden" id="store-url"      value="{{ route('veille.remixes.store') }}">
<input type="hidden" id="update-url"     value="{{ $remix ? route('veille.remixes.update', $remix) : '' }}">
<input type="hidden" id="validate-url"   value="{{ $remix ? route('veille.remixes.validate', $remix) : '' }}">
<input type="hidden" id="redirect-back-url" value="{{ route('veille.sujets.show', $subject) }}">
<input type="hidden" id="app-url"           value="{{ rtrim(url('/'), '/') }}">
</div>
@endsection
