<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — TV+AOS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="min-h-screen bg-gradient-to-br from-primary via-primary to-secondary flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <div class="inline-flex items-center gap-3 mb-4">
                <div class="w-12 h-12 rounded-xl bg-white flex items-center justify-center shadow-lg">
                    <i class="fas fa-broadcast-tower text-primary text-xl"></i>
                </div>
                <div class="text-left">
                    <div class="text-white font-bold text-2xl leading-tight">TV+AOS</div>
                    <div class="text-white/70 text-xs">Studio Production</div>
                </div>
            </div>
            <h1 class="text-white text-2xl font-bold">Connexion à votre espace</h1>
            <p class="text-white/70 text-sm mt-2">Plateforme Conducteur & Production</p>
        </div>

        <div class="bg-white rounded-2xl shadow-2xl p-8">
            <form class="space-y-stack-sm">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-on-surface mb-2">Adresse email</label>
                    <div class="relative">
                        <i class="fas fa-envelope absolute left-3 top-1/2 -translate-y-1/2 text-outline"></i>
                        <input type="email" required placeholder="prenom.nom@tvplusos.tv" class="w-full pl-10 pr-4 py-2.5 border border-outline-variant rounded-lg bg-white focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none transition">
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-medium text-on-surface">Mot de passe</label>
                        <a href="{{ route('password.request') }}" class="text-sm py-2 inline-flex min-h-[44px] items-center text-secondary font-medium hover:underline">Mot de passe oublié ?</a>
                    </div>
                    <div class="relative">
                        <i class="fas fa-lock absolute left-3 top-1/2 -translate-y-1/2 text-outline"></i>
                        <input type="password" required placeholder="••••••••" class="w-full pl-10 pr-4 py-2.5 border border-outline-variant rounded-lg bg-white focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none transition">
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 text-sm text-on-surface cursor-pointer">
                        <input type="checkbox" class="rounded border-outline-variant text-primary focus:ring-primary">
                        Rester connecté
                    </label>
                </div>

                <button type="submit" class="w-full bg-primary hover:bg-primary/90 text-white font-semibold py-2.5 min-h-[44px] rounded-lg transition shadow-sm">
                    Se connecter
                </button>
            </form>

            <div class="relative my-6">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-outline-variant/50"></div>
                </div>
                <div class="relative flex justify-center">
                    <span class="bg-white px-3 text-xs text-outline uppercase">ou continuer avec</span>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <button type="button" class="flex items-center justify-center gap-2 py-2.5 min-h-[44px] border border-outline-variant rounded-lg hover:bg-surface-container transition text-sm font-medium text-on-surface">
                    <i class="fab fa-google text-red-500"></i>
                    Google SSO
                </button>
                <button type="button" class="flex items-center justify-center gap-2 py-2.5 min-h-[44px] border border-outline-variant rounded-lg hover:bg-surface-container transition text-sm font-medium text-on-surface">
                    <i class="fab fa-apple text-black"></i>
                    Apple SSO
                </button>
            </div>
        </div>

        <div class="text-center mt-6 text-white/60 text-xs">
            © {{ date('Y') }} TV+ AOS — Tous droits réservés
        </div>
    </div>
</body>
</html>
