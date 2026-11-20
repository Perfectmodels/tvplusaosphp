<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SourceVeille;
use App\Models\User;
use App\Models\VeilleSubject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VeilleSourcesTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $roleName): User
    {
        Role::findOrCreate($roleName, 'web');
        $user = User::factory()->create();
        $user->assignRole($roleName);

        return $user;
    }

    private function source(array $overrides = []): SourceVeille
    {
        static $id = 0;
        $id++;

        return SourceVeille::create(array_merge([
            'name' => 'Source de veille '.$id,
            'url' => 'https://93.184.215.14/feed-'.$id,
            'type' => 'rss',
            'frequency' => '15min',
            'is_active' => true,
        ], $overrides));
    }

    public function test_une_url_interne_est_refusee_pour_une_source_enregistree(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->from(route('veille.sources'))
            ->post(route('veille.sources.store'), [
                'name' => 'Source locale',
                'url' => 'http://127.0.0.1/internal-feed',
                'type' => 'rss',
                'frequency' => '15min',
            ])
            ->assertSessionHasErrors('url');

        $this->assertDatabaseMissing('source_veille', ['name' => 'Source locale']);
    }

    public function test_desactiver_une_source_preserve_la_source_et_ses_articles(): void
    {
        $admin = $this->userWithRole('admin');
        $source = $this->source();
        $subject = VeilleSubject::create([
            'source_id' => $source->id,
            'title' => 'Article historique',
            'slug' => 'article-historique-source-test',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->delete(route('veille.sources.destroy', $source))
            ->assertRedirect();

        $this->assertDatabaseHas('source_veille', [
            'id' => $source->id,
            'is_active' => false,
            'status' => 'disabled',
        ]);
        $this->assertDatabaseHas('veille_subjects', [
            'id' => $subject->id,
            'source_id' => $source->id,
        ]);
    }

    public function test_la_suppression_d_un_element_de_veille_est_un_archivage_logique(): void
    {
        $admin = $this->userWithRole('admin');
        $subject = VeilleSubject::create([
            'title' => 'Sujet à préserver',
            'slug' => 'sujet-a-preserver',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->delete(route('veille.sujets.destroy', $subject))
            ->assertRedirect(route('veille.sujets.index'));

        $this->assertDatabaseHas('veille_subjects', [
            'id' => $subject->id,
            'status' => 'archived',
        ]);
    }

    public function test_le_declenchement_de_collecte_est_reserve_aux_responsables(): void
    {
        $journaliste = $this->userWithRole('journaliste');

        $this->actingAs($journaliste)
            ->post(route('veille.sources.trigger'))
            ->assertForbidden();
    }

    public function test_la_liste_des_sources_est_paginee_et_recherchable(): void
    {
        $admin = $this->userWithRole('admin');
        foreach (range(1, 13) as $index) {
            $this->source(['name' => $index === 13 ? 'RFI Afrique' : 'Source '.$index]);
        }

        $page = $this->actingAs($admin)
            ->get(route('veille.sources'))
            ->assertOk();

        $page->assertSee('RFI Afrique');
        $page->assertSee('Page 1 sur 2');

        $filteredPage = $this->actingAs($admin)
            ->get(route('veille.sources', ['q' => 'RFI Afrique']))
            ->assertOk();

        $filteredPage->assertSee('RFI Afrique');
        $filteredPage->assertDontSee('Source 1');
    }
}
