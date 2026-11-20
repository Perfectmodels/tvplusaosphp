@extends('layouts.app')
@php
    use App\Enums\LangueEnum;
    use Illuminate\Support\Facades\Gate;
    use Illuminate\Support\Carbon;

    $domaines = is_array($intervenant->domaines) ? $intervenant->domaines : ($intervenant->domaines ? json_decode($intervenant->domaines, true) ?? [] : []);
    $langues = is_array($intervenant->langues) ? $intervenant->langues : ($intervenant->langues ? json_decode($intervenant->langues, true) ?? [] : []);
    $lastScanDate = $intervenant->last_scan_at ? Carbon::parse($intervenant->last_scan_at)->translatedFormat('d F Y') : 'Jamais scanné';
    $totalScans = $intervenant->scans ? $intervenant->scans->count() : \App\Models\IntervenantScanLog::where('intervenant_id', $intervenant->id)->count();

    $pageTitle = 'Fiche Intervenant';
    $pageSubtitle = 'Intervenants / ' . $intervenant->nom_complet . ' · Code ' . $intervenant->code_intervenant;
    $showGlobalSearch = true;
    $headerActions = [
        ['label' => 'Retour liste', 'icon' => 'fa-solid fa-arrow-left', 'href' => route('intervenants.index'), 'variant' => 'ghost'],
        ['label' => 'Badge QR', 'icon' => 'fa-solid fa-id-card', 'href' => route('intervenants.qr', $intervenant), 'variant' => 'ghost'],
    ];
    if (Gate::check('update', $intervenant)) {
        $headerActions[] = ['label' => 'Éditer', 'icon' => 'fa-solid fa-pen', 'href' => route('intervenants.edit', $intervenant), 'variant' => 'primary'];
    }
    if (Gate::check('scan', \App\Models\Intervenant::class)) {
        $headerActions[] = ['label' => 'Pointage QR', 'icon' => 'fa-solid fa-qrcode', 'href' => route('scanner-qr.index', ['intervenant' => $intervenant->id]), 'variant' => 'ghost'];
    }
@endphp
@section('content')

