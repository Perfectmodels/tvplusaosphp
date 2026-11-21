@extends('layouts.app')
@php
$pageTitle    = $subject->title;
$pageSubtitle = $subject->source?->name . ($subject->category ? ' · '.$subject->category->label() : '');
$showGlobalSearch = false;

$promoteHref = route('veille.sujets.promote', $subject);
$archiveHref = route('veille.sujets.archive', $subject);
$remixHref   = route('veille.remixes.create', ['subject' => $subject]);

$remixTypes   = \App\Http\Controllers\VeilleRemixController::$TYPES;
$remixStatusMap = [
    'draft'     => ['label' => 'Brouillon', 'cls' => 'bg-surface-variant text-on-surface-variant'],
    'validated' => ['label' => 'Validé',    'cls' => 'bg-tertiary-container text-on-tertiary-container'],
    'published' => ['label' => 'Publié',    'cls' => 'bg-secondary-container text-on-secondary-container'],
];

@endphp
@section('content')
<section class="space-y-8">
    <div class="bg-surface-container-lowest border border-outline-variant rounded-2xl shadow-[0_10px_30px_-10px_rgba(0,26,65,0.04)] overflow-hidden">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-0">
            <div class="lg:col-span-2 bg-surface-container-lowest p-8 md:p-10 border-b lg:border-b-0 lg:border-r border-outline-variant">
                <div class="flex flex-wrap gap-3 mb-6 items-center">
                    <x-veille-status-badge :status="$subject->status" />
                    @if($subject->evenementsEditoriaux->isNotEmpty())
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-primary-fixed text-on-primary-fixed text-xs tracking-wider font-bold uppercase">
                        <i class="fa-solid fa-link mr-1"></i> LIÉ À UN ÉVÉNEMENT ÉDITORIAL
                    </span>
                    @endif
                    @if($subject->published_at)
                    <span class="inline-flex items-center gap-1.5 text-xs text-on-surface-variant">
                        <i class="fa-regular fa-calendar mr-1.5"></i>
                        Publié {{ $subject->published_at->format('d/m/Y H:i') }}
                    </span>
                    @endif
                    <span class="inline-flex items-center gap-1.5 text-xs text-on-surface-variant">
                        <i class="fa-regular fa-clock mr-1.5"></i>
                        Scrapé {{ $subject->scraped_at?->diffForHumans() ?? 'jamais' }}
                    </span>
                </div>

                @if($subject->image_url)
                <div class="mb-8 rounded-2xl overflow-hidden border border-outline-variant shadow-sm">
                    <img src="{{ $subject->image_url }}" alt="" class="w-full h-64 md:h-80 object-cover" onerror="this.parentElement.classList.add('hidden')">
                </div>
                @endif

                <div class="space-y-6">
                    <h1 class="text-[32px] font-bold leading-10 tracking-[-0.02em] text-on-background">
                        {{ $subject->title }}
                    </h1>

                    @if($subject->summary)
                    <div class="p-5 rounded-xl bg-surface-container-low border border-outline-variant border-l-4 border-l-secondary-container">
                        <p class="text-[16px] text-on-surface-variant italic leading-7">{{ $subject->summary }}</p>
                    </div>
                    @endif

                    <div class="mobile-wrap-tabs flex border-b border-surface-variant gap-3 sm:gap-8 overflow-x-auto" role="tablist" aria-label="Rubriques du sujet de veille">
                        <button class="tab-btn px-1 py-4 text-sm font-semibold tracking-[0.05em] border-b-2 border-primary text-primary whitespace-nowrap" data-tab-target="tab-contenu" id="veille-tab-contenu" role="tab" aria-controls="tab-contenu" aria-selected="true" tabindex="0">
                            Contenu
                        </button>
                        <button class="tab-btn px-1 py-4 text-sm font-semibold tracking-[0.05em] text-on-surface-variant border-b-2 border-transparent hover:text-on-surface whitespace-nowrap" data-tab-target="tab-historique" id="veille-tab-historique" role="tab" aria-controls="tab-historique" aria-selected="false" tabindex="-1">
                            Historique
                        </button>
                        <button class="tab-btn px-1 py-4 text-sm font-semibold tracking-[0.05em] text-on-surface-variant border-b-2 border-transparent hover:text-on-surface whitespace-nowrap" data-tab-target="tab-remixes" id="veille-tab-remixes" role="tab" aria-controls="tab-remixes" aria-selected="false" tabindex="-1">
                            Remixes IA <span class="ml-2 px-2 py-0.5 rounded-full bg-secondary-fixed text-on-secondary-fixed text-xs font-bold">{{ $subject->remixes->count() }}</span>
                        </button>
                    </div>

                    <div id="tab-contenu" class="tab-panel pt-4" role="tabpanel" aria-labelledby="veille-tab-contenu" tabindex="0">
                        @include('_partials.veille-gabon-analysis')
                        @if($subject->content)
                        <article class="prose prose-slate max-w-none text-[15px] leading-7 text-on-surface whitespace-pre-wrap break-words">
                            {!! nl2br(e($subject->content)) !!}
                        </article>
                        @elseif($subject->summary)
                        <div class="space-y-5">
                            <div class="p-5 rounded-xl bg-surface-container-low border border-outline-variant border-l-4 border-l-secondary-container">
                                <div class="text-xs font-bold tracking-[0.15em] uppercase text-on-surface-variant mb-3">
                                    <i class="fa-regular fa-newspaper mr-2"></i> Extrait source (contenu article indisponible)
                                </div>
                                <p class="text-[16px] text-on-surface leading-8">{{ $subject->summary }}</p>
                            </div>
                            @if($subject->url)
                            <a href="{{ $subject->url }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 bg-primary-container/50 text-on-primary-container px-5 py-3 rounded-xl font-semibold text-sm tracking-[0.05em] hover:bg-primary-container transition">
                                <i class="fa-solid fa-arrow-up-right-from-square"></i> Lire l'article complet sur le site source
                            </a>
                            @endif
                        </div>
                        @else
                        <div class="rounded-xl border border-outline-variant border-dashed p-10 text-center">
                            <i class="fa-regular fa-file-lines text-5xl text-outline opacity-50 mb-4"></i>
                            <h4 class="font-semibold text-on-surface mb-2">Aucun contenu détaillé</h4>
                            <p class="text-on-surface-variant text-sm">Ce sujet a été importé sans contenu article détaillé.</p>
                        </div>
                        @endif
                    </div>

                    <div id="tab-historique" class="tab-panel pt-4 hidden" role="tabpanel" aria-labelledby="veille-tab-historique" tabindex="0" hidden>
                        <div class="space-y-3">
                            <div class="flex gap-4">
                                <div class="flex flex-col items-center">
                                    <div class="w-3 h-3 rounded-full bg-secondary-container mt-1.5"></div>
                                    <div class="flex-1 w-px bg-surface-variant my-1"></div>
                                </div>
                                <div class="flex-1 pb-4">
                                    <div class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant mb-1">Sujet créé</div>
                                    <div class="text-sm text-on-surface">Importé depuis {{ $subject->source?->name ?? 'source inconnue' }} le {{ $subject->created_at->format('d/m/Y H:i') }}.</div>
                                </div>
                            </div>
                            @if($subject->scraped_at)
                            <div class="flex gap-4">
                                <div class="flex flex-col items-center">
                                    <div class="w-3 h-3 rounded-full bg-primary-container mt-1.5"></div>
                                    <div class="flex-1 w-px bg-surface-variant my-1"></div>
                                </div>
                                <div class="flex-1 pb-4">
                                    <div class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant mb-1">Dernier scraping</div>
                                    <div class="text-sm text-on-surface">{{ $subject->scraped_at->format('d/m/Y H:i:s') }}.</div>
                                </div>
                            </div>
                            @endif
                            @foreach($subject->evenementsEditoriaux as $event)
                            <div class="flex gap-4">
                                <div class="flex flex-col items-center">
                                    <div class="w-3 h-3 rounded-full bg-primary-container mt-1.5"></div>
                                </div>
                                <div class="flex-1">
                                    <div class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant mb-1">Événement éditorial ouvert</div>
                                    <a class="text-sm text-primary-container hover:underline" href="{{ route('evenements-editoriaux.show', $event) }}">{{ $event->title }} · {{ str_replace('_', ' ', $event->status->value) }}</a>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    <div id="tab-remixes" class="tab-panel pt-4 hidden" role="tabpanel" aria-labelledby="veille-tab-remixes" tabindex="0" hidden>
                        @if($subject->remixes->count())
