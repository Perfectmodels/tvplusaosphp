@extends('layouts.app')

@php
/**
 * @var \Illuminate\Support\Carbon $dateFiltre
 * @var string $search
 * @var int|null $emissionFiltre
 * @var \App\Enums\EtapeParcoursIntervenantEnum|null $etapeFiltre
 * @var string $tri
 * @var object{kpis} $kpis
 * @var \Illuminate\Pagination\LengthAwarePaginator|\App\Models\ParcoursIntervenant[] $parcours
 * @var \Illuminate\Support\Collection $emissionsFiltre
 * @var \Illuminate\Support\Collection $etapesFiltre
 * @var \App\Enums\EtapeParcoursIntervenantEnum[] $etapesTimeline
 */

use App\Enums\EtapeParcoursIntervenantEnum;

$routeBase = 'parcours-intervenants.index';
$total = $parcours->total();
$debut = $parcours->firstItem() ?? 0;
$fin   = $parcours->lastItem() ?? 0;

$kpiCartes = [
    [
        'icone'   => 'fa-solid fa-users',
        'classe'  => 'bg-primary-fixed/5 border-primary-fixed/15 text-primary-fixed',
        'valeur'  => $kpis->total_jour,
        'label'   => 'Intervenants du jour',
    ],
    [
        'icone'   => 'fa-solid fa-check-circle',
        'classe'  => 'bg-success/10 border-success/25 text-success',
        'valeur'  => $kpis->confirmes,
        'label'   => 'Confirmés',
    ],
    [
        'icone'   => 'fa-solid fa-car',
        'classe'  => 'bg-primary-fixed/5 border-primary-fixed/15 text-primary-fixed',
        'valeur'  => $kpis->en_chemin,
        'label'   => 'En chemin',
    ],
    [
        'icone'   => 'fa-regular fa-user',
        'classe'  => 'bg-primary-fixed/5 border-primary-fixed/15 text-primary-fixed',
        'valeur'  => $kpis->arrives,
        'label'   => 'Arrivés',
    ],
    [
        'icone'   => 'fa-solid fa-pen',
        'classe'  => 'bg-primary-fixed/5 border-primary-fixed/15 text-primary-fixed',
        'valeur'  => $kpis->maquillage,
        'label'   => 'En maquillage',
    ],
    [
        'icone'   => 'fa-solid fa-video',
        'classe'  => 'bg-primary-fixed/5 border-primary-fixed/15 text-primary-fixed',
        'valeur'  => $kpis->plateau,
        'label'   => 'Au plateau',
    ],
    [
        'icone'   => 'fa-regular fa-clock',
        'classe'  => 'bg-error/10 border-error/30 text-error',
        'valeur'  => $kpis->retards,
        'label'   => 'Retard',
        'alerte'  => $kpis->retards > 0,
    ],
];
@endphp

@section('title', 'Parcours de l\'intervenant')

