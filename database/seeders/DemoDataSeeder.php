<?php

namespace Database\Seeders;

use App\Enums\CiviliteEnum;
use App\Enums\FonctionJournalisteEnum;
use App\Enums\PoleRubriqueEnum;
use App\Enums\RolePresentateurEmissionEnum;
use App\Enums\StatutDispoIntervenantEnum;
use App\Enums\StatutEmissionEnum;
use App\Enums\StatutJournalisteEnum;
use App\Enums\TypeEmissionEnum;
use App\Enums\TypeIntervenantEnum;
use App\Models\Emission;
use App\Models\Intervenant;
use App\Models\Journaliste;
use App\Models\SourceVeille;
use App\Models\User;
use App\Models\VeilleSubject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $chefEdition = Role::firstOrCreate(['name' => 'chef_edition', 'guard_name' => 'web']);
        $journalisteRole = Role::firstOrCreate(['name' => 'journaliste', 'guard_name' => 'web']);
        $presentateur = Role::firstOrCreate(['name' => 'presentateur', 'guard_name' => 'web']);
        $habilleur = Role::firstOrCreate(['name' => 'habilleur', 'guard_name' => 'web']);
        $lecture = Role::firstOrCreate(['name' => 'lecture', 'guard_name' => 'web']);
        $receptionniste = Role::firstOrCreate(['name' => 'receptionniste', 'guard_name' => 'web']);
        $chefPlateau = Role::firstOrCreate(['name' => 'chef_plateau', 'guard_name' => 'web']);
        $chefRedaction = Role::firstOrCreate(['name' => 'chef_redaction', 'guard_name' => 'web']);
        $directeurGeneral = Role::firstOrCreate(['name' => 'directeur_general', 'guard_name' => 'web']);

        $userAdmin = User::firstOrCreate(
            ['email' => 'admin@tvplusos.tv'],
            [
                'name' => 'Administrateur',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'role' => 'admin',
                'is_active' => true,
            ]
        );
        $userAdmin->assignRole($admin);

        $userChef = User::firstOrCreate(
            ['email' => 'christian.ekogha@tvplusos.tv'],
            [
                'name' => 'Christian Ekogha',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'role' => 'chef_edition',
                'is_active' => true,
                'password_changed_at' => now(),
            ]
        );
        $userChef->assignRole($chefEdition);

        // Compte demo Directeur Général (pas de first login forcé pour ce compte)
        $userDG = User::firstOrCreate(
            ['email' => 'dg@tvplusos.tv'],
            [
                'name' => 'Directeur Général',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'role' => 'directeur_general',
                'is_active' => true,
                'password_changed_at' => now(),
            ]
        );
        $userDG->assignRole($directeurGeneral);

        // 15 comptes utilisateurs Journalistes (existant Breeze sprint 0) — tous password_changed_at=now() pour leur éviter le first-login (existant)
        User::factory()
            ->count(15)
            ->create(['password_changed_at' => now()])
            ->each(fn (User $u) => $u->assignRole($journalisteRole));

        // =====================================================================
        // SPEC §2 — 40 JOURNALISTES (RH)
        // =====================================================================
        if (Journaliste::count() === 0) {
            // 1er: Christian EKOGA = Chef d'édition (lié à son compte user #2)
            $christian = Journaliste::create([
                'matricule' => 'J-2019-0001',
                'civilite' => CiviliteEnum::Monsieur,
                'nom' => 'EKOGA',
                'prenom' => 'Christian',
                'first_name' => 'Christian',
                'last_name' => 'EKOGA',
                'date_naissance' => '1980-07-12',
                'lieu_naissance' => 'Libreville',
                'fonction' => FonctionJournalisteEnum::ChefEdition,
                'pole' => PoleRubriqueEnum::Actualite,
                'statut' => StatutJournalisteEnum::Actif,
                'date_embauche' => '2019-03-15',
                'specialites' => ['Direction', 'Management', 'Grands reportages', 'Politique', 'Interview VIP'],
                'email' => 'christian.ekogha@tvplusos.tv',
                'telephone' => '+241 07 44 00 00 01',
                'phone' => '+241 07 44 00 00 01',
                'adresse' => 'Libreville, Gabon',
                'notes_admin' => 'Chef d\'édition référent TV+AOS — compte chef_edition utilisateur #2.',
                'user_id' => $userChef->id,
                'is_presentateur' => true,
                'date_promotion_presentateur' => now()->subMonths(24),
            ]);

            // 39 journalistes aléatoires réalistes Gabon
            Journaliste::factory()
                ->count(39)
                ->create();

            // Parmi les 39 → promouvoir 4 journalistes au rôle de présentateur
            $journalistes = Journaliste::query()
                ->where('id', '!=', $christian->id)
                ->whereIn('fonction', [
                    FonctionJournalisteEnum::Journaliste->value,
                    FonctionJournalisteEnum::ChefRubrique->value,
                    FonctionJournalisteEnum::Redacteur->value,
                ])
                ->where('statut', StatutJournalisteEnum::Actif->value)
                ->inRandomOrder()
                ->limit(4)
                ->get();
            foreach ($journalistes as $j) {
                $j->promouvoirPresentateur(true);
                // lier role presentateur aux users 15 journalistes si un user existe random
                if ($j->user_id) {
                    try {
                        $u = User::find($j->user_id);
                        if ($u && ! $u->hasRole($presentateur)) {
                            $u->assignRole($presentateur);
                        }
                    } catch (\Throwable) { /* ignore */
                    }
                }
            }
        }

        // =====================================================================
        // SPEC §3 — 60 INTERVENANTS EXTERNES (incluant types politique/expert/sc)
        // (QR code auto-généré au created via boot Intervenant!)
        // =====================================================================
        if (Intervenant::count() === 0) {
            $politicien = max(1, intdiv(60, 5));  // ~12
            $expert = max(1, intdiv(60, 4));  // ~15
            $societeC = max(1, intdiv(60, 6));  // ~10
            $artiste = max(1, intdiv(60, 8));  // ~8
            $sportif = max(1, intdiv(60, 9));  // ~7
            $correspon = max(1, intdiv(60, 12)); // ~5
            $reste = 60 - ($politicien + $expert + $societeC + $artiste + $sportif + $correspon);
            if ($reste < 0) {
                $reste = 0;
            }

            Intervenant::factory()->politicien()->count($politicien)->create();
            Intervenant::factory()->expert()->count($expert)->create();
            Intervenant::factory()->societeCivil()->count($societeC)->create();
            Intervenant::factory()->artiste()->count($artiste)->create();
            Intervenant::factory()->sportif()->count($sportif)->create();
            Intervenant::factory()->count($correspon)
                ->state(fn () => ['type' => TypeIntervenantEnum::Correspondant])
                ->create();
            if ($reste > 0) {
                Intervenant::factory()->count($reste)->create();
            }
            // Interdire 2 intervenants (pour test statut blacklist)
            Intervenant::query()->inRandomOrder()->limit(2)->get()->each(
                static fn (Intervenant $i) => $i->forceFill([
                    'disponibilite' => StatutDispoIntervenantEnum::Interdit,
                    'is_active' => false,
                    'observations_admin' => 'Blacklisté temporairement — non convivial / sujet sensible.',
                ])->save()
            );
        }

        Schema::enableForeignKeyConstraints();

        // Catalogue de référence TV+ Afrique (ajout idempotent, sans écraser les fiches existantes).
        $this->call(EmissionCatalogSeeder::class);

        // =====================================================================
        // SPEC ÉMISSIONS — 12 émissions catalogue 1:1 prototype HTML
        // =====================================================================
        if (Emission::count() === 0) {
            $presentateurs = Journaliste::presentateurs()->orderBy('id')->limit(5)->get();
            if ($presentateurs->count() < 3) {
                $presentateurs = Journaliste::query()
                    ->where('is_active', true)
                    ->inRandomOrder()
                    ->limit(5)
                    ->get();
            }
            $userIds = User::limit(5)->pluck('id');
            $chefId = $userChef?->id ?? $userIds->first();

            $emissionsDefinitions = [
                [
                    'nom' => "MID'INFOS", 'sous_titre' => 'Le journal de midi · Débrief & décryptage',
                    'type' => TypeEmissionEnum::JT, 'categorie' => 'Actualités', 'cible_audience' => 'Tout public',
                    'langues' => ['Français'],
                    'heure_debut' => '12:30', 'heure_fin' => '13:20', 'duree_minutes' => 50, 'fuseau_horaire' => 'Africa/Libreville',
                    'jours_diffusion' => ['lun', 'mar', 'mer', 'jeu', 'ven'], 'date_lancement' => now()->subMonths(6)->format('Y-m-d'),
                    'format_image' => '16:9', 'qualite' => 'HD 1080p', 'couleur_habillage' => '#b91c1cFF', 'police_titres' => 'Inter',
                    'statut' => StatutEmissionEnum::En_cours,
                    'chef_edition_id' => $chefId, 'principal_idx' => 0, 'roles' => [0 => 'Principal', 1 => 'Remplacant'],
                ],
                [
                    'nom' => 'JT SOIR', 'sous_titre' => "L'édition du soir · Faits marquants",
                    'type' => TypeEmissionEnum::JT, 'categorie' => 'Actualités', 'cible_audience' => 'Grand public',
                    'langues' => ['Français'],
                    'heure_debut' => '20:00', 'heure_fin' => '21:00', 'duree_minutes' => 60, 'fuseau_horaire' => 'Africa/Libreville',
                    'jours_diffusion' => ['lun', 'mar', 'mer', 'jeu', 'ven', 'sam', 'dim'], 'date_lancement' => now()->subMonths(12)->format('Y-m-d'),
                    'format_image' => '16:9', 'qualite' => 'HD 1080p', 'couleur_habillage' => '#1e3a8aFF', 'police_titres' => 'Montserrat',
                    'statut' => StatutEmissionEnum::Planifiee,
                    'chef_edition_id' => $chefId, 'principal_idx' => 1, 'roles' => [1 => 'Principal', 0 => 'Remplacant', 2 => 'InviteRegulier'],
                ],
                [
                    'nom' => 'MATINALE 6H', 'sous_titre' => 'Réveil info · Première édition',
                    'type' => TypeEmissionEnum::Mag, 'categorie' => 'Société', 'cible_audience' => 'Grand public',
                    'langues' => ['Français'],
                    'heure_debut' => '06:00', 'heure_fin' => '07:30', 'duree_minutes' => 90, 'fuseau_horaire' => 'Africa/Libreville',
                    'jours_diffusion' => ['lun', 'mar', 'mer', 'jeu', 'ven'], 'date_lancement' => now()->subMonths(4)->format('Y-m-d'),
                    'format_image' => '16:9', 'qualite' => 'HD 1080p', 'couleur_habillage' => '#f59e0bFF', 'police_titres' => 'Inter',
                    'statut' => StatutEmissionEnum::Planifiee,
                    'chef_edition_id' => $chefId, 'principal_idx' => 2, 'roles' => [2 => 'Principal', 3 => 'Remplacant'],
                ],
                [
                    'nom' => 'CULTURE+360', 'sous_titre' => 'Magazine culture · Arts & société',
                    'type' => TypeEmissionEnum::Culture, 'categorie' => 'Culture & Arts', 'cible_audience' => 'Grand public',
                    'langues' => ['Français'],
                    'heure_debut' => '18:00', 'heure_fin' => '19:00', 'duree_minutes' => 60, 'fuseau_horaire' => 'Africa/Libreville',
                    'jours_diffusion' => ['sam', 'dim'], 'date_lancement' => now()->subMonths(3)->format('Y-m-d'),
                    'format_image' => '16:9', 'qualite' => '4K UHD', 'couleur_habillage' => '#7c3aedFF', 'police_titres' => 'Playfair Display',
                    'statut' => StatutEmissionEnum::Planifiee,
                    'chef_edition_id' => $chefId, 'principal_idx' => 3, 'roles' => [3 => 'Principal', 4 => 'InviteRegulier'],
                ],
                [
                    'nom' => 'ÉCO MONDE', 'sous_titre' => "L'économie décryptée · Marchés & décisions",
                    'type' => TypeEmissionEnum::Mag, 'categorie' => 'Économie', 'cible_audience' => 'Professionnels / Décideurs',
                    'langues' => ['Français', 'Anglais'],
                    'heure_debut' => '21:30', 'heure_fin' => '22:15', 'duree_minutes' => 45, 'fuseau_horaire' => 'Africa/Libreville',
                    'jours_diffusion' => ['lun', 'mer', 'ven'], 'date_lancement' => now()->subMonths(2)->format('Y-m-d'),
                    'format_image' => '16:9', 'qualite' => 'HD 1080p', 'couleur_habillage' => '#059669FF', 'police_titres' => 'Inter',
                    'statut' => StatutEmissionEnum::Diffusee,
                    'chef_edition_id' => $chefId, 'principal_idx' => 0, 'roles' => [0 => 'Principal', 2 => 'Remplacant'],
                ],
                [
                    'nom' => 'SPORT 24', 'sous_titre' => 'Tous les sports · Résumés & interviews',
                    'type' => TypeEmissionEnum::Sport, 'categorie' => 'Sport', 'cible_audience' => 'Jeune public (15-25)',
                    'langues' => ['Français'],
                    'heure_debut' => '22:30', 'heure_fin' => '23:30', 'duree_minutes' => 60, 'fuseau_horaire' => 'Africa/Libreville',
                    'jours_diffusion' => ['mar', 'jeu', 'sam'], 'date_lancement' => now()->subMonths(5)->format('Y-m-d'),
                    'format_image' => '16:9', 'qualite' => 'HD 1080p', 'couleur_habillage' => '#0891b2FF', 'police_titres' => 'Oswald',
                    'statut' => StatutEmissionEnum::Planifiee,
                    'chef_edition_id' => $chefId, 'principal_idx' => 1, 'roles' => [1 => 'Principal', 4 => 'Remplacant'],
                ],
                [
                    'nom' => 'PARLONS-EN', 'sous_titre' => 'Talk-show quotidien · Sujets de société',
                    'type' => TypeEmissionEnum::Talk, 'categorie' => 'Société', 'cible_audience' => 'Tout public',
                    'langues' => ['Français'],
                    'heure_debut' => '15:00', 'heure_fin' => '16:30', 'duree_minutes' => 90, 'fuseau_horaire' => 'Africa/Libreville',
                    'jours_diffusion' => ['lun', 'mar', 'mer', 'jeu', 'ven'], 'date_lancement' => now()->subMonths(1)->format('Y-m-d'),
                    'format_image' => '16:9', 'qualite' => 'HD 1080p', 'couleur_habillage' => '#4f46e5FF', 'police_titres' => 'Inter',
                    'statut' => StatutEmissionEnum::Brouillon,
                    'chef_edition_id' => $chefId, 'principal_idx' => 2, 'roles' => [2 => 'Principal', 3 => 'InviteRegulier'],
                ],
                [
                    'nom' => 'DEBAT NATIONAL', 'sous_titre' => 'Table ronde · Décideurs & citoyens',
                    'type' => TypeEmissionEnum::Debat, 'categorie' => 'Politique', 'cible_audience' => 'Grand public',
                    'langues' => ['Français'],
                    'heure_debut' => '20:30', 'heure_fin' => '22:00', 'duree_minutes' => 90, 'fuseau_horaire' => 'Africa/Libreville',
                    'jours_diffusion' => ['jeu'], 'date_lancement' => now()->subMonths(2)->format('Y-m-d'),
                    'format_image' => '16:9', 'qualite' => '4K UHD', 'couleur_habillage' => '#dc2626FF', 'police_titres' => 'Roboto',
                    'statut' => StatutEmissionEnum::Planifiee,
                    'chef_edition_id' => $chefId, 'principal_idx' => 0, 'roles' => [0 => 'Principal', 1 => 'InviteRegulier', 2 => 'InviteRegulier'],
                ],
                [
                    'nom' => "LES GENS D'ICI", 'sous_titre' => 'Portraits · Histoires humaines du Gabon',
                    'type' => TypeEmissionEnum::Mag, 'categorie' => 'Société', 'cible_audience' => 'Grand public',
                    'langues' => ['Français'],
                    'heure_debut' => '19:30', 'heure_fin' => '20:15', 'duree_minutes' => 45, 'fuseau_horaire' => 'Africa/Libreville',
                    'jours_diffusion' => ['dim'], 'date_lancement' => now()->subMonths(1)->format('Y-m-d'),
                    'format_image' => '9:16', 'qualite' => '4K UHD', 'couleur_habillage' => '#db2777FF', 'police_titres' => 'Playfair Display',
                    'statut' => StatutEmissionEnum::Brouillon,
                    'chef_edition_id' => $chefId, 'principal_idx' => 4, 'roles' => [4 => 'Principal', 3 => 'Remplacant'],
                ],
                [
                    'nom' => 'INTERNATIONAL WEEK', 'sous_titre' => "L'actualité monde décryptée",
                    'type' => TypeEmissionEnum::Mag, 'categorie' => 'International', 'cible_audience' => 'Professionnels / Décideurs',
                    'langues' => ['Français', 'Anglais', 'Espagnol'],
                    'heure_debut' => '17:00', 'heure_fin' => '18:00', 'duree_minutes' => 60, 'fuseau_horaire' => 'Africa/Libreville',
                    'jours_diffusion' => ['sam'], 'date_lancement' => now()->subDays(20)->format('Y-m-d'),
                    'format_image' => '16:9', 'qualite' => 'HD 1080p', 'couleur_habillage' => '#0ea5e9FF', 'police_titres' => 'Inter',
                    'statut' => StatutEmissionEnum::Planifiee,
                    'chef_edition_id' => $chefId, 'principal_idx' => 3, 'roles' => [3 => 'Principal', 0 => 'Remplacant'],
                ],
                [
                    'nom' => 'SANTÉ VIE', 'sous_titre' => 'Magazine santé & bien-être',
                    'type' => TypeEmissionEnum::Mag, 'categorie' => 'Société', 'cible_audience' => 'Tout public',
                    'langues' => ['Français'],
                    'heure_debut' => '10:00', 'heure_fin' => '10:45', 'duree_minutes' => 45, 'fuseau_horaire' => 'Africa/Libreville',
                    'jours_diffusion' => ['mar', 'jeu'], 'date_lancement' => now()->subDays(15)->format('Y-m-d'),
                    'format_image' => '4:3', 'qualite' => 'SD 720p', 'couleur_habillage' => '#10b981FF', 'police_titres' => 'Inter',
                    'statut' => StatutEmissionEnum::Diffusee,
                    'chef_edition_id' => $chefId, 'principal_idx' => 2, 'roles' => [2 => 'Principal', 4 => 'InviteRegulier'],
                ],
                [
                    'nom' => 'WEEKEND RADIO', 'sous_titre' => 'Programme radio du samedi & dimanche',
                    'type' => TypeEmissionEnum::Radio, 'categorie' => 'Actualités', 'cible_audience' => 'Tout public',
                    'langues' => ['Français'],
                    'heure_debut' => '08:00', 'heure_fin' => '10:00', 'duree_minutes' => 120, 'fuseau_horaire' => 'Africa/Libreville',
                    'jours_diffusion' => ['sam', 'dim'], 'date_lancement' => now()->subDays(10)->format('Y-m-d'),
                    'format_image' => '4:3', 'qualite' => 'SD 720p', 'couleur_habillage' => '#4b5563FF', 'police_titres' => 'Roboto',
                    'statut' => StatutEmissionEnum::Brouillon,
                    'chef_edition_id' => $chefId, 'principal_idx' => 1, 'roles' => [1 => 'Principal', 0 => 'Remplacant'],
                ],
            ];

            foreach ($emissionsDefinitions as $def) {
                try {
                    DB::transaction(function () use ($def, $presentateurs) {
                        $rolesMap = $def['roles'] ?? [];
                        $principalIdx = $def['principal_idx'] ?? 0;
                        unset($def['roles'], $def['principal_idx']);
                        /** @var Emission $em */
                        $em = Emission::create($def);
                        $ordre = 1;
                        $roleMapLabels = [
                            'Principal' => RolePresentateurEmissionEnum::Principal,
                            'Remplacant' => RolePresentateurEmissionEnum::Remplacant,
                            'InviteRegulier' => RolePresentateurEmissionEnum::InviteRegulier,
                        ];
                        foreach ($rolesMap as $jIdx => $roleLabel) {
                            $j = $presentateurs[$jIdx] ?? null;
                            if (! $j) {
                                continue;
                            }
                            $statut = $ordre === 1 ? 'Confirme' : (random_int(0, 100) > 60 ? 'En_attente' : 'Confirme');
                            $em->ajouterPresentateur($j, $roleMapLabels[$roleLabel] ?? RolePresentateurEmissionEnum::InviteRegulier, $statut, $ordre);
                            $ordre++;
                        }
                    });
                } catch (\Throwable $e) {
                    report($e);

                    continue;
                }
            }
        }

        // =====================================================================
        // SPEC VEILLE — 3 sources témoins (prototype source_vielle.html)
        // =====================================================================
        if (SourceVeille::count() === 0) {
            $this->call(SourceVeilleSeeder::class);
        }

        // Sources Afrique ciblées : ajout idempotent, conserve les paramètres
        // déjà ajustés par l'équipe depuis l'interface.
        $this->call(VeilleSourcesAfriqueSeeder::class);

        // =====================================================================
        // SPEC VEILLE — 4 sujets démo prototype (sujet_veille.html cartes 1-4)
        // =====================================================================
        if (VeilleSubject::count() === 0) {
            $this->call(VeilleSubjectSeeder::class);
        }
    }
}
