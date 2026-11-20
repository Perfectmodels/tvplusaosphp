<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    @include('partials.favicon')
    <title>Participer · {{ $conducteur->emission?->nom ?? 'TV+ OS' }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <main class="mx-auto flex min-h-screen w-full max-w-xl flex-col justify-center px-4 py-8 sm:px-6">
        <header class="mb-6 text-center">
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-primary">TV+A OS · Direct</p>
            <h1 class="mt-2 text-3xl font-extrabold tracking-tight">{{ $conducteur->emission?->nom ?? 'Participez à l’émission' }}</h1>
            <p class="mt-2 text-sm text-slate-600">
                Envoyez une question ou un message au présentateur et aux intervenants.
            </p>
        </header>

        @if(session('success'))
            <div role="status" class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-800">
                <i class="fa-solid fa-circle-check mr-2" aria-hidden="true"></i>{{ session('success') }}
            </div>
        @endif

        <form action="{{ route('emission-interactions.store', $conducteur->public_interaction_token) }}"
              method="POST"
              class="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            @csrf

            <div>
                <label for="nom" class="mb-1.5 block text-sm font-semibold text-slate-800">Votre nom <span class="font-normal text-slate-500">(facultatif)</span></label>
                <input id="nom" name="nom" type="text" maxlength="100" autocomplete="name"
                       value="{{ old('nom') }}"
                       class="input-field"
                       aria-describedby="{{ $errors->has('nom') ? 'nom-error' : '' }}"
                       aria-invalid="{{ $errors->has('nom') ? 'true' : 'false' }}">
                @error('nom')
                    <p id="nom-error" class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="destinataire" class="mb-1.5 block text-sm font-semibold text-slate-800">
                    À qui s’adresse votre message ? <span class="font-normal text-slate-500">(facultatif)</span>
                </label>
                <input id="destinataire" name="destinataire" type="text" maxlength="120"
                       value="{{ old('destinataire') }}"
                       placeholder="Présentateur, intervenant…"
                       class="input-field">
                @error('destinataire')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="message" class="mb-1.5 block text-sm font-semibold text-slate-800">
                    Votre question ou message <span class="text-red-700">*</span>
                </label>
                <textarea id="message" name="message" rows="5" maxlength="1000" required
                          placeholder="Écrivez votre message…"
                          class="input-field resize-y"
                          aria-describedby="{{ $errors->has('message') ? 'message-help message-error' : 'message-help' }}"
                          aria-invalid="{{ $errors->has('message') ? 'true' : 'false' }}">{{ old('message') }}</textarea>
                <p id="message-help" class="mt-1 text-xs text-slate-500">1 000 caractères maximum.</p>
                @error('message')
                    <p id="message-error" class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit"
                    class="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-lg bg-primary px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-primary-container hover:text-primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                Envoyer à l’équipe
            </button>
            <p class="text-center text-xs leading-relaxed text-slate-500">
                Votre message sera transmis à l’équipe de cette émission.
            </p>
        </form>

        <p class="mt-6 text-center text-xs text-slate-500">
            Édition du {{ $conducteur->date_edition?->format('d/m/Y') }} · TV+A OS
        </p>
    </main>
</body>
</html>
