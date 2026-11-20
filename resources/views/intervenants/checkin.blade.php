<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Check-in · {{ $i->nom_complet }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-gradient-to-br from-[#001a41] to-[#0054cd] text-white p-6">
    <div class="max-w-xl mx-auto bg-white/10 backdrop-blur rounded-3xl p-8 border border-white/20 shadow-2xl text-center">
        <div class="text-xs tracking-[0.3em] uppercase opacity-90">TV+A OS · Check-in</div>
        <img src="{{ $i->photoUrl }}" class="w-32 h-32 rounded-full mx-auto mt-6 ring-4 ring-white/30 object-cover bg-white/20">
        <h1 class="text-3xl font-bold mt-5">{{ $i->nom_complet }}</h1>
        <div class="opacity-80 mt-1">{{ $i->fonction ?? '' }} · {{ $i->organisme ?? '' }}</div>
        <div class="mt-6 inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white/10 border border-white/20">
            {{ $i->code_intervenant }} · {{ optional($i->type)->label() ?? '' }}
        </div>
        <div class="mt-8 text-sm opacity-80 space-y-1">
            <div>📞 {{ $i->telephone_1 ?? '—' }}</div>
            <div>✉️ {{ $i->email ?? '—' }}</div>
            <div>📍 {{ $i->ville_residence ? $i->ville_residence.', ' : '' }}{{ $i->pays_residence ?? '' }}</div>
        </div>
        <div class="mt-8 text-sm uppercase tracking-widest opacity-100">
            Bienvenue dans nos studios · Présentez ce badge à l'accueil
        </div>
    </div>
</body>
</html>
