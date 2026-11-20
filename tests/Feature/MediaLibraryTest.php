<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Intervenant;
use App\Models\Journaliste;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Spatie Media Library — la table `media` doit exister.
 *
 * Régression : elle n'avait jamais été migrée, donc toute suppression d'un
 * Journaliste ou d'un Intervenant (qui portent des photos via Media Library)
 * levait « Base table or view not found: 'media' doesn't exist ».
 */
class MediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_table_media_existe(): void
    {
        $this->assertTrue(Schema::hasTable('media'));
    }

    public function test_supprimer_un_journaliste_ne_leve_pas_erreur(): void
    {
        $journaliste = Journaliste::create([
            'nom' => 'Ndiaye',
            'prenom' => 'Awa',
            'is_active' => true,
        ]);

        $journaliste->delete();

        $this->assertDatabaseMissing('journalistes', ['id' => $journaliste->id]);
    }

    public function test_supprimer_un_intervenant_ne_leve_pas_erreur(): void
    {
        $intervenant = Intervenant::create([
            'nom' => 'Mballa',
            'prenom' => 'Jean',
        ]);

        $intervenant->delete();

        $this->assertDatabaseMissing('intervenants', ['id' => $intervenant->id]);
    }

    public function test_supprimer_un_modele_purge_ses_medias(): void
    {
        $intervenant = Intervenant::create([
            'nom' => 'Fouda',
            'prenom' => 'Marie',
        ]);

        // La collection « documents » accepte le texte simple (sans conversion d'image).
        $intervenant->addMediaFromString('Contenu du document.', 'note.txt')
            ->preservingOriginal()
            ->toMediaCollection('documents');

        $this->assertSame(1, $intervenant->getMedia('documents')->count());

        $intervenant->delete();

        $this->assertSame(0, \Spatie\MediaLibrary\MediaCollections\Models\Media::count());
    }

    public function test_une_image_de_type_non_autorisee_est_rejetee(): void
    {
        $intervenant = Intervenant::create(['nom' => 'Test', 'prenom' => 'Upload']);

        $this->expectException(\Spatie\MediaLibrary\MediaCollections\Exceptions\FileUnacceptableForCollection::class);

        // Un SVG (vecteur XSS) ne doit jamais entrer dans une collection.
        $intervenant->addMediaFromString('<svg onload="alert(1)"></svg>', 'vector.svg')
            ->toMediaCollection('documents');
    }
}
