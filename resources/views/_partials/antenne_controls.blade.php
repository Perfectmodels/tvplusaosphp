{{--
    Boutons de pilotage du CHRONO ÉMISSION (un seul compte à rebours pour toute
    l'émission). Utilisé uniquement dans le bandeau du panneau gauche.

    Nécessite (dans le scope Alpine parent) :
      · canPiloterAntenne, antenneEnMarche(), antenneDemarree(), antenneTerminee(), antenneBusy
      · demarrerAntenne(), pauseAntenne(), reinitialiserAntenne(), antenneBoutonLabel()
--}}
@if (! empty($canPiloterAntenne))
    <div class="flex flex-wrap items-center gap-2" x-show="canPiloterAntenne">
        {{-- Démarrer / Reprendre / Redémarrer --}}
        <button type="button"
                @click="demarrerAntenne()"
                x-show="! antenneEnMarche()"
                x-cloak
                :disabled="antenneBusy"
                class="inline-flex items-center gap-2 px-3.5 py-2 min-h-[44px] text-sm font-semibold bg-primary text-white rounded-lg
                       hover:bg-primary-container hover:text-primary transition-colors shadow-sm
                       disabled:opacity-50 disabled:cursor-not-allowed">
            <i x-show="! antenneBusy" class="fa-solid fa-play text-xs"></i>
            <i x-show="antenneBusy" x-cloak class="fa-solid fa-spinner fa-spin text-xs"></i>
            <span x-text="antenneBoutonLabel()">Démarrer</span>
        </button>

        {{-- Pause --}}
        <button type="button"
                @click="pauseAntenne()"
                x-show="antenneEnMarche()"
                x-cloak
                :disabled="antenneBusy"
                class="inline-flex items-center gap-2 px-3.5 py-2 min-h-[44px] text-sm font-semibold border border-amber-500/50 bg-amber-50 text-amber-800 rounded-lg
                       hover:bg-amber-100 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
            <i x-show="! antenneBusy" class="fa-solid fa-pause text-xs"></i>
            <i x-show="antenneBusy" x-cloak class="fa-solid fa-spinner fa-spin text-xs"></i>
            Pause
        </button>

        {{-- Réinitialiser --}}
        <button type="button"
                @click="reinitialiserAntenne()"
                x-show="antenneDemarree()"
                x-cloak
                :disabled="antenneBusy"
                class="inline-flex items-center gap-2 px-3 py-2 min-h-[44px] text-sm border border-outline/30 text-on-surface-variant rounded-lg
                       hover:bg-surface-container-low hover:text-error transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
            <i class="fa-solid fa-rotate-left text-xs"></i>
            Réinitialiser
        </button>
    </div>
@endif
