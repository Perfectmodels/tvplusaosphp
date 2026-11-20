<?php

$leaders = ['admin', 'superadmin', 'directeur_general', 'chef_edition', 'chef_redaction'];
$adminAndEdition = ['admin', 'superadmin', 'chef_edition'];
$conductorReaders = [
    ...$leaders,
    'journaliste',
    'presentateur',
    'habilleur',
    'chef_plateau',
    'receptionniste',
    'lecture',
    'correspondant',
    'realisateur',
    'monteur',
    'responsable_administratif',
];
$journalisteRoles = ['journaliste', 'correspondant'];
$intervenantReaders = ['journaliste', 'presentateur', 'receptionniste', 'habilleur', 'chef_plateau'];
$journalisteReaders = [...$intervenantReaders, 'responsable_administratif'];
$editorialContributors = [...$leaders, ...$journalisteRoles];
$editorialReaders = [
    'admin',
    'directeur_general',
    'chef_edition',
    'chef_redaction',
    'journaliste',
    'correspondant',
    'presentateur',
    'habilleur',
    'lecture',
];
$digitalManagers = ['admin', 'superadmin', 'directeur_general', 'chef_redaction', 'chef_edition'];
$veilleManagers = ['admin', 'superadmin', 'chef_edition', 'chef_redaction'];
$allRoles = array_keys((require __DIR__.'/workspaces.php')['roles']);