<!-- ==================== HERO CARD 1:1 fiche_intervenant.html ==================== -->
<div class="bg-surface rounded-xl shadow-sm border border-outline-variant p-6 flex flex-col lg:flex-row gap-8 relative mb-8">
    <div class="w-48 h-48 rounded-lg overflow-hidden shrink-0 bg-surface-container ring-2 ring-white shadow-inner self-center lg:self-start">
        <img
            class="w-full h-full object-cover"
            src="{{ $intervenant->photoUrl }}"
            alt="{{ $intervenant->nom_complet }}"
            onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name={{ urlencode($intervenant->nom_complet) }}&background=0054cd&color=fff&size=256&bold=true';"
        />
    </div>
    <div class="flex-1 flex flex-col justify-between">
        <div>
            <div class="flex items-center flex-wrap gap-4 mb-2">
                <h3 class="font-headline-lg text-headline-lg text-primary-container">{{ $intervenant->nom_complet }}</h3>
                @php
                    $s = $intervenant->disponibilite;
                    $statColor = match(true) {
                        !$s => 'bg-gray-100 text-gray-700 border-gray-200',
                        $s->value === 'disponible' => 'bg-[#e6f4ea] text-[#137333] border-[#ceead6]',
                        $s->value === 'occupe'     => 'bg-amber-50 text-amber-700 border-amber-200',
                        default                     => 'bg-red-50 text-red-700 border-red-200',
                    };
                    $dotColor = match(true) {
                        !$s => 'bg-gray-400',
                        $s->value === 'disponible' => 'bg-[#137333]',
                        $s->value === 'occupe'     => 'bg-amber-500',
                        default                     => 'bg-red-600',
                    };
                @endphp
                <span class="px-3 py-1 {{ $statColor }} rounded-full text-label-sm font-label-sm flex items-center gap-1.5 border">
                    <span class="w-2 h-2 rounded-full {{ $dotColor }}"></span>
                    {{ $s ? preg_replace('/^[^\s]+\s+/u', '', $s->label()) : 'Inconnu' }}
                </span>
                @if($intervenant->type)
                    <span class="inline-flex px-2.5 py-1 rounded-md text-xs {!! $intervenant->type->badgeClass() !!} border">
                        {!! $intervenant->type->label() !!}
                    </span>
                @endif
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-y-2 gap-x-8 text-body-sm font-body-sm text-on-surface-variant mb-4">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">person</span>
                    <span>Fonction : <span class="text-on-surface font-medium">{{ $intervenant->fonction ?? '—' }}</span></span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">corporate_fare</span>
                    <span>Organisme : <span class="text-on-surface font-medium">{{ $intervenant->organisme ?? '—' }}</span></span>
                </div>
                @if($intervenant->poste)
                <div class="flex items-center gap-2 sm:col-span-2">
                    <span class="material-symbols-outlined text-[18px] text-secondary">workspace_premium</span>
                    <span>Poste : <span class="text-on-surface font-medium">{{ $intervenant->poste }}</span></span>
                </div>
                @endif
            </div>

            @if($domaines)
            <div class="flex flex-wrap gap-2 mb-6">
                @foreach(array_slice($domaines, 0, 6) as $d)
                    <span class="px-3 py-1 bg-secondary-container/10 text-secondary rounded-md text-label-sm font-label-sm border border-secondary-container/20">{{ $d }}</span>
                @endforeach
                @if(count($domaines) > 6)
                    <span class="px-3 py-1 bg-surface-container-highest text-on-surface-variant rounded-md text-label-sm font-label-sm">+{{ count($domaines) - 6 }}</span>
                @endif
            </div>
            @endif
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-6 border-t border-outline-variant pt-4">
            <div class="flex items-start gap-2">
                <span class="material-symbols-outlined text-outline mt-0.5">location_on</span>
                <div class="text-body-sm">
                    <p class="text-on-surface-variant text-xs uppercase tracking-wider mb-0.5">Localisation</p>
                    <p class="font-medium text-on-surface">
                        {{ $intervenant->ville_residence ? : 'Non renseignée' }}
                        @if($intervenant->ville_residence && $intervenant->pays_residence) · @endif
                        {{ $intervenant->pays_residence ?? '' }}
                    </p>
                </div>
            </div>
            <div class="flex items-start gap-2">
                <span class="material-symbols-outlined text-outline mt-0.5">event</span>
                <div class="text-body-sm">
                    <p class="text-on-surface-variant text-xs uppercase tracking-wider mb-0.5">Dernier passage</p>
                    <p class="font-medium text-on-surface">{{ $lastScanDate }}</p>
                </div>
            </div>
            <div class="flex items-start gap-2">
                <span class="material-symbols-outlined text-outline mt-0.5">qr_code_scanner</span>
                <div class="text-body-sm">
                    <p class="text-on-surface-variant text-xs uppercase tracking-wider mb-0.5">Scans enregistrés</p>
                    <p class="font-medium text-on-surface">{{ $totalScans }}</p>
                </div>
            </div>
            <div class="flex items-start gap-2">
                <span class="material-symbols-outlined text-outline mt-0.5">badge</span>
                <div class="text-body-sm">
                    <p class="text-on-surface-variant text-xs uppercase tracking-wider mb-0.5">Identifiant</p>
                    <p class="font-mono font-bold text-primary-container text-sm">{{ $intervenant->code_intervenant }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Colonne 3 BOUTONS Hero 1:1 prototype (Booker/WhatsApp/Historique) -->
    <div class="flex flex-col sm:flex-row lg:flex-col gap-3 lg:w-64 shrink-0 w-full">
        <a id="btn-booker" href="{{ route('parcours-intervenants.index', ['intervenant_id' => $intervenant->id]) }}" class="w-full flex justify-center items-center gap-2 px-4 py-2.5 bg-secondary text-on-secondary rounded-lg text-label-md font-label-md hover:bg-on-secondary-fixed-variant transition-colors shadow-sm">
            <span class="material-symbols-outlined text-[18px]">event_available</span>
            Booker pour une émission
        </a>
        @if($intervenant->telephone_1 || $intervenant->phone)
        <form method="POST" action="{{ route('intervenants.whatsapp', $intervenant) }}" target="_blank" class="w-full">
            @csrf
            <button type="submit" class="w-full flex justify-center items-center gap-2 px-4 py-2.5 bg-surface border border-outline-variant rounded-lg text-label-md font-label-md text-on-surface hover:bg-surface-container-highest transition-colors">
                <span class="material-symbols-outlined text-[18px] text-[#25d366]">chat</span>
                Envoyer WhatsApp
            </button>
        </form>
        @endif
        <a href="#historique" class="w-full flex justify-center items-center gap-2 px-4 py-2.5 bg-surface border border-outline-variant rounded-lg text-label-md font-label-md text-on-surface hover:bg-surface-container-highest transition-colors">
            <span class="material-symbols-outlined text-[18px]">history</span>
            Historique des passages
        </a>
    </div>
</div>

<!-- ==================== BENTO GRID 12 COLS 1:1 fiche_intervenant.html ==================== -->
<div class="grid grid-cols-12 gap-6">
    <!-- ===== COLONNE GAUCHE span-4 ===== -->
    <div class="col-span-12 lg:col-span-4 flex flex-col gap-6">
        <!-- Contact Info -->
        <div class="bg-surface rounded-xl shadow-sm border border-outline-variant p-6">
            <h4 class="font-headline-sm text-headline-sm text-primary-container flex items-center gap-2 mb-6">
                <span class="material-symbols-outlined">contact_page</span>
                Informations de contact
            </h4>
            <div class="space-y-4 text-body-sm font-body-sm">
                <div class="flex justify-between items-start gap-3">
                    <div class="flex items-center gap-3 text-on-surface-variant w-28 shrink-0">
                        <span class="material-symbols-outlined text-[18px]">call</span>
                        <span>Téléphone</span>
                    </div>
                    <span class="font-medium text-on-surface text-right">
                        @if($intervenant->telephone_1)
                            <a href="tel:{{ $intervenant->telephone_1 }}" class="text-secondary hover:underline">{{ $intervenant->telephone_1 }}</a>
                            @if($intervenant->telephone_2)<div class="text-xs text-on-surface-variant mt-0.5">{{ $intervenant->telephone_2 }} (bis)</div>@endif
                        @else — @endif
                    </span>
                </div>
                <div class="flex justify-between items-start gap-3">
                    <div class="flex items-center gap-3 text-on-surface-variant w-28 shrink-0">
                        <span class="material-symbols-outlined text-[18px]">mail</span>
                        <span>E-mail</span>
                    </div>
                    <span class="font-medium text-secondary text-right break-all">
                        @if($intervenant->email)<a href="mailto:{{ $intervenant->email }}" class="hover:underline">{{ $intervenant->email }}</a>@else — @endif
                    </span>
                </div>
                <div class="flex justify-between items-start gap-3">
                    <div class="flex items-center gap-3 text-on-surface-variant w-28 shrink-0">
                        <span class="material-symbols-outlined text-[18px]">location_on</span>
                        <span>Adresse</span>
                    </div>
                    <span class="font-medium text-on-surface text-right">{{ $intervenant->adresse_physique ?? '—' }}</span>
                </div>
                <div class="flex justify-between items-start gap-3">
                    <div class="flex items-center gap-3 text-on-surface-variant w-28 shrink-0">
                        <span class="material-symbols-outlined text-[18px]">language</span>
                        <span>Langues</span>
                    </div>
                    <span class="font-medium text-on-surface text-right">
                        @if($langues)
                            @foreach($langues as $k => $l)
                                {{ LangueEnum::tryFrom($l)?->label() ?? $l }}{{ $k < count($langues) - 1 ? ', ' : '' }}
                            @endforeach
                        @else — @endif
                    </span>
                </div>
                <div class="flex justify-between items-start gap-3">
                    <div class="flex items-center gap-3 text-on-surface-variant w-28 shrink-0">
                        <span class="material-symbols-outlined text-[18px]">person</span>
                        <span>Civilité</span>
                    </div>
                    <span class="font-medium text-on-surface text-right">
                        {{ $intervenant->civilite?->label() ?? '—' }}
                    </span>
                </div>
                @if($intervenant->date_naissance)
                <div class="flex justify-between items-start gap-3">
                    <div class="flex items-center gap-3 text-on-surface-variant w-28 shrink-0">
                        <span class="material-symbols-outlined text-[18px]">cake</span>
                        <span>Âge</span>
                    </div>
                    <span class="font-medium text-on-surface text-right">
                        {{ Carbon::parse($intervenant->date_naissance)->age }} ans
                        <div class="text-xs text-on-surface-variant">{{ $intervenant->lieu_naissance ? 'Né à ' . $intervenant->lieu_naissance : '' }}</div>
                    </span>
                </div>
                @endif
            </div>
        </div>

        <!-- Carte QR SVG inline (spécifique implémentation) -->
        <div id="qr-inline-card" class="bg-surface rounded-xl shadow-sm border border-outline-variant p-6">
            <h4 class="font-headline-sm text-headline-sm text-primary-container flex items-center gap-2 mb-4">
                <span class="material-symbols-outlined text-purple-600">qr_code_2</span>
                QR d'invitation
            </h4>
            <div class="flex flex-col items-center bg-gradient-to-br from-primary-container via-primary-container/95 to-secondary/90 p-5 rounded-xl text-white relative overflow-hidden">
                <div class="absolute -top-10 -right-10 w-24 h-24 rounded-full bg-white/10 blur-xl"></div>
                <div class="bg-white p-4 rounded-xl shadow-inner ring-2 ring-white/20 relative">
                    @php
                        try {
                            echo \SimpleSoftwareIO\QrCode\Facades\QrCode::size(220)->errorCorrection('H')->margin(0)->generate($intervenant->urlQrAcces());
                        } catch(\Throwable) {
                            echo '<div class="w-[220px] h-[220px] flex items-center justify-center text-xs text-gray-500 bg-gray-100 rounded-lg">QR indisponible</div>';
                        }
                    @endphp
                </div>
                <div class="mt-3 font-mono text-sm tracking-widest bg-white/10 px-4 py-1 rounded-lg border border-white/20 w-full text-center">
                    {{ $intervenant->code_intervenant }}
                </div>
            </div>
            <div class="flex gap-2 mt-4">
                <a href="{{ route('intervenants.qr', $intervenant) }}" class="flex-1 flex justify-center items-center gap-2 px-3 py-2.5 min-h-[44px] bg-surface-container rounded-lg text-label-sm font-label-md text-on-surface border border-outline-variant hover:bg-surface-container-high">
                    <span class="material-symbols-outlined text-sm">open_in_new</span>
                    Plein écran
                </a>
                <button type="button" onclick="window.open('{{ route('intervenants.qr.svg', $intervenant) }}','_blank')" class="flex-1 flex justify-center items-center gap-2 px-3 py-2.5 min-h-[44px] bg-[#0054cd] text-white rounded-lg text-label-sm font-label-md hover:bg-secondary">
                    <span class="material-symbols-outlined text-sm">print</span>
                    Imprimer
                </button>
            </div>
        </div>

        <!-- Spécialités -->
        @if($domaines)
        <div class="bg-surface rounded-xl shadow-sm border border-outline-variant p-6">
            <h4 class="font-headline-sm text-headline-sm text-primary-container flex items-center gap-2 mb-4">
                <span class="material-symbols-outlined">local_offer</span>
                Domaines d'expertise
            </h4>
            <div class="flex flex-wrap gap-2">
                @foreach($domaines as $d)
                    <span class="px-3 py-1.5 bg-secondary-container/10 text-secondary rounded-md text-label-sm font-label-sm border border-secondary-container/20">{{ $d }}</span>
                @endforeach
            </div>
        </div>
        @endif

        <!-- Notes internes -->
        @if($intervenant->observations_admin || $intervenant->notes)
        <div class="bg-surface rounded-xl shadow-sm border border-outline-variant p-6">
            <h4 class="font-headline-sm text-headline-sm text-primary-container flex items-center gap-2 mb-4">
                <span class="material-symbols-outlined">note_alt</span>
                Notes internes
            </h4>
            @if($intervenant->observations_admin)
                <div class="bg-primary-container/5 border border-primary-container/20 p-4 rounded-lg text-body-sm font-body-sm text-on-surface mb-3">
                    <div class="text-xs uppercase tracking-wider text-primary-container font-bold mb-1">Observations Admin</div>
                    <div class="whitespace-pre-wrap">{{ $intervenant->observations_admin }}</div>
                </div>
            @endif
            @if($intervenant->notes)
                <div class="bg-surface-container-low p-4 rounded-lg border border-outline-variant text-body-sm font-body-sm text-on-surface-variant whitespace-pre-wrap">
                    {{ $intervenant->notes }}
                </div>
            @endif
        </div>
        @endif

        <!-- Logistique (champs DB supplémentaires non présents prototype) -->
        @if($intervenant->taille_habillement || $intervenant->regime_alimentaire || $intervenant->sujets_sensibles || $intervenant->presentation_courte)
        <div class="bg-surface rounded-xl shadow-sm border border-outline-variant p-6">
            <h4 class="font-headline-sm text-headline-sm text-primary-container flex items-center gap-2 mb-6">
                <span class="material-symbols-outlined text-purple-500">hospitality</span>
                Logistique & Plateau
            </h4>
            <div class="grid grid-cols-2 gap-4 text-body-sm font-body-sm mb-4">
                @if($intervenant->taille_habillement)
                <div>
                    <p class="text-xs uppercase tracking-wider text-on-surface-variant mb-1 font-semibold">Taille habillement</p>
                    <p class="font-medium text-on-surface">{{ $intervenant->taille_habillement }}</p>
                </div>
                @endif
                @if($intervenant->regime_alimentaire)
                <div>
                    <p class="text-xs uppercase tracking-wider text-on-surface-variant mb-1 font-semibold">Régime alimentaire</p>
                    <p class="font-medium text-on-surface">{{ $intervenant->regime_alimentaire }}</p>
                </div>
                @endif
            </div>
            @if($intervenant->sujets_sensibles)
            <div class="mb-4">
                <p class="text-xs uppercase tracking-wider text-error font-bold mb-1 flex items-center gap-1">
                    <span class="material-symbols-outlined text-xs">warning</span>
                    Sujets sensibles (à éviter)
                </p>
                <div class="bg-error-container/20 border border-error-container p-3 rounded-lg text-sm">{{ $intervenant->sujets_sensibles }}</div>
            </div>
            @endif
            @if($intervenant->presentation_courte)
            <div>
                <p class="text-xs uppercase tracking-wider text-on-surface-variant mb-1 font-semibold">Présentation On-Air</p>
                <div class="bg-surface-container-low p-3 rounded-lg border border-outline-variant text-sm">{{ $intervenant->presentation_courte }}</div>
            </div>
            @endif
        </div>
        @endif
    </div>

    <!-- ===== COLONNE DROITE span-8 ===== -->
    <div class="col-span-12 lg:col-span-8 flex flex-col gap-6">
        <!-- Disponibilité & réservation -->
        <div class="bg-surface rounded-xl shadow-sm border border-outline-variant p-6">
            <h4 class="font-headline-sm text-headline-sm text-primary-container flex items-center gap-2 mb-6">
                <span class="material-symbols-outlined">calendar_month</span>
                Disponibilité &amp; réservation
            </h4>
            <div class="bg-surface-container-low rounded-lg border border-outline-variant p-4 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4 text-body-sm font-body-sm">
                    <div>
                        <p class="text-on-surface-variant text-xs uppercase tracking-wider mb-1">Statut actuel</p>
                        <div class="inline-flex items-center gap-1.5 mt-1">
                            <span class="w-2.5 h-2.5 rounded-full {{ $dotColor }}"></span>
                            <span class="font-medium text-secondary">{{ $s ? preg_replace('/^[^\s]+\s+/u', '', $s->label()) : 'Inconnu' }}</span>
                        </div>
                    </div>
                    <div>
                        <p class="text-on-surface-variant text-xs uppercase tracking-wider mb-1">Dernière présence</p>
                        <p class="font-medium text-on-surface">{{ $lastScanDate }}</p>
                    </div>
                    <div>
                        <p class="text-on-surface-variant text-xs uppercase tracking-wider mb-1">Scans plateau</p>
                        <p class="font-medium text-on-surface">{{ $totalScans }} passage{{ $totalScans > 1 ? 's' : '' }}</p>
                    </div>
                    <div>
                        <p class="text-on-surface-variant text-xs uppercase tracking-wider mb-1">Fiche créée le</p>
                        <p class="font-medium text-on-surface">{{ $intervenant->created_at?->translatedFormat('d/m/Y') }}</p>
                    </div>
                    <div>
                        <p class="text-on-surface-variant text-xs uppercase tracking-wider mb-1">Fiche</p>
                        <span class="inline-flex items-center gap-1 mt-1 {{ $intervenant->is_active ? 'bg-success/10 text-success' : 'bg-error/10 text-error' }} px-2 py-0.5 rounded text-xs font-semibold border">
                            <span class="w-1.5 h-1.5 rounded-full {{ $intervenant->is_active ? 'bg-success' : 'bg-error' }}"></span>
                            {{ $intervenant->is_active ? 'Active' : 'Blacklisté' }}
                        </span>
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('parcours-intervenants.index', ['intervenant_id' => $intervenant->id]) }}" class="flex items-center gap-2 px-5 py-2.5 min-h-[44px] bg-surface border border-secondary text-secondary rounded-lg text-label-md font-label-md hover:bg-secondary-container/10 transition-colors">
                    <span class="material-symbols-outlined text-[18px]">check_circle</span>
                    Vérifier disponibilité
                </a>
                @can('update', $intervenant)
                <a href="{{ route('intervenants.edit', $intervenant) }}" class="flex items-center gap-2 px-5 py-2.5 min-h-[44px] bg-surface border border-outline-variant rounded-lg text-label-md font-label-md text-on-surface hover:bg-surface-container-highest transition-colors">
                    <span class="material-symbols-outlined text-[18px]">edit_document</span>
                    Mettre à jour la fiche
                </a>
                @endcan
                @can('delete', $intervenant)
                <form method="POST" action="{{ route('intervenants.destroy', $intervenant) }}"
                      data-confirm-delete="Supprimer {{ e($intervenant->nom_complet) }} ?"
                      data-confirm-delete-text="Cette action est irréversible. Sa photo, ses documents et son badge QR seront supprimés."
                      class="inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="flex items-center gap-2 px-5 py-2.5 min-h-[44px] bg-surface border border-error/40 rounded-lg text-label-md font-label-md text-error hover:bg-error hover:text-white transition-colors">
                        <span class="material-symbols-outlined text-[18px]">delete</span>
                        Supprimer l'intervenant
                    </button>
                </form>
                @endcan
                @if($intervenant->biographie_longue)
                <button type="button" id="btn-toggle-biog" class="flex items-center gap-2 px-5 py-2.5 min-h-[44px] bg-surface border border-outline-variant rounded-lg text-label-md font-label-md text-on-surface hover:bg-surface-container-highest transition-colors">
                    <span class="material-symbols-outlined text-[18px]">menu_book</span>
                    Voir la biographie longue
                </button>
                @endif
            </div>
            @if($intervenant->biographie_longue)
            <div id="biog-full" class="mt-5 bg-surface-container-low p-5 rounded-xl border border-outline-variant hidden">
                <h5 class="font-label-md uppercase tracking-wider text-on-surface-variant mb-2">Biographie détaillée</h5>
                <div class="text-body-sm text-on-surface whitespace-pre-wrap">{{ $intervenant->biographie_longue }}</div>
            </div>
            @endif
        </div>

        <!-- Historique des passages TV+ -->
        <div id="historique" class="bg-surface rounded-xl shadow-sm border border-outline-variant p-6 flex-1 flex flex-col">
            <h4 class="font-headline-sm text-headline-sm text-primary-container flex items-center gap-2 mb-4">
                <span class="material-symbols-outlined">history</span>
                Historique des passages TV+
                @if($totalScans > 0)
                    <span class="ml-auto text-xs px-2.5 py-0.5 rounded-full bg-surface-container-highest text-on-surface-variant font-bold">{{ $totalScans }}</span>
                @endif
            </h4>
            <div class="overflow-x-auto">
                @php
                    $scansLogs = $intervenant->relationLoaded('scans')
                        ? $intervenant->scans->sortByDesc('scanned_at')->values()
                        : \App\Models\IntervenantScanLog::where('intervenant_id', $intervenant->id)->orderByDesc('scanned_at')->limit(10)->get();
                @endphp
                @if($scansLogs->count() > 0)
                <table data-mobile-cards class="w-full text-left border-collapse text-body-sm font-body-sm">
                    <thead>
                    <tr class="border-b border-outline-variant text-on-surface-variant">
                        <th class="py-3 font-medium">Date &amp; Heure</th>
                        <th class="py-3 font-medium">Poste de scan</th>
                        <th class="py-3 font-medium">Agent</th>
                        <th class="py-3 font-medium">Statut</th>
                    </tr>
                    </thead>
                    <tbody class="text-on-surface">
                    @foreach($scansLogs as $sc)
                        <tr class="border-b border-outline-variant/50 hover:bg-surface-container-low transition-colors">
                            <td class="py-3 flex items-center gap-2 whitespace-nowrap">
                                <span class="material-symbols-outlined text-[16px] text-outline">schedule</span>
                                {{ $sc->scanned_at?->translatedFormat('d/m/Y H:i') ?? '—' }}
                            </td>
                            <td class="py-3">{!! $sc->poste?->badge() ?? '<span class="text-gray-500">Inconnu</span>' !!}</td>
                            <td class="py-3 font-medium">
                                @if($sc->scannerBy)
                                    <span class="inline-flex items-center gap-2">
                                        <img class="w-6 h-6 rounded-full" src="{{ $sc->scannerBy->photo ?? 'https://ui-avatars.com/api/?name='.urlencode($sc->scannerBy->name ?? '?').'&background=001a41&color=fff&size=64&bold=true' }}" alt="">
                                        {{ $sc->scannerBy->name ?? '—' }}
                                    </span>
                                @else — @endif
                            </td>
                            <td class="py-3">
                                <span class="text-success text-xs inline-flex items-center gap-1 font-semibold">
                                    <span class="w-1.5 h-1.5 rounded-full bg-success"></span>
                                    OK
                                </span>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                @else
                <div class="py-10 text-center text-sm text-on-surface-variant bg-surface-container-low/40 rounded-lg border border-dashed border-outline-variant/60">
                    <div class="material-symbols-outlined mx-auto text-5xl text-outline/80 mb-3">event_busy</div>
                    <h5 class="font-headline-md text-on-surface mb-1">Aucun passage enregistré</h5>
                    <p class="text-sm max-w-sm mx-auto mb-4">Ce intervenant n'a pas encore été scanné sur les postes (Accueil, Maquillage, Plateau).</p>
                    @can('scan', \App\Models\Intervenant::class)
                    <a href="{{ route('scanner-qr.index', ['intervenant' => $intervenant->id]) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-primary-container text-white rounded-lg text-sm hover:bg-secondary transition-colors">
                        <span class="material-symbols-outlined text-sm">qr_code_scanner</span>
                        Ouvrir le scanner
                    </a>
                    @endcan
                </div>
                @endif
            </div>
        </div>

        <!-- Actions rapides (Bottom) -->
        <div class="bg-surface rounded-xl shadow-sm border border-outline-variant p-6 mt-auto">
            <h4 class="font-headline-sm text-headline-sm text-primary-container flex items-center gap-2 mb-4">
                <span class="material-symbols-outlined">bolt</span>
                Actions rapides
            </h4>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('parcours-intervenants.index', ['intervenant_id' => $intervenant->id]) }}" class="flex items-center gap-2 px-4 py-2.5 min-h-[44px] bg-surface border border-outline-variant rounded-lg text-label-md font-label-md text-on-surface hover:bg-surface-container-highest transition-colors">
                    <span class="material-symbols-outlined text-[18px]">event</span>
                    Booker
                </a>
                @if($intervenant->telephone_1)
                <a href="tel:{{ $intervenant->telephone_1 }}" class="flex items-center gap-2 px-4 py-2.5 min-h-[44px] bg-surface border border-outline-variant rounded-lg text-label-md font-label-md text-on-surface hover:bg-surface-container-highest transition-colors">
                    <span class="material-symbols-outlined text-[18px] text-success">call</span>
                    Appeler
                </a>
                @endif
                <a href="{{ route('intervenants.qr.regenerate', $intervenant) }}" onclick="event.preventDefault();document.getElementById('regen-qr-form').submit();" class="flex items-center gap-2 px-4 py-2.5 min-h-[44px] bg-surface border border-outline-variant rounded-lg text-label-md font-label-md text-on-surface hover:bg-surface-container-highest transition-colors">
                    <span class="material-symbols-outlined text-[18px] text-amber-600">replay</span>
                    Régénérer QR
                </a>
                <a href="{{ route('intervenants.qr', $intervenant) }}" class="flex items-center gap-2 px-4 py-2.5 min-h-[44px] bg-surface border border-outline-variant rounded-lg text-label-md font-label-md text-on-surface hover:bg-surface-container-highest transition-colors">
                    <span class="material-symbols-outlined text-[18px]">qr_code</span>
                    Générer QR Code
                </a>
                <a href="{{ route('intervenants.index') }}" class="flex items-center gap-2 px-4 py-2.5 min-h-[44px] bg-surface border border-outline-variant rounded-lg text-label-md font-label-md text-on-surface hover:bg-surface-container-highest transition-colors ml-auto">
                    <span class="material-symbols-outlined text-[18px]">picture_as_pdf</span>
                    Exporter PDF
                </a>
            </div>
            <form id="regen-qr-form" method="POST" action="{{ route('intervenants.qr.regenerate', $intervenant) }}" class="hidden">@csrf</form>
        </div>
    </div>
