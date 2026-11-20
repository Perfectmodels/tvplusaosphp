@extends('layouts.auth', ['title' => __('Créer un compte · TV+A OS')])

@section('auth_card')
    <div class="flex items-center justify-between mb-4">
        <a href="{{ route('login') }}" class="tv-link inline-flex items-center gap-1.5 text-xs" style="color: var(--tv-navy);">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            {{ __('Retour à la connexion') }}
        </a>
    </div>

    @include('auth._badge', ['icon' => 'badge'])

    <p class="tv-eyebrow uppercase mb-2">{{ __('Nouveau compte') }}</p>

    <h1 class="mb-2">{{ __('Rejoindre TV+A OS') }}</h1>
    <p class="tv-lead mb-5">{{ __('Créez votre compte pour accéder à votre espace de travail.') }}</p>

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

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <div class="mb-3">
            <label for="name" class="tv-label">{{ __('Nom complet') }}</label>
            <div class="tv-field">
                <span class="tv-field-icon"><span class="material-symbols-outlined text-[19px]">badge</span></span>
                <input
                    id="name" name="name" type="text" value="{{ old('name') }}"
                    required autofocus autocomplete="name"
                    placeholder="{{ __('Jean Dupont') }}"
                    aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}"
                />
            </div>
        </div>

        <div class="mb-3">
            <label for="email" class="tv-label">{{ __('Adresse e-mail') }}</label>
            <div class="tv-field">
                <span class="tv-field-icon"><span class="material-symbols-outlined text-[19px]">person</span></span>
                <input
                    id="email" name="email" type="email" value="{{ old('email') }}"
                    required autocomplete="username"
                    placeholder="exemple@tvplusafrique.com"
                    aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                />
            </div>
        </div>

        <div class="mb-3">
            <label for="password" class="tv-label">{{ __('Mot de passe') }}</label>
            <div class="tv-field">
                <span class="tv-field-icon"><span class="material-symbols-outlined text-[19px]">lock</span></span>
                <input
                    id="password" name="password" type="password" required autocomplete="new-password"
                    placeholder="••••••••••••"
                    aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                />
                <button type="button" class="tv-field-btn" data-toggle-password="password"
                        aria-label="{{ __('Afficher le mot de passe') }}">
                    <span class="material-symbols-outlined text-[19px]">visibility</span>
                </button>
            </div>
        </div>

        <div class="mb-4">
            <label for="password_confirmation" class="tv-label">{{ __('Confirmer le mot de passe') }}</label>
            <div class="tv-field">
                <span class="tv-field-icon"><span class="material-symbols-outlined text-[19px]">lock</span></span>
                <input
                    id="password_confirmation" name="password_confirmation" type="password" required
                    autocomplete="new-password"
                    placeholder="••••••••••••"
                />
                <button type="button" class="tv-field-btn" data-toggle-password="password_confirmation"
                        aria-label="{{ __('Afficher le mot de passe') }}">
                    <span class="material-symbols-outlined text-[19px]">visibility</span>
                </button>
            </div>
        </div>

        <label class="flex items-start gap-2 cursor-pointer select-none mb-4" for="terms">
            <input id="terms" name="terms" type="checkbox" class="tv-check mt-0.5" required>
            <span class="text-[.78rem]" style="color: var(--tv-navy);">
                {{ __('J’accepte les') }}
                <a href="{{ route('terms.show') }}" class="tv-link">{{ __('Conditions d’utilisation') }}</a>
                {{ __('et la') }}
                <a href="{{ route('policy.show') }}" class="tv-link">{{ __('Politique de confidentialité') }}</a>.
            </span>
        </label>

        <button type="submit" class="tv-btn-primary">
            {{ __('Créer mon compte') }}
            <span class="material-symbols-outlined text-[18px] align-middle">arrow_forward</span>
        </button>
    </form>

    <p class="tv-help text-center mt-4 mb-0">
        {{ __('Vous avez déjà un compte ?') }}
        <a href="{{ route('login') }}" class="tv-link">{{ __('Se connecter') }}</a>
    </p>
@endsection

@push('scripts')
<script>
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