$modules = [
    'workspace' => [
        'label' => 'Espace métier partagé',
        'permissions' => [
            'workspace.view' => 'Consulter les dossiers dont on fait partie',
            'workspace.view_all' => 'Consulter tous les dossiers partagés',
            'workspace.manage_members' => 'Gérer les participants d’un dossier',
            'workspace.comment' => 'Échanger dans un dossier partagé',
            'workspace.create_tasks' => 'Attribuer des tâches à l’équipe d’un dossier',
            'workspace.change_tasks' => 'Faire avancer ses tâches',
            'workspace.manage_tasks' => 'Faire avancer et réattribuer les tâches de l’équipe',
            'workspace.upload' => 'Joindre un document ou un média au dossier',
        ],
    ],
    'dashboard' => [
        'label' => 'Espaces de travail',
        'permissions' => [
            'dashboard.view' => 'Consulter son tableau de bord',
        ],
    ],
    'conducteurs' => [
        'label' => 'Conducteurs et conférences',
        'permissions' => [
            'conducteurs.view' => 'Consulter les conducteurs',
            'conducteurs.create' => 'Créer un conducteur',
            'conducteurs.edit_all' => 'Modifier tous les conducteurs',
            'conducteurs.edit_own' => 'Modifier ses propres conducteurs',
            'conducteurs.validate' => 'Valider un conducteur',
            'conducteurs.transfer' => 'Transférer un conducteur vers une émission',
            'conducteurs.unlock' => 'Déverrouiller un conducteur',
            'conducteurs.delete' => 'Supprimer un conducteur',
            'conducteurs.reorder' => 'Réorganiser les séquences du conducteur',
            'conducteurs.pilot_air' => 'Piloter le chronomètre antenne',
            'presence.view' => 'Consulter les présences de conférence',
            'presence.manage' => 'Gérer les présences de l’équipe',
            'presence.mark_self' => 'Enregistrer sa propre présence',
        ],
    ],
    'sequences' => [
        'label' => 'Séquences et production',
        'permissions' => [
            'sequences.view' => 'Consulter les séquences',
            'sequences.create_editorial' => 'Créer un sujet dans un conducteur de rédaction',
            'sequences.edit_all' => 'Modifier toutes les séquences éditoriales',
            'sequences.edit_own' => 'Modifier ses propres séquences éditoriales',
            'sequences.delete_all' => 'Supprimer toutes les séquences éditoriales',
            'sequences.delete_own' => 'Supprimer ses propres séquences éditoriales',
            'sequences.manage_broadcast' => 'Préparer les séquences antenne',
            'sequences.delete_broadcast' => 'Supprimer une séquence antenne',
            'sequences.reorder_broadcast' => 'Réorganiser les séquences antenne',
            'sequences.reorder_editorial' => 'Réorganiser les sujets éditoriaux',
            'sequences.workflow.terrain' => 'Mettre à jour l’étape terrain de ses sujets',
            'sequences.workflow.montage' => 'Mettre à jour l’étape montage de ses sujets',
            'sequences.workflow.montage_all' => 'Mettre à jour le montage de toute séquence',
            'sequences.workflow.habillage' => 'Mettre à jour l’habillage',
            'sequences.workflow.livraison' => 'Mettre à jour la livraison de ses sujets',
            'sequences.workflow.livraison_all' => 'Mettre à jour la livraison de toute séquence',
            'sequences.workflow.diffusion' => 'Mettre à jour la diffusion',
            'sequences.presenter_notes' => 'Modifier les notes présentateur',
            'sequences.private_notes' => 'Consulter et modifier les notes personnelles',
        ],
    ],
    'emissions' => [
        'label' => 'Émissions',
        'permissions' => [
            'emissions.view' => 'Consulter les émissions',
            'emissions.create' => 'Créer une émission',
            'emissions.update' => 'Modifier une émission',
            'emissions.delete' => 'Supprimer une émission',
        ],
    ],
    'journalistes' => [
        'label' => 'Journalistes',
        'permissions' => [
            'journalistes.view' => 'Consulter les fiches journalistes',
            'journalistes.create' => 'Créer une fiche journaliste',
            'journalistes.update' => 'Modifier une fiche journaliste',
            'journalistes.delete' => 'Supprimer une fiche journaliste',
        ],
    ],
    'intervenants' => [
        'label' => 'Intervenants',
        'permissions' => [
            'intervenants.view' => 'Consulter les intervenants',
            'intervenants.create' => 'Créer un intervenant',
            'intervenants.update' => 'Modifier un intervenant',
            'intervenants.delete' => 'Supprimer un intervenant',
            'intervenants.scan' => 'Scanner un QR de présence',
            'intervenants.qr' => 'Consulter les QR des intervenants',
        ],
    ],
    'veille' => [
        'label' => 'Veille éditoriale',
        'permissions' => [
            'sujets_veille.view' => 'Consulter les sujets de veille',
            'sujets_veille.manage' => 'Gérer les sujets de veille',
            'sources_veille.view' => 'Consulter les sources de veille',
            'sources_veille.manage' => 'Gérer les sources de veille',
            'sources_veille.scrape' => 'Lancer la collecte des sources',
            'remixes_veille.view' => 'Consulter les contenus dérivés',
            'remixes_veille.manage' => 'Créer, modifier et supprimer un contenu dérivé',
            'remixes_veille.ai' => 'Générer un contenu dérivé avec l’assistant',
            'remixes_veille.validate' => 'Valider un contenu dérivé',
        ],
    ],
    'editorial' => [
        'label' => 'Workflow éditorial',
        'permissions' => [
            'editorial.view' => 'Consulter les événements et sujets éditoriaux',
            'editorial.contribute' => 'Proposer et vérifier un sujet',
            'editorial.produce_assigned' => 'Faire avancer un sujet qui vous est affecté',
            'editorial.manage' => 'Gérer les décisions éditoriales',
            'editorial.assign' => 'Affecter un sujet à un journaliste',
            'editorial.validate' => 'Valider ou rejeter un sujet',
        ],
    ],
    'digital' => [
        'label' => 'Publications numériques',
        'permissions' => [
            'digital.view' => 'Consulter les publications',
            'digital.view_all' => 'Consulter les publications de toute l’équipe',
            'digital.create' => 'Créer une publication',
            'digital.edit_own' => 'Modifier ses propres publications',
            'digital.edit_all' => 'Modifier les publications de toute l’équipe',
            'digital.approve' => 'Valider une publication',
            'digital.publish' => 'Planifier ou publier un contenu',
        ],
    ],
    'users' => [
        'label' => 'Comptes et accès',
        'permissions' => [
            'users.view' => 'Consulter les comptes',
            'users.create' => 'Créer un compte',
            'users.update' => 'Modifier un compte',
            'users.delete' => 'Supprimer un compte',
            'users.reset_password' => 'Réinitialiser un mot de passe',
            'users.toggle_active' => 'Activer ou désactiver un compte',
            'users.assign_roles' => 'Attribuer ou retirer des rôles',
        ],
    ],
    'administration' => [
        'label' => 'Administration',
        'permissions' => [
            'activity_logs.view' => 'Consulter le journal d’activité',
            'role_access.manage' => 'Modifier la matrice des permissions',
            'dashboard.pointage' => 'Accéder au pointage de présence',
        ],
    ],
];