</div>

{{-- Modale QR affichée juste après la création de l’intervenant --}}
@if(request()->boolean('qr'))
<dialog id="qr-created-dialog" class="w-[min(94vw,32rem)] rounded-2xl border-0 bg-surface p-0 text-on-surface shadow-2xl backdrop:bg-black/70">
    <div class="p-6 sm:p-7">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold">QR d’invitation généré</h2>
                <p class="mt-1 text-sm text-on-surface-variant">{{ $intervenant->nom_complet }} · {{ $intervenant->code_intervenant }}</p>
            </div>
            <button type="button" data-qr-close class="inline-flex h-10 w-10 items-center justify-center rounded-full text-on-surface-variant hover:bg-surface-container-high" aria-label="Fermer">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <div class="mt-5 flex justify-center rounded-2xl bg-gradient-to-br from-primary-container via-primary-container/95 to-secondary/90 p-6">
            <div class="rounded-2xl bg-white p-4 shadow-inner ring-2 ring-white/25">
                @php
                    try {
                        echo \SimpleSoftwareIO\QrCode\Facades\QrCode::size(240)->errorCorrection('H')->margin(0)->generate($intervenant->urlQrAcces());
                    } catch (\Throwable) {
                        echo '<div class="flex h-[240px] w-[240px] items-center justify-center rounded-lg bg-gray-100 text-xs text-gray-500">QR indisponible</div>';
                    }
                @endphp
            </div>
        </div>
        <p class="mt-4 break-all rounded-xl bg-surface-container-low p-3 text-xs text-on-surface-variant" aria-label="Lien d’invitation">{{ $intervenant->urlInvitationComplete() }}</p>
        <div class="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <a href="{{ route('intervenants.qr', $intervenant) }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-outline-variant px-4 font-semibold hover:bg-surface-container-low">
                <span class="material-symbols-outlined" style="font-size:20px;">open_in_new</span>
                Plein écran
            </a>
            <button type="button" data-qr-close class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-primary px-4 font-semibold text-on-primary hover:brightness-110">
                <span class="material-symbols-outlined" style="font-size:20px;">check</span>
                Terminer
            </button>
        </div>
    </div>
</dialog>
@endif

<div class="h-10"></div>

<!-- JS interactions -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const btnBiog = document.getElementById('btn-toggle-biog');
    const biog = document.getElementById('biog-full');
    if (btnBiog && biog) {
        btnBiog.addEventListener('click', () => biog.classList.toggle('hidden'));
    }

    const qrDialog = document.getElementById('qr-created-dialog');
    if (qrDialog) {
        qrDialog.querySelectorAll('[data-qr-close]').forEach((button) => {
            button.addEventListener('click', () => qrDialog.close());
        });
        if (typeof qrDialog.showModal === 'function') {
            qrDialog.showModal();
        }
    }
});
</script>
@endsection
