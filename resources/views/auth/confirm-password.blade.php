@extends('layouts.auth', ['title' => __('Confirmer le mot de passe · TV+A OS')])

@section('auth_card')
    @include('auth._badge', ['icon' => 'verified_user'])

    <p class="tv-eyebrow uppercase mb-2">{{ __('Zone sécurisée') }}</p>

    <h1 class="mb-2">{{ __('Confirmez votre mot de passe') }}</h1>
    <p class="tv-lead mb-5">
        {{ __('Cette zone est protégée. Merci de confirmer votre mot de passe avant de continuer.') }}
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

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <div class="mb-4">
            <label for="password" class="tv-label">{{ __('Mot de passe') }}</label>
            <div class="tv-field">
                <span class="tv-field-icon"><span class="material-symbols-outlined text-[19px]">lock</span></span>
                <input
                    id="password" name="password" type="password" required autocomplete="current-password"
                    placeholder="••••••••••••"
                    aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                />
                <button type="button" class="tv-field-btn" data-toggle-password="password"
                        aria-label="{{ __('Afficher le mot de passe') }}">
                    <span class="material-symbols-outlined text-[19px]">visibility</span>
                </button>
            </div>
        </div>

        <button type="submit" class="tv-btn-primary">
            {{ __('Confirmer') }}
            <span class="material-symbols-outlined text-[18px] align-middle">check</span>
        </button>
    </form>

    @if (Route::has('password.request'))
        <p class="tv-help text-center mt-4 mb-0">
            <a href="{{ route('password.request') }}" class="tv-link">{{ __('Mot de passe oublié ?') }}</a>
        </p>
    @endif
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