@if($subject->remixes->contains(fn ($item) => auth()->user()->can('delete', $item)))
@include('_partials.bulk-delete-toolbar', ['resource' => 'remixes-veille', 'parentId' => $subject->id])
@endif

                        <div class="space-y-3" data-bulk-list="bulk-remixes-veille">
                            @foreach($subject->remixes as $r)
                            @php
                                $typeMeta = $remixTypes[$r->type] ?? ['label' => ucfirst($r->type), 'words' => ''];
                                $statusMeta = $remixStatusMap[$r->status] ?? ['label'=>'Inconnu','cls'=>'bg-surface-variant text-on-surface-variant'];
                            @endphp
                            <div class="p-4 rounded-xl border border-outline-variant bg-surface-container-lowest flex gap-4">
@include('_partials.bulk-delete-checkbox', ['resource' => 'remixes-veille', 'record' => $r, 'label' => $r->title ?? 'Remix'])
                                <span class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-secondary-fixed text-on-secondary-fixed flex-shrink-0">
                                    <i class="fa-solid fa-wand-magic-sparkles text-lg"></i>
                                </span>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-3 mb-1 flex-wrap">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="inline-flex items-center gap-1.5 text-xs font-bold tracking-wider uppercase px-2.5 py-1 rounded-full bg-secondary-fixed/50 text-on-secondary-fixed-variant">
                                                <i class="fa-regular fa-file-lines"></i> {{ $typeMeta['label'] }}
                                            </span>
                                            <span class="inline-flex items-center gap-1.5 text-xs font-bold tracking-wider uppercase px-2.5 py-1 rounded-full {{ $statusMeta['cls'] }}">
                                                <i class="fa-{{ $r->status === 'draft' ? 'regular fa-clock' : ($r->status === 'validated' ? 'solid fa-check' : 'solid fa-circle-check') }}"></i> {{ $statusMeta['label'] }}
                                            </span>
                                            @if(!empty($r->tone))
                                            <span class="inline-flex items-center gap-1 text-xs text-on-surface-variant uppercase tracking-wider font-semibold">
                                                <i class="fa-solid fa-palette text-xs"></i> Ton {{ str_replace('_', ' ', $r->tone) }}
                                            </span>
                                            @endif
                                        </div>
                                        <span class="text-xs text-on-surface-variant">
                                            @if($r->user) <i class="fa-solid fa-user mr-1"></i> {{ $r->user->prenom ? $r->user->prenom[0].'. ' : '' }}{{ $r->user->name ?? '—' }} · @endif
                                            <i class="fa-regular fa-calendar mr-1"></i>{{ $r->created_at->format('d/m H:i') }}
                                        </span>
                                    </div>
                                    <h4 class="font-semibold text-on-surface mb-2 line-clamp-1">{{ $r->title ?? 'Sans titre' }}</h4>
                                    @if($r->content)
                                    <p class="text-sm text-on-surface-variant line-clamp-3 leading-6 mb-3">{{ Str::limit(strip_tags($r->content), 320) }}</p>
                                    @endif
                                    <div class="flex flex-wrap items-center gap-2 mt-2">
                                        @can('update', $r)
                                        <a href="{{ route('veille.remixes.edit', $r) }}" class="inline-flex items-center gap-1.5 rounded-lg bg-primary/10 text-primary hover:bg-primary/15 px-3 py-1.5 text-xs font-semibold transition">
                                            <i class="fa-solid fa-pencil text-xs"></i> Éditer ce remix
                                        </a>
                                        @endcan
                                        @if($r->status === 'draft' && $r->ai_model)
                                        <span class="inline-flex items-center gap-1 text-xs text-on-surface-variant font-mono">
                                            <i class="fa-solid fa-microchip"></i> {{ Str::limit($r->ai_model, 40) }}
                                        </span>
                                        @endif
                                        @can('delete', $r)
                                        <form method="POST" action="{{ route('veille.remixes.destroy', $r) }}" class="inline"
                                              data-confirm-delete="Supprimer définitivement ce remix ?"
                                              data-confirm-delete-text="Cette action est irréversible.">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg text-error-container on-error-container hover:bg-error-container/30 px-3 py-1.5 text-xs font-semibold transition">
                                                <i class="fa-solid fa-trash-can text-xs"></i> Supprimer
                                            </button>
                                        </form>
                                        @endcan
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                        @else
                        <div class="rounded-xl border border-outline-variant border-dashed p-10 text-center">
                            <i class="fa-solid fa-wand-magic-sparkles text-5xl text-secondary-container opacity-70 mb-4"></i>
                            <h4 class="font-semibold text-on-surface mb-2">Aucun remix IA généré</h4>
                            <p class="text-on-surface-variant text-sm mb-6">Utilisez l’Assistant IA pour générer titres, angles, scripts et contenus sociaux à partir de ce sujet.</p>
                            @can('create', \App\Models\VeilleRemix::class)
                            <a href="{{ $remixHref }}" class="inline-flex items-center gap-2 bg-primary-container text-on-primary-container px-5 py-3 rounded-xl font-semibold text-sm tracking-[0.05em] hover:opacity-90 transition">
                                <i class="fa-solid fa-wand-magic-sparkles"></i> Ouvrir l’éditeur IA
                            </a>
                            @endcan
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="lg:col-span-1 p-8 md:p-10 bg-surface-container-low/40 space-y-8">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold tracking-[0.1em] uppercase text-on-surface-variant">Métadonnées</h3>
                    @can('update', $subject)
                    <button data-modal-open="editSubjectModal" class="text-xs font-semibold text-primary hover:underline inline-flex items-center gap-1.5">
                        <i class="fa-solid fa-pencil text-xs"></i> Modifier
                    </button>
                    @endcan
                </div>

                <div class="space-y-5">
                    <div>
                        <div class="text-xs font-semibold tracking-wider uppercase text-outline mb-2">Source</div>
                        <div class="text-[15px] text-on-surface font-medium">
                            {{ $subject->source?->name ?? 'Source directe (scraping URL)' }}
                            @if($subject->source?->type)
                            <span class="ml-2 inline-flex text-xs px-2 py-0.5 rounded-full bg-surface-variant text-on-surface-variant font-bold tracking-wider uppercase">{{ $subject->source->type }}</span>
                            @endif
                        </div>
                        @if($subject->url)
                        <a href="{{ $subject->url }}" target="_blank" rel="noopener" class="text-sm text-primary hover:underline mt-1.5 inline-flex items-center gap-1.5">
                            <i class="fa-solid fa-arrow-up-right-from-square text-xs"></i> Ouvrir l'original
                        </a>
                        @endif
                    </div>

                    <div>
                        <div class="text-xs font-semibold tracking-wider uppercase text-outline mb-2">Catégorie</div>
                        <div class="inline-flex px-3 py-1 rounded-full border text-sm font-semibold {{ $subject->category?->badgeClass() ?? 'bg-surface-variant text-on-surface-variant border-outline-variant' }}">
                            {{ $subject->category?->label() ?? 'Non catégorisé' }}
                        </div>
                    </div>

                    <div>
                        <div class="text-xs font-semibold tracking-wider uppercase text-outline mb-2">Priorité</div>
                        <div class="flex items-center gap-1.5">
                            @for($i=1;$i<=10;$i++)
                                <span class="inline-block w-2.5 h-6 rounded-sm {{ $i <= ($subject->priority ?? 5) ? 'bg-primary' : 'bg-surface-variant' }}"></span>
                            @endfor
                            <span class="ml-2 text-sm text-on-surface-variant font-medium">P{{ $subject->priority ?? 5 }}/10</span>
                        </div>
                    </div>

                    <div>
                        <div class="text-xs font-semibold tracking-wider uppercase text-outline mb-2">Journaliste assigné</div>
                        <div class="text-[15px] text-on-surface">
                            @if($subject->journaliste)
                            <span class="inline-flex items-center gap-2">
                                <span class="w-8 h-8 rounded-full bg-primary-fixed text-on-primary-fixed inline-flex items-center justify-center font-bold text-sm">{{ strtoupper(substr($subject->journaliste->prenom,0,1).substr($subject->journaliste->nom,0,1)) }}</span>
                                {{ $subject->journaliste->prenom }} {{ $subject->journaliste->nom }}
                            </span>
                            @else
                            <span class="text-on-surface-variant italic">Non assigné</span>
                            @endif
                        </div>
                    </div>

                    <div>
                        <div class="text-xs font-semibold tracking-wider uppercase text-outline mb-2">Événement éditorial</div>
                        <div class="text-[15px] text-on-surface">
                            @forelse($subject->evenementsEditoriaux as $event)
                                <a class="block text-primary-container hover:underline" href="{{ route('evenements-editoriaux.show', $event) }}">{{ $event->title }} · {{ str_replace('_', ' ', $event->status->value) }}</a>
                            @empty
                                <span class="text-on-surface-variant italic">Aucun événement lié</span>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="h-px bg-surface-variant my-2"></div>

                <div class="space-y-3">
                    @if($subject->evenementsEditoriaux->isNotEmpty())
                        <a href="{{ route('evenements-editoriaux.show', $subject->evenementsEditoriaux->first()) }}" class="w-full inline-flex items-center justify-center gap-2 bg-primary-container text-on-primary-container px-5 py-3.5 rounded-xl font-semibold text-sm tracking-[0.05em] hover:opacity-90 active:scale-95 transition">
                            <i class="fa-solid fa-diagram-project"></i> Ouvrir le workflow éditorial
                        </a>
                    @elseif(auth()->user()->hasAnyRole(['admin', 'directeur_general', 'chef_edition', 'chef_redaction', 'journaliste']))
                        <form method="POST" action="{{ $promoteHref }}"
                              data-confirm="Créer un événement éditorial à partir de cet élément de veille ?"
                              data-confirm-text="L’événement passera en vérification. Il ne sera pas ajouté directement au conducteur.">
                            @csrf
                            <button type="submit" class="w-full inline-flex items-center justify-center gap-2 bg-primary-container text-on-primary-container px-5 py-3.5 rounded-xl font-semibold text-sm tracking-[0.05em] hover:opacity-90 active:scale-95 transition">
                                <i class="fa-solid fa-arrow-right"></i> Créer un événement éditorial
                            </button>
                        </form>
                    @endif

                    @can('update', $subject)
                    <a href="{{ $remixHref }}" class="w-full inline-flex items-center justify-center gap-2 bg-secondary-container text-on-secondary-container px-5 py-3.5 rounded-xl font-semibold text-sm tracking-[0.05em] hover:opacity-90 active:scale-95 transition">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> Remixer IA
                    </a>

                    <form method="POST" action="{{ $archiveHref }}"
                          data-confirm="Archiver ce sujet ?"
                          data-confirm-text="Il disparaîtra du tableau de bord principal.">
                        @csrf
                        <button type="submit" class="w-full inline-flex items-center justify-center gap-2 border border-outline-variant text-on-surface-variant px-5 py-3.5 rounded-xl font-semibold text-sm tracking-[0.05em] hover:text-on-surface hover:border-primary transition">
                            <i class="fa-solid fa-archive"></i> Archiver
                    </form>

                    @endcan
                </div>
            </div>
        </div>
    </div>

    @can('update', $subject)
    <div id="editSubjectModal" data-modal-backdrop role="dialog" aria-modal="true" aria-labelledby="edit-veille-title" tabindex="-1" class="fixed inset-0 z-[70] hidden focus:outline-none">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" data-modal-close></div>
        <div class="relative z-10 flex items-start sm:items-center justify-center min-h-screen p-4 overflow-y-auto">
            <form method="POST" action="{{ route('veille.sujets.update', $subject) }}" class="w-full max-w-2xl bg-surface-container-lowest rounded-2xl shadow-2xl border border-outline-variant overflow-hidden">
                @csrf @method('PUT')
                <div class="px-6 py-4 border-b border-surface-variant flex justify-between items-center bg-surface-container-low/40">
                    <div>
                        <h3 id="edit-veille-title" class="text-xl font-bold text-on-surface">Modifier le sujet</h3>
                        <p class="text-sm text-on-surface-variant mt-1">Mettez à jour le titre, la catégorie ou le statut.</p>
                    </div>
                    <button type="button" data-modal-close aria-label="Fermer le formulaire de veille" class="inline-flex h-11 w-11 items-center justify-center rounded-lg text-on-surface-variant transition hover:bg-surface-container-low">
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>
                </div>
                <div class="p-6 space-y-5 max-h-[70vh] overflow-y-auto">
                    <div>
                        <label for="veille-edit-title" class="mb-2 block text-sm font-semibold text-on-surface">Titre *</label>
                        <input id="veille-edit-title" name="title" required value="{{ old('title',$subject->title) }}" class="w-full px-4 py-3 bg-surface-container-lowest border border-outline-variant rounded-xl text-base focus:outline-none focus:ring-2 focus:ring-primary-fixed focus:border-primary-fixed">
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="veille-edit-category" class="mb-2 block text-sm font-semibold text-on-surface">Catégorie</label>
                            <select id="veille-edit-category" name="category" class="w-full px-4 py-3 bg-surface-container-lowest border border-outline-variant rounded-xl text-base focus:outline-none focus:ring-2 focus:ring-primary-fixed focus:border-primary-fixed">
                                <option value="">— Non classé —</option>
                                @foreach(\App\Enums\PoleRubriqueEnum::selectOptions() as $v => $l)
                                <option value="{{ $v }}" @selected((string) old('category', $subject->category?->value) === $v)>{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="veille-edit-status" class="mb-2 block text-sm font-semibold text-on-surface">Statut</label>
                            <select id="veille-edit-status" name="status" class="w-full px-4 py-3 bg-surface-container-lowest border border-outline-variant rounded-xl text-base">
                                @foreach(['draft'=>'Brouillon','pending'=>'En attente','approved'=>'Retenu','rejected'=>'Rejeté','archived'=>'Archivé'] as $v=>$l)
                                <option value="{{ $v }}" @selected(old('status',$subject->status)===$v)>{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <label for="veille-edit-summary" class="mb-2 block text-sm font-semibold text-on-surface">Résumé</label>
                        <textarea id="veille-edit-summary" name="summary" rows="3" class="w-full px-4 py-3 bg-surface-container-lowest border border-outline-variant rounded-xl text-base focus:outline-none focus:ring-2 focus:ring-primary-fixed focus:border-primary-fixed">{{ old('summary',$subject->summary) }}</textarea>
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="veille-edit-priority" class="mb-2 block text-sm font-semibold text-on-surface">Priorité (1-10)</label>
                            <input type="number" min="1" max="10" id="veille-edit-priority" name="priority" value="{{ old('priority',$subject->priority) }}" class="w-full px-4 py-3 bg-surface-container-lowest border border-outline-variant rounded-xl text-base focus:outline-none focus:ring-2 focus:ring-primary-fixed focus:border-primary-fixed">
                        </div>
                        <div>
                            <label for="veille-edit-url" class="mb-2 block text-sm font-semibold text-on-surface">URL source</label>
                            <input id="veille-edit-url" name="url" type="url" value="{{ old('url',$subject->url) }}" class="w-full px-4 py-3 bg-surface-container-lowest border border-outline-variant rounded-xl text-base focus:outline-none focus:ring-2 focus:ring-primary-fixed focus:border-primary-fixed">
                        </div>
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-surface-variant flex justify-end gap-3 bg-surface-container-low/40">
                    <button type="button" data-modal-close class="px-5 py-3 rounded-xl border border-outline-variant text-on-surface-variant font-semibold text-sm tracking-[0.05em] hover:text-primary hover:border-primary transition">Annuler</button>
                    <button type="submit" class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-primary-container text-on-primary-container font-semibold text-sm tracking-[0.05em] hover:opacity-90 transition">
                        <i class="fa-solid fa-check"></i> Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endcan
</section>

<script>
(function(){
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabPanels = document.querySelectorAll('.tab-panel');
    function activateVeilleTab(button) {
        const target = button.dataset.tabTarget;
        tabBtns.forEach(x => {
            const active = x === button;
            x.classList.toggle('border-primary', active);
            x.classList.toggle('text-primary', active);
            x.classList.toggle('border-transparent', !active);
            x.classList.toggle('text-on-surface-variant', !active);
            x.setAttribute('aria-selected', String(active));
            x.tabIndex = active ? 0 : -1;
        });
        tabPanels.forEach(panel => {
            const hidden = panel.id !== target;
            panel.classList.toggle('hidden', hidden);
            panel.hidden = hidden;
        });
    }
    tabBtns.forEach((button, index) => {
        button.addEventListener('click', () => activateVeilleTab(button));
        button.addEventListener('keydown', event => {
            const keys = {
                ArrowRight: (index + 1) % tabBtns.length,
                ArrowLeft: (index - 1 + tabBtns.length) % tabBtns.length,
                Home: 0,
                End: tabBtns.length - 1,
            };
            if (!(event.key in keys)) return;
            event.preventDefault();
            const next = tabBtns[keys[event.key]];
            activateVeilleTab(next);
            next.focus();
        });
    });

    const modals = document.querySelectorAll('[data-modal-backdrop]');
    modals.forEach(modal => {
        const opens = document.querySelectorAll('[data-modal-open="' + modal.id + '"]');
        const closes = modal.querySelectorAll('[data-modal-close]');
        let returnFocus = null;
        let previousOverflow = '';
        function closeModal() {
            if (modal.classList.contains('hidden')) return;
            modal.classList.add('hidden');
            document.body.style.overflow = previousOverflow;
            if (returnFocus?.isConnected) returnFocus.focus({ preventScroll: true });
            returnFocus = null;
        }
        opens.forEach(button => button.addEventListener('click', event => {
            event.preventDefault();
            if (!modal.classList.contains('hidden')) return;
            returnFocus = button;
            previousOverflow = document.body.style.overflow;
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            (modal.querySelector('input[name="title"]') || modal).focus({ preventScroll: true });
        }));
        closes.forEach(button => button.addEventListener('click', event => {
            event.preventDefault();
            closeModal();
        }));
        modal.addEventListener('keydown', event => {
            if (event.key === 'Escape') { event.preventDefault(); closeModal(); return; }
            if (event.key !== 'Tab') return;
            const focusables = Array.from(modal.querySelectorAll(
                'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
            )).filter(el => el.getClientRects().length);
            if (!focusables.length) { event.preventDefault(); modal.focus(); return; }
            const first = focusables[0], last = focusables[focusables.length - 1];
            if (event.shiftKey && (document.activeElement === first || !modal.contains(document.activeElement))) {
                event.preventDefault(); last.focus();
            } else if (!event.shiftKey && (document.activeElement === last || !modal.contains(document.activeElement))) {
                event.preventDefault(); first.focus();
            }
        });
    });
})();
</script>
@endsection
