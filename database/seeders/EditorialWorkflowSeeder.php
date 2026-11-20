<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\StatutEvenementEditorialEnum;
use App\Enums\StatutVerificationEditorialEnum;
use App\Models\EditorialEvent;
use App\Models\EditorialStory;
use App\Models\EditorialVerification;
use App\Models\SourceVeille;
use App\Models\User;
use App\Models\VeilleSubject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class EditorialWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::findOrCreate('chef_edition', 'web');
        $manager = User::firstOrCreate(
            ['email' => 'chef.edition.workflow@local.test'],
            [
                'name' => 'Chef d’édition — démonstration',
                'password' => Hash::make(Str::random(40)),
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );
        $manager->assignRole($role);

        $source = SourceVeille::firstOrCreate(
            ['name' => 'Source de démonstration — TV+A OS'],
            [
                'type' => 'manual',
                'url' => 'https://93.184.215.14/editorial-workflow',
                'is_active' => true,
            ]
        );

        $veilleSubject = VeilleSubject::firstOrCreate(
            ['slug' => 'demo-approvisionnement-eau-libreville'],
            [
                'source_id' => $source->id,
                'title' => 'Approvisionnement en eau dans le Grand Libreville',
                'summary' => 'Élément de veille destiné à illustrer le workflow éditorial.',
                'status' => 'pending',
                'priority' => 5,
                'scraped_at' => now(),
            ]
        );

        $event = EditorialEvent::firstOrCreate(
            ['title' => 'Approvisionnement en eau dans le Grand Libreville'],
            [
                'description' => 'Événement éditorial de démonstration.',
                'status' => StatutEvenementEditorialEnum::Verifie,
                'created_by' => $manager->id,
                'verified_by' => $manager->id,
                'verified_at' => now(),
            ]
        );
        $event->sourcesVeille()->syncWithoutDetaching([
            $veilleSubject->id => ['linked_by' => $manager->id],
        ]);

        EditorialVerification::firstOrCreate(
            [
                'editorial_event_id' => $event->id,
                'type' => 'source_confirmation',
                'claim' => 'L’élément a été examiné par un responsable éditorial.',
            ],
            [
                'status' => StatutVerificationEditorialEnum::Confirmee,
                'evidence' => 'Donnée de démonstration uniquement.',
                'created_by' => $manager->id,
                'checked_by' => $manager->id,
                'checked_at' => now(),
            ]
        );

        EditorialStory::firstOrCreate(
            [
                'editorial_event_id' => $event->id,
                'title' => 'Les difficultés d’accès à l’eau dans les quartiers de Libreville',
            ],
            [
                'summary' => 'Sujet de démonstration à compléter par la rédaction.',
                'angle' => 'Conséquences concrètes pour les habitants.',
                'status' => 'draft',
                'created_by' => $manager->id,
            ]
        );
    }
}
