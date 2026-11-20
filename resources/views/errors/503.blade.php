{{--
    Page 503 — autonome (HTML + CSS inline, aucun asset Vite ni image externe).
    Pendant `php artisan down`, la middleware de maintenance bloque aussi les
    requêtes /build/* : cette page doit donc pouvoir s'afficher sans elles.

    Réutilise le même partial que 404/403/500 (errors._antenne) avec les
    classes critiques redéfinies ci-dessous.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('partials.favicon')
    <title>Maintenance — TV+A OS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Inter, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        button { font-family: inherit; }

        /* — Redéfinition critique des classes app.css (indisponibles ici) — */
        .tv-auth-bg {
            min-height: 100vh;
            color: #0b1b4d;
            display: flex;
            align-items: center;
            justify-content: center;
            background:
                radial-gradient(circle at 12% 18%, #ffffff 0, transparent 22%),
                radial-gradient(circle at 85% 90%, #ffffff 0, transparent 18%),
                radial-gradient(circle at 70% 10%, #a9c6f2 0, transparent 35%),
                linear-gradient(135deg, #dce9fb 0%, #b9d3f5 50%, #cfe1f9 100%);
        }
        .tv-card {
            background: rgba(255, 255, 255, .45);
            border: 1px solid rgba(255, 255, 255, .8);
            border-radius: 28px;
            box-shadow: 0 20px 60px rgba(60, 110, 190, .18);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            padding: 2.4rem 2.5rem;
            max-width: 460px;
            width: 100%;
        }
        @media (max-width: 991.98px) {
            .tv-card { padding: 1.8rem 1.4rem; }
        }
        .tv-brand-logo,
        .tv-wordmark {
            display: inline-block;
            font-family: Inter, system-ui, sans-serif;
            font-weight: 900;
            letter-spacing: -.03em;
            line-height: 1;
            color: #001a41;
            white-space: nowrap;
        }
        .tv-brand-logo .tv-plus,
        .tv-wordmark-plus { color: #0054cd; }
        .tv-brand-sub {
            font-size: 0.6875rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .22em;
            color: #7f93b2;
            margin-top: .4rem;
        }
        .tv-tagline-sub {
            font-size: 0.875rem;
            letter-spacing: .3em;
            color: #7f93b2;
            margin: 0;
        }
        .btn-primary {
            padding: .5rem 1rem;
            border: 0;
            border-radius: .5rem;
            background: #0054cd;
            color: #fff;
            font-size: .875rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            text-decoration: none;
            cursor: pointer;
        }
        .btn-primary:hover { background: #0d3264; }
        .btn-outline {
            padding: .5rem 1rem;
            border: 1px solid #e2e8f0;
            border-radius: .5rem;
            background: #fff;
            color: #475569;
            font-size: .875rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            text-decoration: none;
            cursor: pointer;
        }
        .btn-outline:hover { background: #f8fafc; }
    </style>
</head>
<body>
    @include('errors._antenne', [
        'code'    => '503',
        'titre'   => 'En maintenance',
        'message' => 'L\'application est momentanément indisponible. De retour sur l\'antenne dans quelques instants.',
        'actions' => [
            [
                'label'  => 'Réessayer',
                'reload' => true,
            ],
        ],
    ])
</body>
</html>
