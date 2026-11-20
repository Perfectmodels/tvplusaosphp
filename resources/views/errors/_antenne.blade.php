{{--
    Coque commune des pages d'erreur 404 / 403 / 500 (et 503 en autonome).
    Identité « TV+ Afrique » : fond dégradé, carte en verre dépoli, grand code
    héros + mire TV en teintes de marque — aucun asset externe (classes
    existantes app.css + styles scopés ci-dessous).

    Variables attendues :
    - $code    : string — code HTTP en chiffres (« 404 », « 403 », « 500 »…)
    - $titre   : string — titre de la page
    - $message : string — explication (direction, pas d'excuse)
    - $actions : array  — [['label' => …, 'href' => …] | ['label' => …, 'back' => true]
                           | ['label' => …, 'reload' => true], …]
                           Le 1er lien est primaire, les suivants secondaires.
--}}
<style>
    .tv-erreur-code {
        font-size: clamp(76px, 24vw, 128px);
        font-weight: 900;
        letter-spacing: -.04em;
        line-height: 1;
        color: #081340;
        margin-top: 2rem;
    }
    .tv-erreur-mire {
        display: flex;
        width: 220px;
        max-width: 70%;
        height: 12px;
        border-radius: 6px;
        overflow: hidden;
        margin: 1.25rem auto 0;
        box-shadow: 0 2px 6px rgba(8, 19, 64, .18);
    }
    .tv-erreur-mire span { flex: 1; }
    .tv-erreur-titre {
        font-size: clamp(24px, 5vw, 30px);
        font-weight: 800;
        letter-spacing: -.02em;
        color: #081340;
        margin: 1.25rem 0 .75rem;
    }
    .tv-erreur-message {
        color: #7f93b2;
        font-size: .95rem;
        line-height: 1.65;
        max-width: 42ch;
        margin: 0 auto;
    }
    .tv-erreur-actions {
        display: flex;
        flex-direction: column;
        gap: .75rem;
        justify-content: center;
        margin-top: 2rem;
    }
    @media (min-width: 480px) {
        .tv-erreur-actions { flex-direction: row; flex-wrap: wrap; }
    }
    .tv-erreur-actions .btn-primary,
    .tv-erreur-actions .btn-outline {
        min-height: 44px;
        padding: 0 1rem;
        white-space: nowrap;
    }
</style>

<div class="tv-auth-bg flex items-center justify-center p-4">
    <div class="tv-card" style="max-width: 560px; text-align: center;">

        {{-- Marque --}}
        <x-brand.wordmark class="tv-brand-logo" style="font-size: 1.5rem;" />
        <div class="tv-brand-sub">TV+ Afrique</div>

        {{-- Code héros --}}
        <div class="tv-erreur-code">{{ $code }}</div>

        {{-- Mire TV — teintes de marque (aucun asset externe) --}}
        <div class="tv-erreur-mire" aria-hidden="true">
            <span style="background:#081340"></span>
            <span style="background:#1f6fd1"></span>
            <span style="background:#a9c6f2"></span>
            <span style="background:#dbe5f3"></span>
            <span style="background:#7f93b2"></span>
            <span style="background:#1f6fd1"></span>
            <span style="background:#081340"></span>
        </div>

        <h1 class="tv-erreur-titre">{{ $titre }}</h1>
        <p class="tv-erreur-message">{{ $message }}</p>

        {{-- Actions — cibles ≥ 44px --}}
        <div class="tv-erreur-actions">
            @foreach ($actions as $action)
                @if (!empty($action['back']))
                    <button type="button" class="btn-outline"
                            onclick="window.history.length > 1 ? window.history.back() : window.location.assign('{{ route('dashboard') }}')">
                        {{ $action['label'] }}
                    </button>
                @elseif (!empty($action['reload']))
                    <button type="button" class="btn-primary" onclick="window.location.reload()">
                        {{ $action['label'] }}
                    </button>
                @else
                    <a href="{{ $action['href'] }}" class="{{ $loop->first ? 'btn-primary' : 'btn-outline' }}">
                        {{ $action['label'] }}
                    </a>
                @endif
            @endforeach
        </div>

        <footer class="mt-8 pt-5" style="border-top: 1px solid #dbe5f3;">
            <p class="tv-tagline-sub m-0">INFORMER. COORDONNER. INSPIRER.</p>
        </footer>
    </div>
</div>
