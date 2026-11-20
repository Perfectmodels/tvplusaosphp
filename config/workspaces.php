<?php

return [
    'roles' => [
        'chef_edition' => 'Chef d’édition',
        'chef_redaction' => 'Rédacteur en chef',
        'journaliste' => 'Journaliste',
        'realisateur' => 'Réalisateur',
        'community_manager' => 'Community Manager',
        'correspondant' => 'Correspondant',
        'presentateur' => 'Présentateur',
        'monteur' => 'Monteur',
        'documentaliste' => 'Documentaliste',
        'responsable_administratif' => 'Responsable administratif',
        'directeur_general' => 'Direction générale',
        'admin' => 'Administrateur',
        'superadmin' => 'Superadministrateur',
        'chef_plateau' => 'Chef de plateau',
        'habilleur' => 'Habilleur',
        'receptionniste' => 'Réceptionniste',
        'lecture' => 'Lecture seule',
    ],
    'task_types' => ['editorial' => 'Rédaction / validation', 'terrain' => 'Terrain', 'montage' => 'Montage', 'habillage' => 'Habillage', 'antenne' => 'Antenne / réalisation', 'digital' => 'Publication numérique', 'documentation' => 'Documentation', 'administration' => 'Administration', 'other' => 'Autre'],
    'task_statuses' => ['todo' => 'À faire', 'in_progress' => 'En cours', 'blocked' => 'Bloquée', 'done' => 'Terminée'],
    'task_types_by_role' => ['journaliste' => ['terrain', 'editorial'], 'correspondant' => ['terrain'], 'monteur' => ['montage'], 'habilleur' => ['habillage'], 'realisateur' => ['antenne'], 'presentateur' => ['antenne'], 'chef_plateau' => ['antenne'], 'community_manager' => ['digital'], 'documentaliste' => ['documentation'], 'responsable_administratif' => ['administration']],
    'digital_platforms' => ['site' => 'Site web', 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'tiktok' => 'TikTok', 'x' => 'X', 'whatsapp' => 'WhatsApp'],
    'digital_statuses' => ['draft' => 'Brouillon', 'awaiting_validation' => 'À valider', 'changes_requested' => 'Correction demandée', 'approved' => 'Validé', 'scheduled' => 'Planifié', 'published' => 'Publié'],
];
