<main id="tvos-welcome-intro" class="tvos-intro" aria-labelledby="tvos-intro-title" aria-describedby="tvos-intro-description" data-state="loading" data-intro-active data-destination="{{ route('login') }}">
    <div class="tvos-intro__aurora" aria-hidden="true"></div>
    <div class="tvos-intro__header"><x-brand.wordmark class="tvos-intro__brand" /></div>
    <div class="tvos-intro__scene">
        <div class="tvos-intro__eye"><x-welcome-eye aria-label="Œil animé de TV+A OS" /></div>
        <div class="tvos-intro__content">
            <h1 id="tvos-intro-title">Bienvenue dans<br><span class="tvos-intro__luminous">TV+A OS.</span></h1>
            <p id="tvos-intro-description">L’espace partagé de TV+ Afrique,<br>de la rédaction à l’antenne.</p>
            <div class="tvos-intro__actions">
                <button id="tvos-intro-enter" type="button" class="tvos-intro__button tvos-intro__button--primary" hidden>
                    <span class="material-symbols-outlined" aria-hidden="true" data-play-icon>volume_up</span>
                    <span data-play-label>Activer le son</span>
                </button>
                <noscript><a href="{{ route('login') }}" class="tvos-intro__button tvos-intro__button--primary">Ouvrir la connexion</a></noscript>
            </div>
            <p id="tvos-intro-status" class="tvos-intro__status" role="status" aria-live="polite" hidden></p>
        </div>
    </div>
    <p class="tvos-intro__signature">Informer. Coordonner. Inspirer.</p>
    <audio id="tvos-intro-audio" preload="auto" autoplay><source src="{{ asset('media/audio-oeil.mp3') }}" type="audio/mpeg"></audio>
</main>