$grants = [
    'workspace.view' => $allRoles,
    'workspace.view_all' => $leaders,
    'workspace.manage_members' => ['admin', 'superadmin', 'chef_redaction', 'chef_edition'],
    'workspace.comment' => array_values(array_diff($allRoles, ['lecture'])),
    'workspace.create_tasks' => array_values(array_diff($allRoles, ['lecture'])),
    'workspace.change_tasks' => array_values(array_diff($allRoles, ['lecture'])),
    'workspace.manage_tasks' => ['admin', 'superadmin', 'chef_redaction', 'chef_edition'],
    'workspace.upload' => array_values(array_diff($allRoles, ['lecture'])),

    'dashboard.view' => $allRoles,
    'conducteurs.view' => $conductorReaders,
    'conducteurs.create' => ['chef_edition', 'chef_redaction'],
    'conducteurs.edit_all' => $leaders,
    'conducteurs.edit_own' => $conductorReaders,
    'conducteurs.validate' => $leaders,
    'conducteurs.transfer' => $leaders,
    'conducteurs.unlock' => $leaders,
    'conducteurs.delete' => $leaders,
    'conducteurs.reorder' => [...$leaders, 'realisateur'],
    'conducteurs.pilot_air' => [...$leaders, 'presentateur', 'chef_plateau', 'realisateur'],
    'presence.view' => $conductorReaders,
    'presence.manage' => $leaders,
    'presence.mark_self' => $conductorReaders,
    'sequences.view' => $allRoles,
    'sequences.create_editorial' => $editorialContributors,
    'sequences.edit_all' => $leaders,
    'sequences.edit_own' => $journalisteRoles,
    'sequences.delete_all' => $leaders,
    'sequences.delete_own' => $journalisteRoles,
    'sequences.manage_broadcast' => [...$leaders, 'realisateur'],
    'sequences.delete_broadcast' => [...$leaders, 'realisateur'],
    'sequences.reorder_broadcast' => [...$leaders, 'realisateur'],
    'sequences.reorder_editorial' => [...$leaders, ...$journalisteRoles],
    'sequences.workflow.terrain' => $journalisteRoles,
    'sequences.workflow.montage' => $journalisteRoles,
    'sequences.workflow.montage_all' => ['monteur'],
    'sequences.workflow.habillage' => ['habilleur'],
    'sequences.workflow.livraison' => $journalisteRoles,
    'sequences.workflow.livraison_all' => ['chef_plateau'],
    'sequences.workflow.diffusion' => ['chef_plateau'],
    'sequences.presenter_notes' => ['presentateur', 'chef_plateau'],
    'sequences.private_notes' => ['presentateur', 'chef_plateau'],
    'emissions.view' => [...$conductorReaders, 'documentaliste'],
    'emissions.create' => $adminAndEdition,
    'emissions.update' => $adminAndEdition,
    'emissions.delete' => $adminAndEdition,
    'journalistes.view' => [...$journalisteReaders, ...$leaders],
    'journalistes.create' => $adminAndEdition,
    'journalistes.update' => $adminAndEdition,
    'journalistes.delete' => $adminAndEdition,
    'intervenants.view' => [...$intervenantReaders, ...$leaders],
    'intervenants.create' => $adminAndEdition,
    'intervenants.update' => $adminAndEdition,
    'intervenants.delete' => $adminAndEdition,
    'intervenants.scan' => [...$intervenantReaders, ...$leaders],
    'intervenants.qr' => [...$intervenantReaders, ...$leaders],
    'sujets_veille.view' => ['admin', 'superadmin', 'chef_edition', 'chef_redaction', 'journaliste', 'presentateur', 'habilleur', 'correspondant'],
    'sujets_veille.manage' => $veilleManagers,
    'sources_veille.view' => ['admin', 'superadmin', 'chef_edition', 'chef_redaction', 'journaliste', 'presentateur', 'receptionniste', 'habilleur', 'chef_plateau', 'lecture'],
    'sources_veille.manage' => $veilleManagers,
    'sources_veille.scrape' => ['admin', 'superadmin', 'chef_edition'],
    'remixes_veille.view' => ['admin', 'superadmin', 'chef_edition', 'chef_redaction', 'journaliste', 'presentateur', 'habilleur'],
    'remixes_veille.manage' => $veilleManagers,
    'remixes_veille.ai' => $veilleManagers,
    'remixes_veille.validate' => $veilleManagers,
    'editorial.view' => $editorialReaders,
    'editorial.contribute' => $editorialContributors,
    'editorial.produce_assigned' => ['journaliste'],
    'editorial.manage' => $leaders,
    'editorial.assign' => $leaders,
    'editorial.validate' => $leaders,
    'digital.view' => [...$digitalManagers, 'community_manager'],
    'digital.view_all' => $digitalManagers,
    'digital.create' => ['admin', 'superadmin', 'community_manager'],
    'digital.edit_own' => ['admin', 'superadmin', 'community_manager'],
    'digital.edit_all' => ['admin', 'superadmin'],
    'digital.approve' => ['admin', 'superadmin', 'chef_redaction', 'chef_edition'],
    'digital.publish' => ['admin', 'superadmin', 'community_manager'],
    'users.view' => ['admin', 'superadmin'],
    'users.create' => ['admin', 'superadmin'],
    'users.update' => ['admin', 'superadmin'],
    'users.delete' => ['admin', 'superadmin'],
    'users.reset_password' => ['admin', 'superadmin'],
    'users.toggle_active' => ['admin', 'superadmin'],
    'users.assign_roles' => ['admin', 'superadmin'],
    'activity_logs.view' => ['admin', 'superadmin'],
    'role_access.manage' => ['admin', 'superadmin'],
    'dashboard.pointage' => ['admin', 'superadmin', 'directeur_general', 'chef_edition', 'chef_redaction', 'journaliste', 'lecture'],
];

$defaults = array_fill_keys($allRoles, []);
foreach ($grants as $permission => $roles) {
    foreach ($roles as $role) {
        $defaults[$role][] = $permission;
    }
}
$defaults['admin'] = $defaults['superadmin'] = array_keys(array_merge(...array_values(array_map(
    static fn (array $module): array => $module['permissions'],
    $modules
))));

return [
    'modules' => $modules,
    'defaults' => $defaults,
    'protected_permissions' => ['dashboard.view', 'role_access.manage', 'users.assign_roles'],
];
