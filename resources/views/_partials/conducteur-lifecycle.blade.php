@php
    $etatConducteur = $conducteur->status;
    $statutConducteur = \App\Enums\StatutConducteurEnum::class;
@endphp
<nav aria-label="Validation et archivage du conducteur"
     class="flex flex-wrap items-center gap-2 rounded-xl border border-outline-variant bg-white p-3 shadow-sm sm:p-4">
    <span class="mr-2 text-xs font-bold uppercase tracking-wide text-on-surface-variant">
        <i class="fa-solid fa-list-check mr-1 text-primary" aria-hidden="true"></i>
        Cycle de validation
    </span>
    @if(in_array($etatConducteur, [$statutConducteur::Brouillon, $statutConducteur::En_construction], true))
        @can('update', $conducteur)
            <form method="POST" action="{{ route('conducteurs.lifecycle.soumettre', $conducteur) }}">
                @csrf @method('PATCH')
                <button class="min-h-10 rounded-lg bg-primary px-3.5 py-2 text-xs font-bold text-white hover:opacity-90" type="submit">
                    Soumettre à validation
                </button>
            </form>
        @endcan
    @elseif($etatConducteur === $statutConducteur::A_valider)
        @can('validateConducteur', $conducteur)
            <form method="POST" action="{{ route('conducteurs.lifecycle.valider', $conducteur) }}">
                @csrf @method('PATCH')
                <button class="min-h-10 rounded-lg bg-primary px-3.5 py-2 text-xs font-bold text-white hover:opacity-90" type="submit">
                    Valider le conducteur
                </button>
            </form>
        @endcan
    @elseif(in_array($etatConducteur, [$statutConducteur::Valide, $statutConducteur::Transfere, $statutConducteur::En_direct], true))
        @can('validateConducteur', $conducteur)
            <form method="POST" action="{{ route('conducteurs.lifecycle.cloturer', $conducteur) }}">
                @csrf @method('PATCH')
                <button class="min-h-10 rounded-lg border border-outline/30 px-3.5 py-2 text-xs font-semibold hover:bg-surface-container" type="submit">
                    Clôturer l'édition
                </button>
            </form>
        @endcan
    @elseif($etatConducteur === $statutConducteur::Cloture)
        @can('delete', $conducteur)
            <form method="POST" action="{{ route('conducteurs.lifecycle.archiver', $conducteur) }}"
                  onsubmit="return window.confirm('Archiver ce conducteur ? Les sujets et séquences resteront conservés.')">
                @csrf @method('PATCH')
                <button class="min-h-10 rounded-lg border border-outline/30 px-3.5 py-2 text-xs font-semibold hover:bg-surface-container" type="submit">
                    <i class="fa-solid fa-box-archive mr-1" aria-hidden="true"></i>Archiver sans supprimer
                </button>
            </form>
        @endcan
    @elseif($etatConducteur === $statutConducteur::Archive)
        <span class="text-xs text-on-surface-variant">Conducteur archivé · consultation conservée</span>
        @can('unlock', $conducteur)
            <form method="POST" action="{{ route('conducteurs.lifecycle.restaurer', $conducteur) }}">
                @csrf @method('PATCH')
                <button class="min-h-10 rounded-lg border border-outline/30 px-3.5 py-2 text-xs font-semibold hover:bg-surface-container" type="submit">
                    Restaurer
                </button>
            </form>
        @endcan
    @endif
    <span class="ml-auto text-xs font-semibold text-on-surface-variant" aria-live="polite">
        {{ $etatConducteur instanceof $statutConducteur ? $etatConducteur->label() : 'Statut indisponible' }}
    </span>
</nav>