@section('content')
    <div class="min-h-screen w-full">
        <div class="mx-auto max-w-[1600px] px-5 md:px-8 py-6">

            <!-- =========================================================
                 HEADER PAGE : Titre + Barre de filtres (capture TV+A OS)
                 ========================================================= -->
            <header class="mb-7 space-y-5">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <h1 class="text-[24px] sm:text-[26px] md:text-[30px] font-bold text-on-surface tracking-tight leading-[1.1]">
                            PARCOURS DE L'INTERVENANT
                        </h1>
                        <p class="mt-2 text-sm md:text-[15px] text-on-surface-variant">
                            Suivi en temps réel des invités réservés pour aujourd'hui.
                        </p>
                    </div>

                    <!-- Bouton Programmer un intervenant -->
                    <button type="button" id="btn-programmer"
                            class="min-h-[44px] w-full sm:w-auto px-5 rounded-full bg-primary text-on-primary inline-flex items-center justify-center gap-2 text-sm font-semibold
                                   hover:opacity-95 transition-all shadow-md shrink-0">
                        <i class="fa-solid fa-user-plus text-sm" aria-hidden="true"></i>
                        <span>Programmer un intervenant</span>
                    </button>
                </div>

                <!-- Barre filtres : Search + Filtre + Date picker -->
                <form method="GET" action="{{ route($routeBase) }}"
                      class="grid grid-cols-1 sm:grid-cols-2 xl:flex xl:flex-wrap xl:items-end gap-3 w-full">
                    <!-- SEARCH -->
                    <div class="relative group sm:col-span-2 xl:flex-1 xl:min-w-[280px]">
                        <label for="parcours-search" class="sr-only">Rechercher un intervenant</label>
                        <input type="text" id="parcours-search" name="q" value="{{ e($search) }}"
                               placeholder="Rechercher un intervenant"
                               class="w-full h-11 rounded-full bg-surface-container-lowest border border-outline-variant pl-11 pr-4 text-sm text-on-surface placeholder:text-on-surface-variant/70 shadow-sm outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/40 transition-all">
                        <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant/70 text-sm pointer-events-none" aria-hidden="true"></i>
                    </div>

                    <div data-emission-searchable class="sm:col-span-2 xl:min-w-[240px] space-y-1.5">
                        <label for="parcours-emission-search" class="text-xs font-semibold text-on-surface-variant">Émission</label>
                        <input type="search" id="parcours-emission-search" data-emission-search aria-label="Rechercher une émission à filtrer"
                               placeholder="Rechercher une émission…" autocomplete="off"
                               class="w-full h-11 rounded-full bg-surface-container-lowest border border-outline-variant px-4 text-sm text-on-surface outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/40">
                        <select name="emission_id" data-emission-select
                                class="w-full min-h-[44px] rounded-xl bg-surface-container-lowest border border-outline-variant px-3 text-sm text-on-surface outline-none focus:ring-2 focus:ring-primary/20">
                            <option value="">Toutes les émissions</option>
                            @foreach($emissionsFiltre as $id => $nom)
                                <option value="{{ $id }}" @selected((int) $emissionFiltre === (int) $id)>{{ $nom }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- DATE PICKER -->
                    <div class="space-y-1.5">
                        <label for="parcours-date" class="text-xs font-semibold text-on-surface-variant">Date</label>
                        <div class="relative">
                            <input type="date" id="parcours-date" name="date" value="{{ $dateFiltre->toDateString() }}"
                                   class="w-full h-11 pl-11 pr-4 rounded-full bg-surface-container-lowest border border-outline-variant text-sm font-medium text-on-surface shadow-sm outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/40 transition-all">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant/70 text-sm pointer-events-none">
                                <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                            </span>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2 sm:items-end sm:pt-5 xl:pt-0">
                        <button type="submit" class="min-h-[44px] flex-1 sm:flex-none px-5 rounded-full bg-primary text-on-primary text-sm font-semibold shadow-sm hover:opacity-95 transition-colors">
                            Appliquer
                        </button>
                        @if($search !== '' || $emissionFiltre || $etapeFiltre)
                            <a href="{{ route($routeBase, ['date'=>$dateFiltre->toDateString()]) }}"
                               class="min-h-[44px] flex-1 sm:flex-none inline-flex items-center justify-center px-5 rounded-full border border-outline-variant text-on-surface-variant hover:bg-surface-container-high text-sm transition-colors">
                                Réinitialiser
                            </a>
                        @endif
                    </div>
                </form>
            </header>

            <!-- =========================================================
                 7 CARTES KPI (conforme capture 7 blocs)
                 ========================================================= -->
            <section aria-label="KPI du jour"
                     class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3 md:gap-4 mb-8">
                @foreach($kpiCartes as $kpi)
                    <div class="relative rounded-2xl bg-surface-container-lowest border border-outline-variant/50 shadow-[0_2px_8px_-2px_rgba(0,0,0,.06)] p-4 hover:shadow-md transition-shadow group
                                @if(!empty($kpi['alerte'])) ring-1 ring-error/30 @endif">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 shrink-0 rounded-xl border flex items-center justify-center text-[15px] {{ $kpi['classe'] }}">
                                <i class="{{ $kpi['icone'] }}"></i>
                            </div>
                            <div class="min-w-0">
                                <div class="text-[24px] md:text-[26px] font-extrabold tracking-tight text-on-surface leading-none
                                            @if(!empty($kpi['alerte'])) text-error @endif">
                                    {{ $kpi['valeur'] }}
                                </div>
                                <div class="mt-1.5 text-xs text-on-surface-variant font-medium leading-tight">
                                    {{ $kpi['label'] }}
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </section>

            <!-- =========================================================
                 TABLEAU PRINCIPAL (capture TV+A OS)
                 Colonnes: INTERVENANT · FONCTION · ÉMISSION · PARCOURS timeline · Badge statut
                 ========================================================= -->
            <section class="bg-surface-container-lowest rounded-3xl border border-outline-variant/50 shadow-[0_2px_14px_-4px_rgba(0,0,0,.06)] overflow-hidden">
                @if($total === 0)
                    <div class="px-8 py-20 text-center">
                        <div class="mx-auto w-16 h-16 rounded-2xl bg-primary-fixed/5 border border-primary-fixed/15 text-primary-fixed flex items-center justify-center text-2xl">
                            <i class="fa-solid fa-user-clock"></i>
                        </div>
                        <h3 class="mt-5 text-lg font-bold text-on-surface">Aucun intervenant programmé pour cette journée</h3>
                        <p class="mt-2 text-sm text-on-surface-variant">
                            Sélectionnez une autre date, ou ajoutez des intervenants aux sujets du conducteur Chef d'Édition.
                        </p>
                        <div class="mt-6 flex flex-wrap gap-3 justify-center">
                            <a href="{{ route('conducteurs.chef_edition') }}"
                               class="min-h-[44px] px-5 rounded-full bg-primary text-on-primary text-sm font-semibold shadow-sm hover:opacity-95 transition-colors inline-flex items-center justify-center gap-2">
                                <i class="fa-solid fa-edit-note" aria-hidden="true"></i>
                                Ouvrir le Chef d'Édition
                            </a>
                            <a href="{{ route('intervenants.index') }}"
                               class="h-11 px-5 rounded-full border border-outline-variant text-on-surface-variant hover:bg-surface-container-high text-sm transition-colors inline-flex items-center gap-2">
                                <i class="fa-solid fa-group_add"></i>
                                Liste des intervenants
                            </a>
                        </div>
                    </div>
                @else
                    <!-- HEADER TABLEAU — colonnes d'étapes du parcours -->
                    <div class="overflow-x-auto">
                    <table data-mobile-cards class="w-full text-left text-sm min-w-full lg:min-w-[1280px]">
                        <thead>
                            <tr class="border-b border-outline-variant/40 bg-surface/40 text-xs uppercase font-bold tracking-wider text-on-surface-variant/80">
                                <th class="px-4 py-3.5 w-[260px]">Nom de l’intervenant</th>
                                <th class="px-4 py-3.5 w-[150px] hidden md:table-cell">Fonction</th>
                                <th class="px-4 py-3.5 w-[140px]">Émission</th>
                                @foreach(\App\Http\Controllers\ParcoursIntervenantController::ETAPES_UI as $def)
                                    <th class="px-2 py-3.5 w-[108px] text-center hidden lg:table-cell">
                                        <span class="inline-flex items-center gap-1.5">
                                            <i class="fa-solid {{ $def['icone'] }} text-xs opacity-70" aria-hidden="true"></i>
                                            {{ $def['label'] }}
                                        </span>
                                    </th>
                                @endforeach
                                <th class="px-3 py-3.5 w-[110px] text-center">État</th>
                                <th class="px-4 py-3.5 w-[120px] text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant/25">
                        @foreach($parcours as $p)
                            @php
                                $i = $p->intervenant;
                                $nomComplet = $i?->nom_complet ?? '(Supprimé #'.$p->intervenant_id.')';
                                $photo = $i?->photo_url ?? (
                                    'https://ui-avatars.com/api/?name='.urlencode($nomComplet).'&background=001a41&color=ffffff&size=128'
                                );
                                $fonction = $i?->fonction ?? '—';
                                $organisme = $i?->organisme;
                                $emission = $p->emission?->nom ?? $p->sequence?->emission?->nom ?? $p->conducteur?->emission?->nom ?? '—';

                                $heurePassage = $p->heure_passage_prevue?->format('H:i');

                                $etapesUi = collect(\App\Http\Controllers\ParcoursIntervenantController::ETAPES_UI)->keyBy('value')->all();
                                $timeline = collect($p->timeline_ui)->keyBy(fn($t) => $t['etape']->value);

                                // Première étape non faite = étape courante
                                $courante = null;
                                foreach ($etapesUi as $val => $_d) {
                                    if (($timeline[$val]['statut'] ?? '') !== 'fait') { $courante = $val; break; }
                                }

                                $etapesValidees = collect($etapesUi)->map(function ($def) use ($timeline) {
                                    $etape = $timeline[$def['value']] ?? null;

                                    if (($etape['statut'] ?? '') !== 'fait') {
                                        return null;
                                    }

                                    return [
                                        'label' => $def['label'],
                                        'icone' => $def['icone'],
                                        'date_iso' => $etape['date_iso'] ?? null,
                                        'horodatage' => $etape['horodatage'] ?? null,
                                    ];
                                })->filter()->values();
                            @endphp
                            <tr class="hover:bg-primary-fixed/[0.025] transition-colors group align-middle">

                                {{-- INTERVENANT --}}
                                <td class="px-4 py-3.5" data-card-wide="true">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <img src="{{ $photo }}" alt="" loading="lazy"
                                             class="w-10 h-10 rounded-xl object-cover border border-outline-variant/60 shrink-0 bg-surface-container">
                                        <div class="min-w-0">
                                            <div class="text-sm font-semibold text-on-surface truncate">{{ $nomComplet }}</div>
                                            @if($organisme)
                                                <div class="text-xs text-on-surface-variant truncate">{{ $organisme }}</div>
                                            @endif
                                            <div class="mt-1 text-xs text-on-surface-variant md:hidden">{{ $fonction }}</div>
                                        </div>
                                    </div>
                                    <div aria-label="Horodatages des étapes validées" class="mt-3 w-full lg:hidden">
                                        <p class="mb-1 text-[11px] font-semibold text-on-surface-variant">Étapes validées · date et heure</p>
                                        @if($etapesValidees->isNotEmpty())
                                            <ul class="grid grid-cols-1 sm:grid-cols-2 gap-1.5">
                                                @foreach($etapesValidees as $etape)
                                                    <li class="flex items-center justify-between gap-2 rounded-lg bg-surface-container-lowest px-2.5 py-1.5">
                                                        <span class="min-w-0 truncate text-xs text-on-surface-variant">
                                                            <i class="fa-solid {{ $etape['icone'] }} mr-1" aria-hidden="true"></i>{{ $etape['label'] }}
                                                        </span>
                                                        <time @if($etape['date_iso']) datetime="{{ $etape['date_iso'] }}" @endif
                                                              class="shrink-0 font-mono text-[11px] font-semibold text-emerald-700">
                                                            {{ $etape['horodatage'] ?? 'Horodatage indisponible' }}
                                                        </time>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @else
                                            <p class="text-xs text-on-surface-variant">Aucune étape validée.</p>
                                        @endif
                                    </div>
                                </td>

                                {{-- FONCTION --}}
                                <td class="px-4 py-3.5 text-sm text-on-surface-variant/90 truncate hidden md:table-cell">{{ $fonction }}</td>

                                {{-- ÉMISSION --}}
                                <td class="px-4 py-3.5" data-card-wide="true">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full
                                                 bg-primary/10 text-primary border border-primary/20
                                                 text-xs font-bold tracking-wide max-w-full">
                                        <i class="fa-solid fa-tv text-xs" aria-hidden="true"></i>
                                        <span class="uppercase truncate">{{ $emission }}</span>
                                    </span>

                                    @if($heurePassage)
                                        <div class="mt-1 text-xs font-mono font-semibold text-on-surface-variant">
                                            <i class="fa-regular fa-clock mr-1" aria-hidden="true"></i>{{ $heurePassage }}
                                        </div>
                                    @endif
                                </td>

                                {{-- 6 COLONNES D'ÉTAPES --}}
                                @foreach($etapesUi as $val => $def)
                                    @php
                                        $t = $timeline[$val] ?? null;
                                        $statut = $t['statut'] ?? 'futur';
                                        $heure  = $t['heure'] ?? null;
                                        $estCourant = ($courante === $val);
                                    @endphp
                                    <td class="px-2 py-3.5 text-center hidden lg:table-cell">
                                        @if($statut === 'fait')
                                            <div class="inline-flex flex-col items-center gap-0.5">
                                                <span class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-700 inline-flex items-center justify-center">
                                                    <i class="fa-solid fa-check text-xs"></i>
                                                </span>
                                                <time @if($t['date_iso'] ?? false) datetime="{{ $t['date_iso'] }}" @endif
                                                      class="flex flex-col items-center leading-tight">
                                                    <span class="text-[10px] font-medium text-on-surface-variant">{{ $t['date_complete'] ?? $t['date'] ?? '—' }}</span>
                                                    <span class="text-xs font-mono font-bold text-emerald-700">{{ $heure ?? '—' }}</span>
                                                </time>
                                            </div>
                                        @elseif($estCourant && $def['bouton'])
                                            @if($val === 'arrive_accueil')
                                                {{-- ⛔ Scan obligatoire : l'arrivée se valide uniquement au scanner --}}
                                                <a href="{{ route('scanner-qr.index') }}"
                                                   title="Scan QR obligatoire à l'accueil (utilisateur autorisé)"
                                                   class="inline-flex items-center gap-1 min-h-[44px] px-3 rounded-lg bg-sky-600 text-white text-xs font-semibold hover:bg-sky-700 transition-colors whitespace-nowrap">
                                                    <i class="fa-solid fa-qrcode text-xs"></i> Scanner
                                                </a>
                                            @else
                                                @can('advance', [$p, \App\Enums\EtapeParcoursIntervenantEnum::from($val)])
                                                <button type="button"
                                                        class="js-etape-ligne-btn inline-flex items-center gap-1 min-h-[44px] px-3 rounded-lg bg-primary text-white text-xs font-semibold hover:bg-primary-container hover:text-primary transition-colors whitespace-nowrap"
                                                        data-parcours-id="{{ $p->id }}"
                                                        data-etape="{{ $val }}"
                                                        data-url="{{ route('parcours-intervenants.etape', $p->id) }}">
                                                    <i class="fa-solid {{ $def['icone'] }} text-xs"></i> {{ $def['bouton'] }}
                                                </button>
                                                @else
                                                    <span class="text-xs font-medium text-on-surface-variant">Réservé au personnel autorisé</span>
                                                @endcan
                                            @endif
                                        @elseif($estCourant)
                                            <span class="inline-flex items-center gap-1 min-h-[44px] px-3 rounded-lg bg-primary/10 text-primary text-xs font-semibold animate-pulse">
                                                <i class="fa-solid {{ $def['icone'] }} text-xs"></i> à valider
                                            </span>
                                        @else
                                            <span class="w-8 h-8 rounded-full bg-surface-container text-on-surface-variant/50 inline-flex items-center justify-center">
                                                <i class="fa-solid {{ $def['icone'] }} text-xs"></i>
                                            </span>
                                        @endif
                                    </td>
                                @endforeach

                                {{-- ÉTAT --}}
                                <td class="px-3 py-3.5 text-center sm:text-left lg:text-center" data-card-wide="true">
                                    {!! $p->badgeStatutActuel !!}
                                    @if(! $p->conducteur_id)
                                        <div class="mt-1 text-xs font-semibold text-amber-700 bg-amber-50 border border-amber-200 rounded px-1.5 py-0.5 inline-block">
                                            En attente de conducteur pour cette édition
                                        </div>
                                    @elseif(! $p->sequence_intervenant_id)
                                        <div class="mt-1 text-xs font-semibold text-blue-800 bg-blue-50 border border-blue-200 rounded px-1.5 py-0.5 inline-block">
                                            Conducteur trouvé · séquence à affecter
                                        </div>
                                    @endif
                                </td>

                                {{-- ACTIONS --}}
                                <td class="px-4 py-3.5 text-right" data-card-wide="true">
                                    <div class="flex flex-wrap items-center justify-start lg:justify-end gap-2">
                                        {{-- Envoi WhatsApp : le formulaire cible _blank pour garder l'app ouverte --}}
                                        @can('sendInvitation', $p)
                                        <form method="POST"
                                              action="{{ route('parcours-intervenants.whatsapp', $p) }}"
                                              target="_blank"
                                              class="js-whatsapp-form inline-flex">
                                            @csrf
                                            <button type="submit"
                                                    title="Envoyer l'invitation par WhatsApp"
                                                    class="inline-flex items-center gap-1.5 min-h-[44px] px-3 rounded-full border border-[#25d366]/40 bg-[#25d366]/10 text-[#128c4a] text-xs font-semibold hover:bg-[#25d366] hover:text-white hover:border-[#25d366] transition-colors">
                                                <i class="fa-brands fa-whatsapp text-sm" aria-hidden="true"></i> WhatsApp
                                            </button>
                                        </form>
                                        @endcan

                                        {{-- QR permanent : ouvre la page publique (aperçu + téléchargement) --}}
                                        <a href="{{ route('invitations.show', ['token' => $p->intervenant->tokenInvitation()]) }}"
                                           target="_blank"
                                           title="Afficher / télécharger le QR de l'intervenant"
                                           class="inline-flex items-center justify-center min-h-[44px] min-w-[44px] rounded-full border border-outline-variant text-on-surface-variant hover:bg-primary hover:text-on-primary hover:border-primary transition-colors">
                                            <i class="fa-solid fa-qrcode text-xs" aria-hidden="true"></i>
                                            <span class="sr-only">QR</span>
                                        </a>

                                        <button type="button"
                                                class="js-parcours-btn lg:opacity-60 group-hover:opacity-100 transition-all inline-flex items-center gap-1.5 min-h-[44px] px-3 rounded-full
                                                       border border-outline-variant text-on-surface-variant text-xs font-semibold
                                                       hover:bg-primary hover:text-on-primary hover:border-primary"
                                                data-parcours-id="{{ $p->id }}"
                                                data-parcours-url="{{ route('parcours-intervenants.etape', $p->id) }}"
                                                data-parcours-retard="{{ route('parcours-intervenants.retard', $p->id) }}"
                                                data-parcours-noshow="{{ route('parcours-intervenants.no_show', $p->id) }}">
                                            <i class="fa-solid fa-route text-xs" aria-hidden="true"></i> Détail
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    </div>


                    <!-- FOOTER TABLEAU: Résultats + Pagination + Taille page -->
                    <footer class="px-4 md:px-8 py-5 border-t border-outline-variant/40 flex flex-col md:flex-row items-center justify-between gap-4 bg-surface/30">
                        <div class="text-sm text-on-surface-variant text-center md:text-left">
                            <span class="font-semibold text-on-surface">{{ $debut }} – {{ $fin }}</span>
                            <span> sur </span>
                            <span class="font-semibold text-on-surface">{{ $total }}</span>
                            <span> intervenants</span>
                        </div>

                        <div class="flex w-full md:w-auto items-center gap-3 flex-wrap justify-center md:justify-end">
                            <div class="w-full sm:w-auto overflow-x-auto">{{ $parcours->onEachSide(1)->links() }}</div>
                            <form method="GET" action="{{ route($routeBase) }}" class="flex items-center gap-2">
                                @foreach(request()->except(['par_page','page']) as $k => $v)
                                    <input type="hidden" name="{{ $k }}" value="{{ e($v) }}">
                                @endforeach
                                <label class="text-xs text-on-surface-variant font-medium flex items-center gap-1">
                                    <span class="sr-only">Éléments par page</span>
                                    <select name="par_page" onchange="this.form.submit()"
                                            class="h-10 px-2.5 pr-8 rounded-lg bg-surface-container border border-outline-variant text-sm font-medium outline-none focus:ring-2 focus:ring-primary/20">
                                        @foreach([10,25,50,100] as $sz)
                                            <option value="{{ $sz }}" @selected(request()->integer('par_page',10)===$sz)>
                                                {{ $sz }} / page
                                            </option>
                                        @endforeach
                                    </select>
                                </label>
                            </form>
                        </div>
                    </footer>
                @endif
            </section>
        </div>
    </div>

    {{-- ========================================================= --}}
    {{-- MODAL : WORKFLOW PARCOURS INTERVENANT (validation manuelle) --}}
    {{-- ========================================================= --}}
    <div id="parcours-modal"
         class="fixed inset-0 z-[110] hidden items-end sm:items-center justify-center p-0 sm:p-4">
        <div class="absolute inset-0 bg-slate-900/55 backdrop-blur-[2px]" data-parcours-close></div>

        <div class="relative w-full sm:max-w-3xl bg-white rounded-t-2xl sm:rounded-2xl shadow-2xl border border-outline/10 max-h-[96dvh] sm:max-h-[92dvh] overflow-hidden flex flex-col">

            <header class="px-6 py-5 border-b border-outline/10">
                <div class="flex justify-between items-start gap-4">
                    <div>
                        <div class="flex items-center gap-3 text-xs text-on-surface-variant uppercase tracking-wider mb-1.5">
                            <i class="fa-solid fa-route text-primary"></i>
                            <span>Parcours de l'intervenant</span>
                        </div>
                        <h3 class="text-xl font-bold text-on-surface" id="parcours-titre">—</h3>
                        <p class="text-sm text-on-surface-variant mt-0.5">
                            Invitation → Confirmation → En chemin → <span class="inline-flex items-center gap-1 font-semibold text-sky-700"><i class="fa-solid fa-qrcode text-xs"></i>Scan accueil</span> → Arrivé → Maquillage → Plateau
                        </p>
                    </div>
                    <button type="button" data-parcours-close
                            class="p-2 rounded-lg hover:bg-surface-container text-secondary transition-colors">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </header>

            <div class="flex-1 overflow-y-auto p-6 space-y-6">

                {{-- Bandeau d'état --}}
                <div id="parcours-bandeau" class="flex flex-wrap items-center gap-2"></div>
                <div id="parcours-affecter" class="hidden rounded-xl border border-outline/20 bg-surface-container-low/50 p-4">
                    <label for="parcours-select-sequence" class="mb-2 block text-sm font-semibold text-on-surface">
                        Affecter cet invité à une séquence
                    </label>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <select id="parcours-select-sequence"
                                class="min-w-0 flex-1 rounded-lg border border-outline/30 bg-white px-3 py-2 text-sm"></select>
                        <button id="parcours-btn-affecter" type="button"
                                class="min-h-10 rounded-lg bg-primary px-4 py-2 text-sm font-bold text-white hover:opacity-90">
                            Valider l'affectation
                        </button>
                    </div>
                    <p class="mt-1 text-xs text-on-surface-variant">Seules les séquences de cette émission et de cette édition sont proposées.</p>
                </div>

                {{-- ⛔ Règle « scan obligatoire » --}}
                <div class="rounded-xl border border-sky-200 bg-sky-50/60 p-4 flex items-start gap-3">
                    <i class="fa-solid fa-qrcode mt-0.5 text-sky-600"></i>
                    <p class="text-xs leading-relaxed text-sky-900">
                        <span class="font-semibold">Quand scanner le QR ?</span>
                        Après la confirmation de l'invitation, l'arrivée de l'invité est validée
                        <span class="font-semibold">uniquement par le scan de son QR au poste « Accueil »</span>
                        (par un utilisateur autorisé). Les étapes studio suivent ensuite manuellement.
                    </p>
                </div>

                {{-- Étapes du parcours --}}
                <ol id="parcours-steps" class="space-y-2"></ol>

                {{-- Actions exceptionnelles --}}
                <div class="rounded-xl border border-amber-200 bg-amber-50/60 p-4">
                    <div class="flex items-start gap-3">
                        <div class="w-9 h-9 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </div>
                        <div class="flex-1">
                            <h4 class="font-semibold text-amber-900 text-sm">Cas particuliers</h4>
                            <p class="text-xs text-amber-800/80 mt-0.5">
                                Signalez un retard ou une absence si l'intervenant ne se présente pas comme prévu.
                            </p>
                            <div class="flex flex-wrap items-center gap-2 mt-3">
                                <button type="button" id="parcours-btn-retard"
                                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-amber-600 text-white text-sm font-semibold hover:bg-amber-700 transition-colors">
                                    <i class="fa-solid fa-clock text-xs"></i> En retard
                                </button>
                                <button type="button" id="parcours-btn-noshow"
                                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-amber-300 text-amber-800 text-sm font-semibold hover:bg-amber-100 transition-colors">
                                    <i class="fa-solid fa-user-slash text-xs"></i> Absent (no-show)
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Journal logistique --}}
                <div>
                    <h4 class="text-xs uppercase tracking-wider text-on-surface-variant font-semibold mb-2">
                        <i class="fa-solid fa-clipboard-list mr-1"></i>Journal logistique
                    </h4>
                    <div id="parcours-notes"
                         class="text-xs text-on-surface-variant whitespace-pre-line leading-relaxed rounded-lg border border-outline/15 bg-surface-container-lowest p-3 min-h-[60px]">
                        —
                    </div>
                </div>
            </div>

            <footer class="px-4 sm:px-6 py-4 border-t border-outline/10 bg-surface-container-lowest flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3">
                <p class="text-xs text-on-surface-variant">
                    <i class="fa-solid fa-circle-info mr-1 text-primary" aria-hidden="true"></i>
                    Chaque validation est horodatée et attribuée à votre compte.
                </p>
                <button type="button" data-parcours-close
                        class="w-full sm:w-auto px-5 py-2.5 min-h-[44px] rounded-lg border border-outline/25 text-on-surface-variant text-sm hover:bg-surface-container transition-colors">
                    Fermer
                </button>
            </footer>
        </div>
    </div>

    {{-- ========================================================= --}}
    {{-- MODAL : PROGRAMMER UN INTERVENANT                         --}}
    {{-- ========================================================= --}}
    <div id="programmer-modal" class="fixed inset-0 z-[115] hidden items-end sm:items-center justify-center p-0 sm:p-4">
        <div class="absolute inset-0 bg-slate-900/55 backdrop-blur-[2px]" data-programmer-close></div>

        <div class="relative w-full sm:max-w-2xl bg-white rounded-t-2xl sm:rounded-2xl shadow-2xl border border-outline/10 max-h-[96dvh] sm:max-h-[92dvh] overflow-hidden flex flex-col">
            <header class="px-6 py-5 border-b border-outline/10">
                <div class="flex justify-between items-start gap-4">
                    <div>
                        <div class="flex items-center gap-2 text-xs text-on-surface-variant uppercase tracking-wider mb-1.5">
                            <i class="fa-solid fa-user-plus text-primary"></i>
                            <span>Programmation</span>
                        </div>
                        <h3 class="text-xl font-bold text-on-surface">Programmer un intervenant</h3>
                        <p class="text-sm text-on-surface-variant mt-1">
                            L'intervenant reçoit son <strong>invitation</strong>. Il ne sera placé dans le conducteur
                            <strong>qu'à son acceptation</strong>.
                        </p>
                    </div>
                    <button type="button" data-programmer-close
                            class="p-2 rounded-lg hover:bg-surface-container text-secondary transition-colors">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </header>

            <div class="flex-1 overflow-y-auto p-6 space-y-5">

                {{-- Intervenant --}}
                <div>
                    <label class="block text-xs font-semibold text-on-surface-variant mb-1.5">
                        Intervenant <span class="text-error">*</span>
                    </label>
                    <input type="search" id="prog-intervenant-search"
                               placeholder="Rechercher un intervenant (nom, fonction, organisme)…"
                               autocomplete="off"
                               class="w-full h-10 px-3 mb-2 rounded-lg border border-outline-variant bg-white text-sm outline-none focus:ring-2 focus:ring-primary/30">
                        <select id="prog-intervenant" required
                            class="w-full h-11 px-3 rounded-lg border border-outline-variant bg-white text-sm outline-none focus:ring-2 focus:ring-primary/30">
                        <option value="">— Sélectionner un intervenant —</option>
                        @foreach($intervenantsProgrammables as $i)
                            <option value="{{ $i['id'] }}"
                                    data-fonction="{{ e($i['fonction']) }}"
                                    data-organisme="{{ e($i['organisme']) }}"
                                        data-search="{{ strtolower(e($i['nom_complet'].' '.$i['fonction'].' '.$i['organisme'])) }}">
                                {{ $i['nom_complet'] }} — {{ $i['fonction'] }}@if($i['organisme'] !== '—') · {{ $i['organisme'] }}@endif
                            </option>
                        @endforeach
                    </select>
                    @can('create', \App\Models\Intervenant::class)
                        <p class="mt-1.5 text-xs text-on-surface-variant">
                            <a href="{{ route('intervenants.create') }}" target="_blank" rel="noopener"
                               class="text-primary hover:underline inline-flex items-center gap-1">
                                <i class="fa-solid fa-plus text-[10px]"></i> Créer un nouvel intervenant
                            </a>
                            <span class="opacity-70"> (ouvre dans un nouvel onglet)</span>
                        </p>
                    @endcan
                </div>
 
                {{-- Émission --}}
                <div>
                    <label class="block text-xs font-semibold text-on-surface-variant mb-1.5">
                        Émission <span class="text-error">*</span>
                    </label>
                    <div data-emission-searchable class="space-y-2">
                        <input type="search" data-emission-search aria-label="Rechercher une émission à programmer"
                               placeholder="Rechercher une émission…" autocomplete="off"
                               class="w-full h-10 px-3 rounded-lg border border-outline-variant bg-white text-sm outline-none focus:ring-2 focus:ring-primary/30">
                        <select id="prog-emission" required data-emission-select
                                class="w-full h-11 px-3 rounded-lg border border-outline-variant bg-white text-sm outline-none focus:ring-2 focus:ring-primary/30">
                            <option value="">— Sélectionner une émission —</option>
                            @foreach($emissionsProgrammables as $e)
                                <option value="{{ $e['id'] }}" @if($e['heure']) data-heure="{{ e($e['heure']) }}" @endif>
                                    {{ $e['nom'] }}@if($e['heure']) · dès {{ $e['heure'] }}@endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Heure de passage + note --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-on-surface-variant mb-1.5">
                            <i class="fa-regular fa-clock mr-1"></i>Heure de passage <span class="text-error">*</span>
                        </label>
                        <input type="datetime-local" id="prog-heure" required
                               class="w-full h-11 px-3 rounded-lg border border-outline-variant bg-white text-sm outline-none focus:ring-2 focus:ring-primary/30">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-on-surface-variant mb-1.5">Note (optionnel)</label>
                        <input type="text" id="prog-notes" maxlength="500" placeholder="Ex. : invité VIP, en duplex…"
                               class="w-full h-11 px-3 rounded-lg border border-outline-variant bg-white text-sm outline-none focus:ring-2 focus:ring-primary/30">
                    </div>
                </div>

                <div class="rounded-lg border border-primary/20 bg-primary/5 p-4 text-xs text-on-surface-variant leading-relaxed">
                    <i class="fa-solid fa-circle-info text-primary mr-1.5"></i>
                    Le booking ouvre WhatsApp avec un message prérempli et le lien du QR code.
                    Cliquez sur <strong>« Envoyer » dans WhatsApp</strong> pour transmettre l'invitation.
                    Cliquez ensuite sur <strong>« Accepté »</strong> dans sa ligne
                    pour qu'il soit <strong>placé dans le conducteur</strong> et que son parcours se poursuive.
                </div>
            </div>

            <footer class="px-4 sm:px-6 py-4 border-t border-outline/10 bg-surface-container-lowest flex flex-col-reverse sm:flex-row sm:flex-wrap sm:items-center sm:justify-end gap-3">
                <div id="prog-whatsapp-result" class="hidden w-full text-sm text-on-surface order-last sm:order-first" role="status"></div>
                <button type="button" data-programmer-close
                        class="w-full sm:w-auto px-5 py-2.5 min-h-[44px] rounded-lg border border-outline/25 text-on-surface-variant text-sm hover:bg-surface-container transition-colors">
                    Annuler
                </button>
                <button type="button" id="prog-submit"
                        class="w-full sm:w-auto px-6 min-h-[44px] rounded-lg bg-primary text-on-primary text-sm font-semibold hover:opacity-95 transition-colors inline-flex items-center justify-center gap-2 disabled:opacity-50">
                    <i class="fa-solid fa-paper-plane text-xs" aria-hidden="true"></i>
                    <span>Booker et ouvrir WhatsApp</span>
                </button>
            </footer>
        </div>
    </div>

    {{-- ========================================================= --}}
    {{-- MODAL : APERÇU / ÉDITION DU MESSAGE WHATSAPP              --}}
    {{-- ========================================================= --}}
    <div id="message-modal" class="fixed inset-0 z-[120] hidden items-end sm:items-center justify-center p-0 sm:p-4">
        <div class="absolute inset-0 bg-slate-900/55 backdrop-blur-[2px]" data-message-close></div>
        <div class="relative w-full sm:max-w-2xl bg-white rounded-t-2xl sm:rounded-2xl shadow-2xl border border-outline/10 max-h-[96dvh] sm:max-h-[92dvh] overflow-hidden flex flex-col">
            <header class="px-6 py-5 border-b border-outline/10">
                <div class="flex justify-between items-start gap-4">
                    <div>
                        <div class="flex items-center gap-2 text-xs text-on-surface-variant uppercase tracking-wider mb-1.5">
                            <i class="fa-brands fa-whatsapp text-green-600"></i>
                            <span>Message WhatsApp</span>
                        </div>
                        <h3 class="text-xl font-bold text-on-surface">Aperçu et édition du message</h3>
                        <p class="text-sm text-on-surface-variant mt-1">
                            Modifiez le message si besoin, puis envoyez-le via WhatsApp.
                        </p>
                    </div>
                    <button type="button" data-message-close
                            class="p-2 rounded-lg hover:bg-surface-container text-secondary transition-colors">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </header>
            <div class="flex-1 overflow-y-auto p-6 space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-on-surface-variant mb-1.5">
                        <i class="fa-solid fa-angles-right text-primary mr-1"></i> Angles / thèmes à aborder (pour l'IA)
                    </label>
                    <input type="text" id="msg-angles"
                           placeholder="Ex. : économie, jeunesse, santé…"
                           class="w-full h-11 px-3 rounded-lg border border-outline-variant bg-white text-sm outline-none focus:ring-2 focus:ring-primary/30">
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" id="msg-ai-suggest"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-primary/30 text-primary text-sm font-semibold hover:bg-primary/10 transition-colors disabled:opacity-50">
                        <i class="fa-solid fa-wand-magic-sparkles text-xs"></i> Suggérer avec l'IA
                    </button>
                    <span id="msg-ai-status" class="text-xs text-on-surface-variant"></span>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-on-surface-variant mb-1.5">
                        Message à envoyer
                    </label>
                    <textarea id="msg-text" rows="12"
                              class="w-full px-3 py-2.5 rounded-lg border border-outline-variant bg-white text-sm outline-none focus:ring-2 focus:ring-primary/30 font-mono leading-relaxed resize-y"></textarea>
                </div>
            </div>
            <footer class="px-4 sm:px-6 py-4 border-t border-outline/10 bg-surface-container-lowest flex flex-col-reverse sm:flex-row sm:flex-wrap sm:items-center sm:justify-end gap-3">
                <button type="button" data-message-close
                        class="w-full sm:w-auto px-5 py-2.5 min-h-[44px] rounded-lg border border-outline/25 text-on-surface-variant text-sm hover:bg-surface-container transition-colors">
                    Annuler
                </button>
                <button type="button" id="msg-send-whatsapp"
                        class="w-full sm:w-auto px-6 min-h-[44px] rounded-lg bg-green-600 text-white text-sm font-semibold hover:bg-green-700 transition-colors inline-flex items-center justify-center gap-2">
                    <i class="fa-brands fa-whatsapp"></i> Envoyer via WhatsApp
                </button>
            </footer>
        </div>
    </div>
         class="fixed bottom-6 right-6 z-[120] hidden max-w-sm px-5 py-3.5 rounded-xl shadow-2xl text-sm font-medium"></div>

    <style>
        .parcours-step{display:flex;align-items:flex-start;gap:.9rem;padding:.85rem 1rem;border-radius:.85rem;border:1px solid var(--tw-shadow-color,rgba(0,0,0,.06));transition:all .18s}
        .parcours-step.fait{background:#f0fdf4;border-color:#bbf7d0}
        .parcours-step.courant{background:#eff6ff;border-color:#bfdbfe;box-shadow:0 0 0 3px rgba(59,130,246,.12)}
        .parcours-step.a_venir{background:#f8fafc;border-color:#e2e8f0;opacity:.72}
        .parcours-dot{width:2.15rem;height:2.15rem;border-radius:9999px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:0.875rem}
        .parcours-step.fait .parcours-dot{background:#16a34a;color:#fff}
        .parcours-step.courant .parcours-dot{background:#2563eb;color:#fff;animation:parcoursPulse 1.8s ease-in-out infinite}
        .parcours-step.a_venir .parcours-dot{background:#e2e8f0;color:#64748b}
        @keyframes parcoursPulse{0%,100%{box-shadow:0 0 0 0 rgba(37,99,235,.45)}50%{box-shadow:0 0 0 7px rgba(37,99,235,0)}}
        #parcours-toast.success{background:#166534;color:#fff}
        #parcours-toast.error{background:#b91c1c;color:#fff}
    </style>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    const modal    = document.getElementById('parcours-modal');
    const toast    = document.getElementById('parcours-toast');
    const elSteps  = document.getElementById('parcours-steps');
    const elTitre  = document.getElementById('parcours-titre');
    const elBandeau= document.getElementById('parcours-bandeau');
    const elNotes  = document.getElementById('parcours-notes');

    let urls = { etat: '', etape: '', retard: '', noshow: '', assign: '' };
    const SCAN_URL = "{{ route('scanner-qr.index') }}";
    let etat = null;
    let busy = false;

    function csrf() {
        const m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    function showToast(msg, kind) {
        toast.textContent = msg;
        toast.className = 'fixed bottom-6 right-6 z-[120] max-w-sm px-5 py-3.5 rounded-xl shadow-2xl text-sm font-medium ' + (kind || 'success');
        toast.style.display = 'block';
        clearTimeout(showToast._t);
        showToast._t = setTimeout(() => { toast.style.display = 'none'; }, 3200);
    }

    function openModal() {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = '';
        location.reload();
    }

    function render() {
        if (!etat) return;

        elTitre.textContent = (etat.intervenant && etat.intervenant.nom_complet) ? etat.intervenant.nom_complet : ('Intervenant #' + etat.id);

        // Bandeau d'état
        const badges = [];
        badges.push('<span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-primary/10 text-primary">'
            + '<i class="fa-solid fa-route"></i> Étape : ' + (etat.etape_courante_label || 'Terminé') + '</span>');
        if (etat.en_retard) {
            badges.push('<span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">'
                + '<i class="fa-solid fa-clock"></i> En retard +' + etat.retard_minutes + ' min</span>');
        }
        if (etat.no_show) {
            badges.push('<span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-red-100 text-red-700">'
                + '<i class="fa-solid fa-user-slash"></i> Absent (no-show)</span>');
        }
        if (etat.statut_affectation === 'en_attente_conducteur') {
            badges.push('<span class="rounded-full bg-amber-100 px-3 py-1.5 text-xs font-semibold text-amber-800">Édition sans conducteur : affectation en attente</span>');
        } else if (etat.statut_affectation === 'conducteur_sans_sequence') {
            badges.push('<span class="rounded-full bg-blue-100 px-3 py-1.5 text-xs font-semibold text-blue-800">Séquence à affecter</span>');
        }
        document.getElementById('parcours-btn-retard')?.classList.toggle('hidden', !etat.can_retard);
        document.getElementById('parcours-btn-noshow')?.classList.toggle('hidden', !etat.can_no_show);
        const assignBox = document.getElementById('parcours-affecter');
        const selectSequence = document.getElementById('parcours-select-sequence');
        const sequenceOptions = Array.isArray(etat.sequences_disponibles) ? etat.sequences_disponibles : [];
        const canAssign = !!etat.can_affecter && etat.statut_affectation === 'conducteur_sans_sequence' && sequenceOptions.length > 0;
        assignBox.classList.toggle('hidden', !canAssign);
        if (canAssign) {
            // Text-only option creation: no HTML injection from sequence titles.
            selectSequence.replaceChildren(...sequenceOptions.map(s => new Option(s.label, String(s.id))));
        }
        // ⛔ Scan obligatoire : l'étape courante est studio mais l'invité n'a
        //    pas encore été scanné à l'accueil.
        const scanRequis = (etat.etapes || []).some(function (e) {
            return e.value === etat.etape_courante && e.bloque_scan;
        });
        if (scanRequis) {
            badges.push('<span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-sky-100 text-sky-800">'
                + '<i class="fa-solid fa-qrcode"></i> Scan QR requis — poste Accueil</span>');
        }
        elBandeau.innerHTML = badges.join('');

        // Étapes
        elSteps.innerHTML = (etat.etapes || []).map(function (e, i) {
            const etatClasse = e.fait ? 'fait' : (e.value === etat.etape_courante ? 'courant' : 'a_venir');
            const icone = e.fait ? 'fa-check' : e.icone;
            const heure = e.fait && e.horodatage ? e.horodatage
                : (e.value === etat.etape_courante ? (e.bloque_scan ? 'scan requis' : 'à valider') : '—');
            const valeurHeure = e.fait && e.date
                ? '<time class="font-mono" datetime="' + e.date + '">' + heure + '</time>'
                : '<span class="font-mono">' + heure + '</span>';

            let action = '';
            if (e.fait) {
                action = '<span class="text-xs text-emerald-700 font-medium inline-flex items-center gap-1">'
                    + '<i class="fa-solid fa-circle-check"></i> validé</span>';
            } else if (e.value === etat.etape_courante && e.bloque_scan && etat.can_scanner) {
                // ⛔ L'arrivée (et les étapes studio tant que le scan n'a pas eu
                //    lieu) se valide UNIQUEMENT au Scanner QR.
                action = '<a href="' + SCAN_URL + '" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-sky-600 text-white text-xs font-semibold hover:bg-sky-700 transition-colors">'
                    + '<i class="fa-solid fa-qrcode text-xs"></i> Scanner le QR</a>';
            } else if (e.value === etat.etape_courante && e.bouton && e.can_valider) {
                action = '<button type="button" class="js-etape-btn inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-primary text-white text-xs font-semibold hover:bg-primary-container hover:text-primary transition-colors"'
                    + ' data-etape="' + e.value + '">'
                    + '<i class="fa-solid ' + e.icone + ' text-xs"></i> ' + e.bouton + '</button>';
            }

            return '<li class="parcours-step ' + etatClasse + '">'
                + '<div class="parcours-dot"><i class="fa-solid ' + icone + '"></i></div>'
                + '<div class="flex-1 min-w-0">'
                +   '<div class="flex items-center justify-between gap-3 flex-wrap">'
                +     '<div class="min-w-0">'
                +       '<div class="text-sm font-semibold text-on-surface">' + (i + 1) + '. ' + e.label + '</div>'
                +       '<div class="text-xs text-on-surface-variant">' + (e.fait ? 'Validée le : ' : 'Validation : ') + valeurHeure + '</div>'
                +     '</div>'
                +     '<div class="shrink-0">' + action + '</div>'
                +   '</div>'
                + '</div>'
                + '</li>';
        }).join('');

        elNotes.textContent = (etat.notes_logistique && etat.notes_logistique.trim()) ? etat.notes_logistique : '—';
    }

    async function charger() {
        try {
            const r = await fetch(urls.etat, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
            const j = await r.json();
            if (j && j.ok) { etat = j.parcours; render(); }
        } catch (e) {
            showToast('Impossible de charger le parcours.', 'error');
        }
    }

    async function poster(url, body) {
        if (busy) return;
        busy = true;
        try {
            const r = await fetch(url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf(),
                },
                credentials: 'same-origin',
                body: JSON.stringify(body || {}),
            });
            const j = await r.json().catch(() => ({}));
            if (!r.ok || !j.ok) throw new Error(j.message || ('Erreur ' + r.status));
            if (j.parcours) { etat = j.parcours; render(); }
            showToast(j.message || 'Effectué.', 'success');
        } catch (e) {
            showToast(e.message || 'Une erreur est survenue.', 'error');
        } finally {
            busy = false;
        }
    }

    // Ouverture depuis les boutons de la liste
    // ------------------------------------------------------------------
    //  PROGRAMMATION D'UN INTERVENANT
    // ------------------------------------------------------------------
    const progModal = document.getElementById('programmer-modal');
    const progUrl   = "{{ route('parcours-intervenants.programmer') }}";
    let bookingBusy = false;

    function openProg() {
        if (!progModal) return;
        // Heure par défaut = maintenant (l'intervenant est alors visible sur la date du jour)
        const heureInput = document.getElementById('prog-heure');
        if (heureInput && !heureInput.value) {
            const d = new Date();
            const p = (n) => String(n).padStart(2, '0');
            heureInput.value = d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate())
                             + 'T' + p(d.getHours()) + ':' + p(d.getMinutes());
        }
        progModal.classList.remove('hidden');
        progModal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    }
    function closeProg(reload) {
        if (!progModal) return;
        progModal.classList.add('hidden');
        progModal.classList.remove('flex');
        document.body.style.overflow = '';
        if (reload) location.reload();
    }

    async function programmer() {
        if (bookingBusy) return;
        const intId = document.getElementById('prog-intervenant');
        const emId  = document.getElementById('prog-emission');
        const heure = document.getElementById('prog-heure');
        const notes = document.getElementById('prog-notes');

        if (!intId.value || !emId.value || !heure.value) {
            showToast('Veuillez sélectionner un intervenant, une émission et une heure de passage.', 'error');
            return;
        }

        const body = {
            intervenant_id: +intId.value,
            emission_id:    +emId.value,
            heure_passage:   heure.value,
            ouvrir_whatsapp: true,
        };
        if (notes && notes.value.trim()) body.notes = notes.value.trim();

        bookingBusy = true;
        const submit = document.getElementById('prog-submit');
        submit.disabled = true;
        // Réserver l'onglet pendant le clic pour éviter le blocage après fetch.
        const whatsappWindow = window.open('about:blank', '_blank');
        if (whatsappWindow) whatsappWindow.opener = null;
        let bookingEnregistre = false;

        try {
            const r = await fetch(progUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf(),
                },
                credentials: 'same-origin',
                body: JSON.stringify(body),
            });
            const j = await r.json().catch(() => ({}));
            if (!r.ok || !j.ok) {
                const erreur = j.errors ? Object.values(j.errors).flat()[0] : null;
                throw new Error(erreur || j.message || ('Erreur ' + r.status));
            }
            bookingEnregistre = true;
            showToast(j.message || 'Booking enregistré.', 'success');

            if (j.message_text) {
                if (whatsappWindow && !whatsappWindow.closed) whatsappWindow.close();
                openMsgModal(j.message_text, j.parcours?.id || null, j.telephone || null);
            } else if (j.whatsapp_url) {
                if (whatsappWindow && !whatsappWindow.closed) {
                    whatsappWindow.location.replace(j.whatsapp_url);
                } else {
                    const result = document.getElementById('prog-whatsapp-result');
                    const lien = document.createElement('a');
                    lien.href = j.whatsapp_url;
                    lien.target = '_blank';
                    lien.rel = 'noopener noreferrer';
                    lien.className = 'font-bold text-secondary underline';
                    lien.textContent = 'Ouvrir WhatsApp avec l’invitation';
                    const retour = document.createElement('a');
                    const destination = new URL(window.location.href);
                    destination.searchParams.set('date', heure.value.slice(0, 10));
                    destination.searchParams.delete('intervenant_id');
                    retour.href = destination.toString();
                    retour.className = 'font-bold text-secondary underline';
                    retour.textContent = 'Afficher le booking';
                    result.replaceChildren(document.createTextNode('Booking enregistré. '), lien, document.createTextNode(' · '), retour);
                    result.classList.remove('hidden');
                    return;
                }
            } else if (whatsappWindow) {
                whatsappWindow.close();
            }

            // On recharge sur la DATE DU PASSAGE programmé, pour que
            // l'intervenant soit visible dans le tableau.
            const datePassage = (heure && heure.value) ? heure.value.slice(0, 10) : null;
            setTimeout(function () {
                if (datePassage) {
                    const u = new URL(window.location.href);
                    u.searchParams.set('date', datePassage);
                    u.searchParams.delete('intervenant_id');
                    window.location.href = u.toString();
                } else {
                    location.reload();
                }
            }, 700);
        } catch (e) {
            if (whatsappWindow && !whatsappWindow.closed) whatsappWindow.close();
            showToast(e.message || 'Une erreur est survenue.', 'error');
        } finally {
            bookingBusy = false;
            submit.disabled = bookingEnregistre;
        }
    }

    const intervenantBooking = new URL(window.location.href).searchParams.get('intervenant_id');
    const choixIntervenant = document.getElementById('prog-intervenant');
    if (intervenantBooking && choixIntervenant
        && Array.from(choixIntervenant.options).some((option) => option.value === intervenantBooking)) {
        choixIntervenant.value = intervenantBooking;
        openProg();
    }

    // Recherche textuelle dans la liste des intervenants du modal de booking
    const searchIntervenant = document.getElementById('prog-intervenant-search');
    if (searchIntervenant && choixIntervenant) {
        searchIntervenant.addEventListener('input', function () {
            const q = this.value.trim().toLowerCase();
            Array.from(choixIntervenant.options).forEach(function (opt) {
                if (!opt.value) return; // placeholder
                const hay = (opt.dataset.search || opt.textContent || '').toLowerCase();
                opt.hidden = q !== '' && !hay.includes(q);
            });
        });
    }

    // Validation d'une étape DIRECTEMENT depuis la colonne du tableau
    async function validerEtapeLigne(btn) {
        if (busy) return;
        busy = true;
        try {
            const r = await fetch(btn.dataset.url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ etape: btn.dataset.etape }),
            });
            const j = await r.json().catch(() => ({}));
            if (!r.ok || !j.ok) throw new Error(j.message || ('Erreur ' + r.status));
            showToast(j.message || 'Étape validée.', 'success');
            setTimeout(() => location.reload(), 800);
        } catch (e) {
            showToast(e.message || 'Une erreur est survenue.', 'error');
        } finally {
            busy = false;
        }
    }

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.js-parcours-btn');
        if (btn) {
            urls = {
                etat:   '/parcours-intervenants/' + btn.dataset.parcoursId + '/etat',
                etape:  btn.dataset.parcoursUrl,
                retard: btn.dataset.parcoursRetard,
                noshow: btn.dataset.parcoursNoshow,
                assign: '/parcours-intervenants/' + btn.dataset.parcoursId + '/affecter-sequence',
            };
            etat = null;
            elSteps.innerHTML = '<li class="text-center text-on-surface-variant text-sm py-6">Chargement…</li>';
            openModal();
            charger();
            return;
        }

        if (e.target.closest('[data-parcours-close]')) {
            closeModal();
            return;
        }

        const etapeBtn = e.target.closest('.js-etape-btn');
        if (etapeBtn) {
            poster(urls.etape, { etape: etapeBtn.dataset.etape });
            return;
        }

        if (e.target.closest('#parcours-btn-affecter')) {
            const sequenceId = Number(document.getElementById('parcours-select-sequence')?.value || 0);
            if (sequenceId) poster(urls.assign, { sequence_id: sequenceId });
            return;
        }

        if (e.target.closest('#parcours-btn-retard')) {
            poster(urls.retard, {});
            return;
        }
        if (e.target.closest('#parcours-btn-noshow')) {
            const confirmation = window.TvAlert?.confirm
                ? window.TvAlert.confirm({
                    title: 'Marquer cet intervenant comme absent (no-show) ?',
                    text: 'Cette action sera enregistrée dans son parcours.',
                    confirmText: 'Marquer absent',
                })
                : Promise.resolve({ isConfirmed: window.confirm('Marquer cet intervenant comme absent (no-show) ?') });

            confirmation.then((r) => { if (r && r.isConfirmed) poster(urls.noshow, {}); });
            return;
        }

        // --- Programmation ---
        if (e.target.closest('#btn-programmer')) { openProg(); return; }
        if (e.target.closest('[data-programmer-close]')) { closeProg(false); return; }
        if (e.target.closest('#prog-submit')) { programmer(); return; }

        // --- Validation d'étape en ligne (colonne du tableau) ---
        const ligneBtn = e.target.closest('.js-etape-ligne-btn');
        if (ligneBtn) { validerEtapeLigne(ligneBtn); return; }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
        if (e.key === 'Escape' && progModal && !progModal.classList.contains('hidden')) closeProg(false);
    });
    // --- Modal message WhatsApp ---
    const msgModal = document.getElementById('message-modal');
    let currentMsgParcoursId = null;
    let currentMsgTelephone = null;
    function openMsgModal(message, parcoursId, telephone) {
        if (!msgModal) return;
        currentMsgParcoursId = parcoursId;
        currentMsgTelephone = telephone;
        const ta = document.getElementById('msg-text');
        if (ta) ta.value = message || '';
        const angles = document.getElementById('msg-angles');
        if (angles) angles.value = '';
        const status = document.getElementById('msg-ai-status');
        if (status) status.textContent = '';
        msgModal.classList.remove('hidden');
        msgModal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    }
    function closeMsgModal() {
        if (!msgModal) return;
        msgModal.classList.add('hidden');
        msgModal.classList.remove('flex');
        document.body.style.overflow = '';
    }
    function numeroWhatsApp(tel) {
        if (!tel) return null;
        const digits = String(tel).replace(/\D+/g, '');
        if (digits === '') return null;
        if (digits.startsWith('241')) return digits.length >= 10 ? digits : null;
        if (digits.length === 9 && digits.startsWith('0')) return '241' + digits.slice(1);
        if (digits.length === 8) return '241' + digits;
        return digits.length >= 10 ? digits : null;
    }

    const msgAiBtn = document.getElementById('msg-ai-suggest');
    if (msgAiBtn) {
        msgAiBtn.addEventListener('click', async function () {
            if (!currentMsgParcoursId) return;
            const angles = document.getElementById('msg-angles')?.value || '';
            const status = document.getElementById('msg-ai-status');
            const ta = document.getElementById('msg-text');
            msgAiBtn.disabled = true;
            if (status) status.textContent = 'Génération en cours…';
            try {
                const r = await fetch('/parcours-intervenants/' + currentMsgParcoursId + '/suggest-message', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() },
                    credentials: 'same-origin',
                    body: JSON.stringify({ angles: angles }),
                });
                const j = await r.json().catch(() => ({}));
                if (!r.ok || !j.ok) throw new Error(j.message || 'Erreur IA');
                if (ta && j.suggestion) ta.value = j.suggestion;
                if (status) status.textContent = 'Suggestion générée.';
            } catch (e) {
                if (status) status.textContent = e.message || 'Erreur IA';
            } finally {
                msgAiBtn.disabled = false;
            }
        });
    }

    const msgSendBtn = document.getElementById('msg-send-whatsapp');
    if (msgSendBtn) {
        msgSendBtn.addEventListener('click', function () {
            const ta = document.getElementById('msg-text');
            const text = (ta?.value || '').trim();
            if (!text) { showToast('Le message est vide.', 'error'); return; }
            const num = numeroWhatsApp(currentMsgTelephone);
            if (!num) { showToast('Aucun numéro WhatsApp exploitable.', 'error'); return; }
            const url = 'https://wa.me/' + num + '?text=' + encodeURIComponent(text);
            window.open(url, '_blank', 'noopener');
            if (currentMsgParcoursId) {
                fetch('/parcours-intervenants/' + currentMsgParcoursId + '/whatsapp', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() },
                    credentials: 'same-origin',
                }).catch(() => {});
            }
            closeMsgModal();
            location.reload();
        });
    }

    document.querySelectorAll('[data-message-close]').forEach(function (el) {
        el.addEventListener('click', function () { closeMsgModal(); });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && msgModal && !msgModal.classList.contains('hidden')) closeMsgModal();
    });

})();
</script>
@endpush
