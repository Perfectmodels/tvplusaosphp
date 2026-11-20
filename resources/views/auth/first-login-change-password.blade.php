@extends('layouts.auth', ['title' => __('Première connexion · TV+A OS')])

@section('auth_card')
    <div class="flex items-center justify-between mb-4">
        <span class="tv-eyebrow uppercase">{{ __('Première connexion') }}</span>
        <form method="POST" action="{{ route('logout') }}" class="inline">
            @csrf
            <button type="submit" class="tv-link inline-flex items-center gap-1.5 text-xs" style="color: var(--tv-navy);">
                <span class="material-symbols-outlined text-[18px]">logout</span>
                {{ __('Se déconnecter') }}
            </button>
        </form>
    </div>

    @include('auth._badge', ['icon' => 'lock_reset'])

    <h1 class="mb-2">{{ __('Définissez votre mot de passe') }}</h1>
    <p class="tv-lead mb-5">
        {{ __("Bienvenue sur TV+A OS. Pour sécuriser votre compte, définissez un mot de passe personnel. Il vous sera demandé lors de vos prochaines connexions.") }}
    </p>

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

    <form method="POST" action="{{ route('first-login-change-password.store') }}">
        @csrf

        <div class="mb-3">
            <label for="email" class="tv-label">{{ __('Compte connecté') }}</label>
            <div class="tv-field" style="opacity:.75;">
                <span class="tv-field-icon"><span class="material-symbols-outlined text-[19px]">badge</span></span>
                <input
                    id="email" type="text" readonly
                    value="{{ auth()->user()->nomComplet }} · {{ auth()->user()->email }}"
                    style="cursor:not-allowed;"
                />
            </div>
        </div>

        <div class="mb-3">
            <label for="password" class="tv-label">{{ __('Nouveau mot de passe') }}</label>
            <div class="tv-field">
                <span class="tv-field-icon"><span class="material-symbols-outlined text-[19px]">enhanced_encryption</span></span>
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
                <span class="tv-field-icon"><span class="material-symbols-outlined text-[19px]">enhanced_encryption</span></span>
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

        <button type="submit" class="tv-btn-primary">
            {{ __('Enregistrer et accéder au tableau de bord') }}
            <span class="material-symbols-outlined text-[18px] align-middle">arrow_forward</span>
        </button>
    </form>

    <p class="tv-help text-center mt-4 mb-0">
        {{ __('Règles : minimum 8 caractères, avec au moins une majuscule, un chiffre et un symbole.') }}
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
