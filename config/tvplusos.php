<?php

return [
    'app_name' => env('TVPLUS_APP_NAME', 'TV+AOS'),
    'qr_jwt_secret' => env('TVPLUS_QR_JWT_SECRET', 'tvplus-qr-secret-change-in-production'),

    'design_system' => [
        'primary' => '#001a41',
        'on_primary' => '#ffffff',
        'primary_container' => '#d8e2ff',
        'secondary' => '#0054cd',
        'tertiary' => '#000000',
        'surface' => '#fef7ff',
        'success' => '#007d44',
        'warning' => '#924a00',
        'info' => '#0061a4',
        'error' => '#ba1a1a',
    ],

    'roles' => [
        [
            'name' => 'admin',
            'label' => 'Administrateur',
            'level' => 100,
            'description' => 'Accès total à toutes les fonctionnalités',
        ],
        [
            'name' => 'chef_edition',
            'label' => 'Chef d\'édition',
            'level' => 80,
            'description' => 'Gestion des conducteurs, conférences, validation',
        ],
        [
            'name' => 'journaliste',
            'label' => 'Journaliste',
            'level' => 60,
            'description' => 'Rédaction, veille, reportages',
        ],
        [
            'name' => 'jri',
            'label' => 'JRI / Journaliste Reporter',
            'level' => 55,
            'description' => 'Journaliste reporter d\'images - terrain',
        ],
        [
            'name' => 'presentateur',
            'label' => 'Présentateur',
            'level' => 50,
            'description' => 'Lecture conducteur, présentation JT',
        ],
        [
            'name' => 'habilleur',
            'label' => 'Habilleur / Régisseur',
            'level' => 40,
            'description' => 'Logistique studio, QR scanner, habillage',
        ],
        [
            'name' => 'invite',
            'label' => 'Invité / Intervenant externe',
            'level' => 10,
            'description' => 'Accès lecture seule à sa fiche',
        ],
        [
            'name' => 'lecture',
            'label' => 'Lecture seule',
            'level' => 5,
            'description' => 'Consultation sans modification',
        ],
    ],

    'modules' => [
        ['key' => 'dashboard', 'label' => 'Tableau de bord', 'icon' => 'gauge-high'],
        ['key' => 'conducteurs', 'label' => 'Conducteurs (Rédaction / Émission)', 'icon' => 'clipboard-list'],
        ['key' => 'emissions', 'label' => 'Émissions', 'icon' => 'folder-open'],
        ['key' => 'planning', 'label' => 'Planification', 'icon' => 'calendar-days'],
        ['key' => 'conferences', 'label' => 'Conférences de rédaction', 'icon' => 'people-group'],
        ['key' => 'veille_sources', 'label' => 'Veille - Sources', 'icon' => 'globe'],
        ['key' => 'veille_sujets', 'label' => 'Veille - Sujets', 'icon' => 'lightbulb'],
        ['key' => 'veille_detail', 'label' => 'Veille - Détail & Remixes IA', 'icon' => 'magnifying-glass'],
        ['key' => 'journalistes', 'label' => 'Journalistes', 'icon' => 'user-pen'],
        ['key' => 'intervenants', 'label' => 'Intervenants', 'icon' => 'users'],
        ['key' => 'intervenants_suivi', 'label' => 'Suivi logistique intervenants', 'icon' => 'clock-rotate-left'],
        ['key' => 'scanner_qr', 'label' => 'Scanner QR', 'icon' => 'qrcode'],
        ['key' => 'ressources', 'label' => 'Médiathèque / Ressources', 'icon' => 'photo-film'],
        ['key' => 'archives', 'label' => 'Archives', 'icon' => 'box-archive'],
        ['key' => 'reglages', 'label' => 'Paramètres', 'icon' => 'gear'],
        ['key' => 'permissions', 'label' => 'Utilisateurs & Permissions', 'icon' => 'shield-halved'],
        ['key' => 'diffusion', 'label' => 'Diffusion & Streaming', 'icon' => 'satellite'],
        ['key' => 'rapports', 'label' => 'Rapports & Analytics', 'icon' => 'chart-column'],
        ['key' => 'presentateur_view', 'label' => 'Vue Présentateur', 'icon' => 'microphone'],
        ['key' => 'sdk_api', 'label' => 'API & Intégrations', 'icon' => 'plug'],
    ],

    'storage' => [
        'default_storage_to' => '2_Go',
        'max_upload_size_mb' => 500,
        'allowed_mimes' => [
            'image' => ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'],
            'video' => ['mp4', 'mov', 'avi', 'mkv', 'webm'],
            'audio' => ['mp3', 'wav', 'ogg', 'aac'],
            'document' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'],
        ],
    ],

    'logistique' => [
        'postes' => ['accueil', 'maquillage', 'costume', 'repetition', 'studio', 'cantine', 'sortie', 'admin'],
    ],

    'scraping' => [
        'default_types' => ['html', 'rss', 'api'],
        'max_items_per_source' => 50,
        'throttle_seconds_per_source' => 2,
    ],

    'ai_providers' => [
        'openai' => ['enabled' => true, 'model_default' => 'gpt-4o-mini'],
        'anthropic' => ['enabled' => true, 'model_default' => 'claude-3-haiku'],
        'google' => ['enabled' => true, 'model_default' => 'gemini-1.5-flash'],
        'openrouter' => ['enabled' => true, 'model_default' => 'microsoft/phi-3'],
    ],
];
