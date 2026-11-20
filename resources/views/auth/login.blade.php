@extends('layouts.guest')
@section('title', 'Connexion · TV+A OS')
@push('styles')
    @vite(['resources/css/welcome-eye.css', 'resources/js/welcome-eye.js', 'resources/css/login-experience.css', 'resources/js/login-experience.js'])
@endpush
@section('content')
<div class="tv-login">
    <header class="tv-login__header"><x-brand.wordmark class="tv-login__brand" /><a href="{{ url('/') }}" class="tv-login__replay"><span class="material-symbols-outlined" aria-hidden="true">play_circle</span>Revoir l’introduction</a></header>
    <main class="tv-login__layout">
        <section class="tv-login__signin" aria-labelledby="login-title">
            <h1 id="login-title">Connexion</h1><p class="tv-login__lead">Retrouvez votre équipe et votre espace métier.</p>
    {{-- Erreurs de connexion --}}
    @if ($errors->any())
        <div class="tv-alert tv-alert-error mb-4" role="alert">
            <span class="material-symbols-outlined text-[18px] leading-none">error</span>
            <div>
                @foreach ($errors->all() as $error)
                    <p class="m-0 font-medium">{{ $error }}</p>
                @endforeach
            </div>
        </div>
    @endif

    @if (session('status'))
        <div class="tv-alert tv-alert-success mb-4" role="status">
            <span class="material-symbols-outlined text-[18px] leading-none">check_circle</span>
            <p class="m-0 font-medium">{{ session('status') }}</p>
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="mb-3">
            <label for="email" class="tv-label">{{ __('Adresse e-mail') }}</label>
            <div class="tv-field">
                <span class="tv-field-icon"><span class="material-symbols-outlined text-[19px]">mail</span></span>
                <input
                    id="email" name="email" type="email" value="{{ old('email') }}"
                    autocomplete="email" required autofocus
                    placeholder="exemple@tvplusafrique.com"
                    aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                />
            </div>
        </div>

        <div class="mb-4">
            <label for="password" class="tv-label">{{ __('Mot de passe') }}</label>
            <div class="tv-field">
                <span class="tv-field-icon"><span class="material-symbols-outlined text-[19px]">lock</span></span>
                <input
                    id="password" name="password" type="password" required
                    autocomplete="current-password"
                    placeholder="••••••••••••"
                    aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                />
                <button type="button" class="tv-field-btn" data-toggle-password="password"
                        aria-label="{{ __('Afficher le mot de passe') }}">
                    <span class="material-symbols-outlined text-[19px]">visibility</span>
                </button>
            </div>
        </div>

        <div class="tv-form-options">
            <label class="flex items-center gap-2 cursor-pointer select-none" for="remember">
                <input id="remember" name="remember" type="checkbox" class="tv-check"/>
                <span class="text-xs text-[color:var(--tv-navy)]">{{ __('Se souvenir de moi') }}</span>
            </label>

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="tv-link text-xs">
                    {{ __('Mot de passe oublié ?') }}
                </a>
            @endif
        </div>

        <button type="submit" class="tv-btn-primary">
            {{ __('Se connecter') }} <span class="material-symbols-outlined text-[18px] align-middle">arrow_forward</span>
        </button>
    </form>

    <p class="tv-help text-center mt-4 mb-0">
        {{ __("Besoin d'un accès ?") }} {{ __('Contactez votre administrateur.') }}
    </p>
            <details class="tv-login__first"><summary>Première connexion ?</summary><ol><li>Utilisez l’adresse e-mail associée à votre compte.</li><li>Changez le mot de passe initial si la plateforme vous le demande.</li><li>Choisissez votre vue métier et retrouvez vos priorités.</li></ol></details>
        </section>
        <section class="tv-login__guide" aria-labelledby="guide-title">
            <div class="tv-login__eye"><x-welcome-eye aria-label="Œil TV+A OS — votre rédaction connectée" /></div>
            <h2 id="guide-title">Du sujet à l’antenne,<br>avancez ensemble.</h2>
            <p class="tv-login__guide-intro">Découvrez les espaces de TV+A OS.<br>Chaque fonction retrouve ses outils et ses priorités.</p>
            @php
                $guides = [
                    ['key' => 'redaction', 'title' => 'Rédaction', 'icon' => 'edit_document', 'heading' => 'Faire émerger les bons sujets', 'text' => 'Retrouvez les sujets, les affectations et les conférences de rédaction dans un espace commun.'],
                    ['key' => 'production', 'title' => 'Production', 'icon' => 'movie_edit', 'heading' => 'Passer du terrain à la livraison', 'text' => 'Suivez le terrain, le montage, l’habillage et la livraison dans les conducteurs de production.'],
                    ['key' => 'antenne', 'title' => 'Antenne', 'icon' => 'live_tv', 'heading' => 'Préparer le passage à l’antenne', 'text' => 'Consultez les conducteurs validés, les scripts et les horaires de passage.'],
                    ['key' => 'equipe', 'title' => 'Équipe', 'icon' => 'groups', 'heading' => 'Garder le même fil avec l’équipe', 'text' => 'Attribuez des tâches, partagez des fichiers et échangez les consignes dans les dossiers métier.'],
                    ['key' => 'digital', 'title' => 'Digital', 'icon' => 'public', 'heading' => 'Prolonger les sujets en ligne', 'text' => 'Préparez les contenus numériques, demandez leur validation et suivez leur planification.'],
                ];
            @endphp
            <div class="tv-login__topics" role="tablist" aria-label="Découvrir les fonctionnalités">
                @foreach($guides as $guide)<button type="button" id="guide-tab-{{ $guide['key'] }}" role="tab" aria-selected="{{ $loop->first ? 'true' : 'false' }}" aria-controls="guide-panel-{{ $guide['key'] }}" tabindex="{{ $loop->first ? '0' : '-1' }}" data-guide-tab="{{ $guide['key'] }}"><span class="material-symbols-outlined" aria-hidden="true">{{ $guide['icon'] }}</span><span>{{ $guide['title'] }}</span></button>@endforeach
            </div>
            @foreach($guides as $guide)<section id="guide-panel-{{ $guide['key'] }}" class="tv-login__panel" role="tabpanel" aria-labelledby="guide-tab-{{ $guide['key'] }}" tabindex="0" data-guide-panel="{{ $guide['key'] }}" @if(! $loop->first) hidden @endif><h3>{{ $guide['heading'] }}</h3><p>{{ $guide['text'] }}</p></section>@endforeach
            <p class="tv-login__scope">Les espaces disponibles dépendent des droits de votre compte.</p>
        </section>
    </main>
    <footer class="tv-login__footer">Informer. Coordonner. Inspirer.</footer>
</div>
@endsection
@push('scripts')
<script>
    // Afficher / masquer le mot de passe (boutons marqués data-toggle-password)
    document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.getAttribute('data-toggle-password'));
            if (!input) return;
            var reveal = input.type === 'password';
            input.type = reveal ? 'text' : 'password';
            btn.querySelector('.material-symbols-outlined').textContent = reveal ? 'visibility_off' : 'visibility';
            btn.setAttribute('aria-label', reveal ? @json(__('Masquer le mot de passe')) : @json(__('Afficher le mot de passe')));
        });
    });
</script>
@endpush
