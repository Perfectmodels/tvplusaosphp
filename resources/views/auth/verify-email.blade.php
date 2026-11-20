@extends('layouts.auth', ['title' => __("Vérifier l'e-mail · TV+A OS")])

@section('auth_card')
    @include('auth._badge', ['icon' => 'mark_email_unread'])

    <p class="tv-eyebrow uppercase mb-2">{{ __('Vérification') }}</p>

    <h1 class="mb-2">{{ __('Merci de votre inscription !') }}</h1>
    <p class="tv-lead mb-5">
        {{ __("Avant de commencer, pourriez-vous vérifier votre adresse e-mail en cliquant sur le lien que nous venons de vous envoyer ? Si vous n'avez pas reçu l'e-mail, nous nous ferons un plaisir de vous en renvoyer un autre.") }}
    </p>

    @if (session('status') == 'verification-link-sent')
        <div class="tv-alert tv-alert-success mb-4" role="status">
            <span class="material-symbols-outlined text-[18px] leading-none">check_circle</span>
            <p class="m-0 font-medium">
                {{ __("Un nouveau lien de vérification a été envoyé à l'adresse e-mail fournie lors de votre inscription.") }}
            </p>
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

    <form method="POST" action="{{ route('verification.send') }}" class="mb-3">
        @csrf
        <button type="submit" class="tv-btn-primary">
            {{ __("Renvoyer l'e-mail de vérification") }}
            <span class="material-symbols-outlined text-[18px] align-middle">forward_to_inbox</span>
        </button>
    </form>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="w-full h-11 rounded-xl border bg-white/55 font-semibold text-xs transition-colors"
                style="border-color: var(--tv-line); color: var(--tv-navy);">
            <span class="material-symbols-outlined text-[17px] align-middle">logout</span>
            {{ __('Se déconnecter') }}
        </button>
    </form>

    <p class="tv-help text-center mt-4 mb-0">
        {{ __("Si vous ne trouvez pas l'e-mail, vérifiez votre dossier « courriers indésirables » ou « spam ».") }}
    </p>
@endsection
