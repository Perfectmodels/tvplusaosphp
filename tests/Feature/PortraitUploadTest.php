<?php

namespace Tests\Feature;

use App\Models\Intervenant;
use App\Models\Journaliste;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PortraitUploadTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('admin', 'web'));

        return $user;
    }

    public static function personnes(): array
    {
        return ['journaliste' => [Journaliste::class, 'journalistes'], 'intervenant' => [Intervenant::class, 'intervenants']];
    }

    private function payload(string $module): array
    {
        return ['nom' => 'Portrait testé', 'prenom' => 'Personne', 'email' => fake()->unique()->safeEmail(), 'type' => 'invite'];
    }

    #[DataProvider('personnes')]
    public function test_le_fichier_local_est_stocke_et_possede_une_miniature(string $class, string $module): void
    {
        Storage::fake('public');
        config(['media-library.image_driver' => 'gd']);
        $file = UploadedFile::fake()->image('portrait.png', 400, 400);
        $this->actingAs($this->admin())->post(route($module.'.store'), [...$this->payload($module), 'photo_file' => $file])
            ->assertSessionHasNoErrors()->assertRedirect();
        $personne = $class::firstOrFail();
        $media = $personne->getFirstMedia('portrait');
        $this->assertNotNull($media);
        Storage::disk('public')->assertExists($media->getPathRelativeToRoot());
        $this->assertTrue($media->hasGeneratedConversion('thumb'));
        Storage::disk('public')->assertExists($media->getPathRelativeToRoot('thumb'));
        $this->assertSame($media->getUrl('thumb'), $personne->photoUrl);
    }

    #[DataProvider('personnes')]
    public function test_un_faux_fichier_image_est_refuse_avant_de_creer_la_fiche(string $class, string $module): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin())->post(route($module.'.store'), [...$this->payload($module),
            'photo_file' => UploadedFile::fake()->createWithContent('portrait.jpg', '<script>faux portrait</script>'),
        ])->assertSessionHasErrors('photo_file');
        $this->assertSame(0, $class::count());
        $this->assertDatabaseCount('media', 0);
    }

    #[DataProvider('personnes')]
    public function test_une_image_trop_lourde_est_refusee(string $class, string $module): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin())->post(route($module.'.store'), [...$this->payload($module),
            'photo_file' => UploadedFile::fake()->image('portrait.jpg')->size(2049),
        ])->assertSessionHasErrors('photo_file');
        $this->assertSame(0, $class::count());
    }

    #[DataProvider('personnes')]
    public function test_la_photo_peut_etre_retiree_depuis_la_fiche(string $class, string $module): void
    {
        Storage::fake('public');
        config(['media-library.image_driver' => 'gd']);
        $personne = $class::factory()->create();
        $personne->addMedia(UploadedFile::fake()->image('portrait.png'))->toMediaCollection('portrait', 'public');
        $this->actingAs($this->admin())->put(route($module.'.update', $personne), [
            ...$this->payload($module), 'remove_photo' => 1, 'photo' => '',
            'matricule' => $personne->matricule,
        ])->assertSessionHasNoErrors();
        $this->assertSame(0, $personne->fresh()->getMedia('portrait')->count());
        $this->assertNull($personne->fresh()->photo);
    }
}
