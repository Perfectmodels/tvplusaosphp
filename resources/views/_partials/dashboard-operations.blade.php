    {{-- KPI Row — GRILLE 4 colonnes Prototype EXACTE (Glassmorphism cards) --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
        {{-- KPI 1: Diffusions Totales → émissions au statut « Diffusée » --}}
        <div class="bg-white rounded-xl p-6 card-shadow border border-outline-variant/30 flex flex-col relative overflow-hidden group">
            <div class="absolute top-0 right-0 w-16 h-16 bg-primary-container/5 rounded-bl-full -z-10 group-hover:scale-110 transition-transform"></div>
            <div class="flex justify-between items-start mb-4">
                <span class="text-on-surface-variant font-semibold text-sm">Émissions Diffusées</span>
                <span class="material-symbols-outlined text-primary-container">live_tv</span>
            </div>
            <div class="text-3xl font-black text-on-primary-fixed mb-1">{{ $emissionsDiffusees }}</div>
            <div class="text-xs text-secondary-container font-medium flex items-center gap-1">
                <span class="material-symbols-outlined text-sm">trending_up</span>
                sur {{ $totalEmissions }} émissions au catalogue
            </div>
        </div>

        {{-- KPI 2: Invités Disponibles → intervenants externes disponibles (plus de présentateurs internes) --}}
        <div class="bg-white rounded-xl p-6 card-shadow border border-outline-variant/30 flex flex-col relative overflow-hidden group">
            <div class="absolute top-0 right-0 w-16 h-16 bg-secondary-container/5 rounded-bl-full -z-10 group-hover:scale-110 transition-transform"></div>
            <div class="flex justify-between items-start mb-4">
                <span class="text-on-surface-variant font-semibold text-sm">Invités Disponibles</span>
                <span class="material-symbols-outlined text-secondary-container">group</span>
            </div>
            <div class="text-3xl font-black text-on-primary-fixed mb-1">{{ $intervenantsDispos }}</div>
            <div class="text-xs text-on-surface-variant font-medium">sur {{ $totalIntervenants }} invités référencés</div>
        </div>

        {{-- KPI 3: Progression Prod. = moyenne des séquences terminées du jour --}}
        <div class="bg-white rounded-xl p-6 card-shadow border border-outline-variant/30 flex flex-col relative overflow-hidden group">
            <div class="absolute top-0 right-0 w-16 h-16 bg-primary-container/5 rounded-bl-full -z-10 group-hover:scale-110 transition-transform"></div>
            <div class="flex justify-between items-start mb-4">
                <span class="text-on-surface-variant font-semibold text-sm">Progression Prod.</span>
                <span class="material-symbols-outlined text-primary-container">donut_large</span>
            </div>
            <div class="text-3xl font-black text-on-primary-fixed mb-1">{{ $progression }}%</div>
            <div class="w-full bg-surface-container h-1.5 rounded-full mt-2">
                <div class="bg-primary-container h-1.5 rounded-full transition-all" style="width: {{ $progression }}%"></div>
            </div>
        </div>

        {{-- KPI 4: Alertes en attente — compteur calculé (plus de 3 en dur) --}}
        @php $alertesActives = $alertesOperationnelles['total'] > 0; @endphp
        <div class="bg-white rounded-xl p-6 {{ $alertesActives ? 'border border-error/20' : 'border border-outline-variant/30' }} flex flex-col relative overflow-hidden group">
            <div class="absolute top-0 right-0 w-16 h-16 {{ $alertesActives ? 'bg-error/5' : 'bg-surface-container/30' }} rounded-bl-full -z-10 group-hover:scale-110 transition-transform"></div>
            <div class="flex justify-between items-start mb-4">
                <span class="font-semibold text-sm {{ $alertesActives ? 'text-error' : 'text-on-surface-variant' }}">Alertes en attente</span>
                <span class="material-symbols-outlined {{ $alertesActives ? 'text-error' : 'text-on-surface-variant' }}">warning</span>
            </div>
            <div class="text-3xl font-black mb-1 {{ $alertesActives ? 'text-error' : 'text-on-surface-variant' }}">{{ $alertesOperationnelles['total'] }}</div>
            <div class="text-xs font-medium {{ $alertesActives ? 'text-error/80' : 'text-on-surface-variant' }}">{{ $alertesOperationnelles['details'] }}</div>
        </div>
    </div>
