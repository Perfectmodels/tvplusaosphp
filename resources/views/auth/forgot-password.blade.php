@extends('layouts.auth', ['title' => __("Mot de passe oublié · TV+A OS")])

@section('auth_card')
    <div class="flex items-center justify-between mb-4">
        <a href="{{ route('login') }}" class="tv-link inline-flex items-center gap-1.5 text-xs" style="color: var(--tv-navy);">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            {{ __('Retour à la connexion') }}
        </a>
    </div>

    @include('auth._badge', ['icon' => 'lock'])

    <p class="tv-eyebrow uppercase mb-2">{{ __('Sécurisé') }}</p>

    <h1 class="mb-2">{{ __('Mot de passe oublié ?') }}</h1>
    <p class="tv-lead mb-5">
        {{ __("Entrez votre adresse e-mail et nous vous enverrons un lien pour réinitialiser votre mot de passe.") }}
    </p>

    @if (session('status'))
        <div class="tv-alert tv-alert-success mb-4" role="status">
            <span class="material-symbols-outlined text-[18px] leading-none">check_circle</span>
            <p class="m-0 font-medium">{{ session('status') }}</p>
        </div>
    @endif

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

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div class="mb-4">
            <label for="email" class="tv-label">{{ __('Adresse e-mail') }}</label>
            <div class="tv-field">
                <span class="tv-field-icon"><span class="material-symbols-outlined text-[19px]">person</span></span>
                <input
                    id="email" name="email" type="email" value="{{ old('email') }}"
                    required autofocus autocomplete="email"
                    placeholder="exemple@tvplusafrique.com"
                    aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                />
            </div>
        </div>

        <button type="submit" class="tv-btn-primary">
            {{ __('Envoyer le lien de réinitialisation') }}
            <span class="material-symbols-outlined text-[18px] align-middle">arrow_forward</span>
        </button>
    </form>

    <p class="tv-help text-center mt-4 mb-0">
        {{ __('Besoin d’aide ?') }} {{ __('Contactez votre administrateur.') }}
    </p>
@endsection
