{{-- ============================================================
    PILE DE NOTIFICATIONS FLOTTANTES (bulles pop-up)
    - alertes info globales (clignotement rouge persistant)
    - message en cours du conducteur (injecté par bulles-conducteur)
    - messages du public (injectés par bulles-conducteur)

    Position fixe en haut à droite, sous la barre supérieure.
    ============================================================ --}}
<style>
    .tv-notif-stack {
        position: fixed;
        top: calc(var(--tv-notif-top, 4.75rem) + .7rem);
        right: max(.875rem, env(safe-area-inset-right));
        z-index: 45;
        display: flex;
        flex-direction: column;
        align-items: stretch;
        gap: .6rem;
        width: min(23rem, calc(100vw - 1.75rem));
        max-height: calc(100dvh - 6.5rem);
        overflow-y: auto;
        overscroll-behavior: contain;
        pointer-events: none;
        scrollbar-width: none;
    }
    .tv-notif-stack::-webkit-scrollbar { display: none; }
    .tv-notif-stack > * { pointer-events: auto; }

    .tv-notif-bubble {
        position: relative;
        width: 100%;
        border-radius: .85rem;
        border: 1px solid rgb(226 232 240);
        background: #fff;
        box-shadow: 0 16px 38px rgba(15, 23, 42, .18);
        animation: tv-notif-in .28s ease-out both;
    }
    @keyframes tv-notif-in {
        from { opacity: 0; transform: translateY(-10px) scale(.98); }
        to   { opacity: 1; transform: translateY(0) scale(1); }
    }

    /* --- Alerte info : clignotement rouge persistant --- */
    .tv-notif-alerte {
        padding: .8rem .9rem;
        color: #450a0a;
        border-color: rgb(252 165 165);
        background: rgb(254 242 242);
        animation: tv-notif-in .28s ease-out both, tv-alerte-clignotante 1.25s ease-in-out infinite;
    }
    .tv-notif-alerte .tv-alerte-dot {
        animation: tv-alerte-dot 1.25s ease-in-out infinite;
    }
    @keyframes tv-alerte-clignotante {
        0%, 100% {
            border-color: rgb(252 165 165);
            background-color: rgb(254 242 242);
            box-shadow: 0 16px 38px rgba(15, 23, 42, .18), 0 0 0 0 rgba(185, 28, 28, .35);
        }
        50% {
            border-color: rgb(185, 28, 28);
            background-color: rgb(255, 255, 255);
            box-shadow: 0 16px 38px rgba(15, 23, 42, .18), 0 0 0 .45rem rgba(185, 28, 28, 0);
        }
    }
    @keyframes tv-alerte-dot {
        0%, 100% { background-color: rgb(185, 28, 28); transform: scale(1); opacity: 1; }
        50% { background-color: rgb(248 113 113); transform: scale(1.35); opacity: .75; }
    }

    /* --- Message en cours du conducteur --- */
    .tv-bulle-sequence {
        overflow: hidden;
        border-color: rgba(148, 163, 184, .35);
        background: linear-gradient(135deg, #0b1220 0%, #13203a 100%);
        color: #e2e8f0;
    }
    .tv-bulle-sequence::before {
        content: "";
        position: absolute;
        inset: 0 auto 0 0;
        width: 4px;
        background: #64748b;
    }
    .tv-bulle-sequence.is-live::before { background: #dc2626; }
    .tv-bulle-sequence.is-live {
        border-color: rgba(248, 113, 113, .55);
        animation: tv-notif-in .28s ease-out both, tv-bulle-live 1.4s ease-in-out infinite;
    }
    @keyframes tv-bulle-live {
        0%, 100% { box-shadow: 0 16px 38px rgba(15, 23, 42, .28), 0 0 0 0 rgba(220, 38, 38, .45); }
        50% { box-shadow: 0 16px 38px rgba(15, 23, 42, .28), 0 0 0 .4rem rgba(220, 38, 38, 0); }
    }
    .tv-bulle-live-dot {
        display: inline-block;
        width: .55rem;
        height: .55rem;
        flex: none;
        border-radius: 9999px;
        background: #64748b;
    }
    .is-live .tv-bulle-live-dot {
        background: #ef4444;
        animation: tv-bulle-dot 1s ease-in-out infinite;
    }
    @keyframes tv-bulle-dot {
        0%, 100% { opacity: 1; transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, .55); }
        50% { opacity: .55; transform: scale(1.25); box-shadow: 0 0 0 .28rem rgba(239, 68, 68, 0); }
    }

    /* --- Message du public --- */
    .tv-bulle-public {
        border-color: rgba(37, 99, 235, .28);
        background: linear-gradient(135deg, #ffffff 0%, #f4f8ff 100%);
    }
    .tv-bulle-public::before {
        content: "";
        position: absolute;
        inset: 0 auto 0 0;
        width: 4px;
        border-radius: .85rem 0 0 .85rem;
        background: #2563eb;
    }

    @media (max-width: 640px) {
        .tv-notif-stack {
            right: .65rem;
            width: calc(100vw - 1.3rem);
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .tv-notif-bubble,
        .tv-notif-alerte,
        .tv-bulle-sequence.is-live,
        .tv-bulle-live-dot,
        .tv-alerte-dot {
            animation: none !important;
        }
    }
</style>

<div id="tv-notifications"
     class="tv-notif-stack"
     role="region"
     aria-label="Notifications en cours"
     aria-live="polite">
    @include('_partials.alertes-globales')
</div>

<script>
    (() => {
        const header = document.querySelector('header.ui-topbar') || document.querySelector('header');
        const stack = document.getElementById('tv-notifications');
        if (!header || !stack) return;

        const synchroniser = () => {
            const hauteur = Math.ceil(header.getBoundingClientRect().height);
            if (hauteur > 0) {
                stack.style.setProperty('--tv-notif-top', `${hauteur}px`);
            }
        };

        synchroniser();
        window.addEventListener('resize', synchroniser);

        if (typeof ResizeObserver !== 'undefined') {
            new ResizeObserver(synchroniser).observe(header);
        }
    })();
</script>
