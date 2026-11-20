<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Conducteur;
use App\Models\Emission;
use App\Models\Intervenant;
use App\Models\Sequence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Reproduction du signalement « PATCH /conducteurs/chef-edition/9
 * net::ERR_TOO_MANY_REDIRECTS » : on rejoue la chaîne de redirections
 * comme le navigateur (Referer = page du conducteur) pour vérifier qu'elle
 * se termine bien sur un 200.
 */
class SequenceUpdateRedirectChainTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        Role::findOrCreate('admin');

        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function conducteur(): Conducteur
    {
        $emission = Emission::create([
            'nom' => 'JT Redirect Test',
            'title' => 'JT Redirect Test',
            'slug' => 'jt-redirect-test-'.uniqid(),
            'is_active' => true,
        ]);

        return Conducteur::create([
            'emission_id' => $emission->id,
            'date_edition' => now()->toDateString(),
            'type' => 'emission',
        ]);
    }

    public function test_la_mise_a_jour_dun_sujet_ne_boucle_pas_en_redirection(): void
    {
        $conducteur = $this->conducteur();
        $intervenant = Intervenant::create(['nom' => 'Dupont', 'prenom' => 'Awa', 'fonction' => 'Expert', 'is_active' => true]);
        $sequence = Sequence::create([
            'conducteur_id' => $conducteur->id,
            'title' => 'Débat',
            'type' => 'SUJET',
            'position' => 1,
        ]);

        $pageUrl = '/conducteurs/chef-edition/'.$conducteur->id;

        $this->actingAs($this->user())->get($pageUrl)->assertOk();

        $response = $this->withHeaders(['Referer' => 'http://localhost'.$pageUrl])
            ->from($pageUrl)
            ->patch(route('conducteurs.sequences.update', $sequence), [
                'title' => 'Débat',
                'type' => 'SUJET',
                'intervenant_ids' => json_encode([$intervenant->id]),
            ]);

        $chain = [];
        while ($response->isRedirect() && count($chain) < 25) {
            $location = (string) $response->headers->get('Location');
            $chain[] = $response->getStatusCode().' → '.$location;
            $response = $this->get($location);
        }

        // Un seul retour vers la page du conducteur est normal (back())
        $this->assertCount(
            1,
            $chain,
            'Chaîne de redirections anormale ('.count($chain)." hops) :\n".implode("\n", $chain)
        );
        $response->assertOk();
    }

    /**
     * Le PATCH envoyé par la modale ne doit viser que la route sequences.update,
     * jamais la route conducteur (chef_edition.update).
     */
    public function test_la_page_du_chef_edition_pointe_la_modale_vers_sequences_update(): void
    {
        $conducteur = $this->conducteur();

        $html = $this->actingAs($this->user())
            ->get('/conducteurs/chef-edition/'.$conducteur->id)
            ->assertOk()
            ->getContent();

        // @json échappe les slashes : « \/ »
        $this->assertStringContainsString('conducteurs\/sequences\/_SEQ_', $html);
        $this->assertStringNotContainsString('conducteurs\/chef-edition\/_SEQ_', $html);
    }

    /**
     * Sans id, index() redirige vers chef-edition/{id} : si route() perdait le
     * paramètre, la redirection ciblerait la même URL → boucle infinie.
     */
    public function test_la_page_sans_id_se_redirige_vers_la_page_avec_id(): void
    {
        $conducteur = $this->conducteur();

        $response = $this->actingAs($this->user())->get('/conducteurs/chef-edition');

        $response->assertRedirect('/conducteurs/chef-edition/'.$conducteur->id);

        $chain = [];
        $hops = 0;
        while ($response->isRedirect() && $hops < 25) {
            $location = (string) $response->headers->get('Location');
            $chain[] = $location;
            $response = $this->get($location);
            $hops++;
        }

        $this->assertCount(1, $chain, 'Boucle de redirection : '.implode(' → ', $chain));
        $response->assertOk();
    }

    /**
     * Le fetch de la modale envoie `Accept: application/json` : la réponse
     * doit être un 200 JSON SANS aucune redirection (le navigateur ne suit
     * donc jamais de Location → ERR_TOO_MANY_REDIRECTS impossible).
     */
    public function test_le_patch_xhr_renvoie_du_json_sans_redirection(): void
    {
        $conducteur = $this->conducteur();
        $intervenant = Intervenant::create(['nom' => 'Martin', 'prenom' => 'Paul', 'fonction' => 'Syndicat', 'is_active' => true]);
        $sequence = Sequence::create([
            'conducteur_id' => $conducteur->id,
            'title' => 'Débat',
            'type' => 'SUJET',
            'position' => 1,
        ]);

        $this->actingAs($this->user());

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->from('/conducteurs/chef-edition/'.$conducteur->id)
            ->patch(route('conducteurs.sequences.update', $sequence), [
                'title' => 'Débat modifié',
                'type' => 'SUJET',
                'intervenant_ids' => json_encode([$intervenant->id]),
            ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertHeaderMissing('Location');

        $this->assertSame('Débat modifié', $sequence->fresh()->title);
        $this->assertSame([$intervenant->id], $sequence->fresh()->intervenants->pluck('id')->all());
    }

    /**
     * Validation échouée en XHR → 422 JSON (jamais un back() 302 que le
     * fetch suivrait).
     */
    public function test_le_patch_xhr_invalide_renvoie_422_json(): void
    {
        $conducteur = $this->conducteur();
        $sequence = Sequence::create([
            'conducteur_id' => $conducteur->id,
            'title' => 'Débat',
            'type' => 'SUJET',
            'position' => 1,
        ]);

        $this->actingAs($this->user());

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->from('/conducteurs/chef-edition/'.$conducteur->id)
            ->patch(route('conducteurs.sequences.update', $sequence), [
                'duration_minutes' => -5,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['duration_minutes'])
            ->assertHeaderMissing('Location');
    }

    /**
     * Même garantie sur la création (POST) et la suppression (DELETE).
     */
    public function test_le_store_et_le_delete_xhr_renvoient_du_json_sans_redirection(): void
    {
        $conducteur = $this->conducteur();

        $this->actingAs($this->user());

        $create = $this->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
            ->post(route('conducteurs.sequences.store'), [
                'conducteur_id' => $conducteur->id,
                'title' => 'Nouveau sujet',
                'type' => 'SUJET',
                'intervenant_ids' => '[]',
            ]);

        $create->assertStatus(201)
            ->assertJsonPath('ok', true)
            ->assertHeaderMissing('Location');

        $sequence = Sequence::where('title', 'Nouveau sujet')->firstOrFail();

        $delete = $this->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
            ->delete(route('conducteurs.sequences.destroy', $sequence));

        $delete->assertOk()
            ->assertJsonPath('ok', true)
            ->assertHeaderMissing('Location');

        $this->assertNull(Sequence::find($sequence->id));
    }
}
