<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoleSequenceIntervenantEnum;
use App\Enums\StatutEmissionEnum;
use App\Enums\StatutSondageEnum;
use App\Enums\TypeEmissionEnum;
use App\Enums\TypeSequenceEnum;
use App\Models\Conducteur;
use App\Models\Emission;
use App\Models\Intervenant;
use App\Models\Sequence;
use App\Models\Sondage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SondagePublicTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_page_publique_affiche_les_debatteurs_et_les_resultats(): void
    {
        $sondage = $this->sondageOuvert();

        $this->get(route('sondage.show', $sondage->token))
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('Qui a gagné le débat ?')
            ->assertSee('Alice Ouattara')
            ->assertSee('Bob Koffi');
    }

    public function test_un_telespectateur_peut_voter_une_seule_fois(): void
    {
        $sondage = $this->sondageOuvert();
        $candidat = $sondage->candidats()->firstOrFail();

        $this->post(route('sondage.vote', $sondage->token), ['candidat_id' => $candidat->id])
            ->assertRedirect(route('sondage.show', $sondage->token));

        $this->assertSame(1, $sondage->votes()->count());

        $voterId = $sondage->votes()->value('voter_id');
        $this->withCookie('sondage_voter_'.$sondage->id, $voterId)
            ->from(route('sondage.show', $sondage->token))
            ->post(route('sondage.vote', $sondage->token), ['candidat_id' => $candidat->id])
            ->assertRedirect(route('sondage.show', $sondage->token));

        $this->assertSame(1, $sondage->votes()->count());
    }

    public function test_les_resultats_sont_agriges_en_direct(): void
    {
        $sondage = $this->sondageOuvert();
        $candidats = $sondage->candidats()->get();

        $sondage->votes()->create([
            'candidat_id' => $candidats[0]->id,
            'voter_id' => fake()->uuid(),
            'voter_hash' => fake()->sha256(),
        ]);
        $sondage->votes()->create([
            'candidat_id' => $candidats[1]->id,
            'voter_id' => fake()->uuid(),
            'voter_hash' => fake()->sha256(),
        ]);
        $sondage->votes()->create([
            'candidat_id' => $candidats[1]->id,
            'voter_id' => fake()->uuid(),
            'voter_hash' => fake()->sha256(),
        ]);

        $response = $this->getJson(route('sondage.resultats', $sondage->token))
            ->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonPath('candidats.0.votes', 1)
            ->assertJsonPath('candidats.1.votes', 2);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_un_sondage_clos_refuse_les_votes(): void
    {
        $sondage = $this->sondageOuvert();
        $sondage->forceFill(['statut' => StatutSondageEnum::Clos, 'clos_at' => now()])->save();

        $this->post(route('sondage.vote', $sondage->token), [
            'candidat_id' => $sondage->candidats()->value('id'),
        ])->assertForbidden();

        $this->assertSame(0, $sondage->votes()->count());
    }

    public function test_un_candidat_etranger_au_sondage_est_rejete(): void
    {
        $sondage = $this->sondageOuvert();
        $autreSondage = $this->sondageOuvert('Autre question du débat ?');
        $candidatEtranger = $autreSondage->candidats()->value('id');

        $this->post(route('sondage.vote', $sondage->token), ['candidat_id' => $candidatEtranger])
            ->assertNotFound();

        $this->assertSame(0, $sondage->votes()->count());
    }

    public function test_un_sondage_en_brouillon_n_est_pas_public(): void
    {
        $sondage = $this->sondageOuvert();
        $sondage->forceFill(['statut' => StatutSondageEnum::Brouillon])->save();

        $this->get(route('sondage.show', $sondage->token))->assertNotFound();
    }

    private function sondageOuvert(string $question = 'Qui a gagné le débat ?'): Sondage
    {
        $emission = Emission::create([
            'nom' => 'Le débat test',
            'type' => TypeEmissionEnum::Debat->value,
            'statut' => StatutEmissionEnum::En_cours->value,
        ]);
        $conducteur = Conducteur::create([
            'emission_id' => $emission->id,
            'date_edition' => now()->toDateString(),
            'type' => 'emission',
        ]);
        $sequence = Sequence::create([
            'emission_id' => $emission->id,
            'conducteur_id' => $conducteur->id,
            'title' => 'Débat',
            'type' => TypeSequenceEnum::Debat->value,
            'position' => 1,
        ]);

        foreach ([['Alice', 'Ouattara'], ['Bob', 'Koffi']] as $index => [$prenom, $nom]) {
            $intervenant = Intervenant::create([
                'prenom' => $prenom,
                'nom' => $nom,
                'is_active' => true,
            ]);
            $sequence->intervenants()->attach($intervenant->id, [
                'role_dans_sequence' => RoleSequenceIntervenantEnum::Debatteur->value,
                'ordre_passage' => $index + 1,
            ]);
        }

        $sondage = Sondage::create([
            'emission_id' => $emission->id,
            'sequence_id' => $sequence->id,
            'question' => $question,
            'statut' => StatutSondageEnum::Ouvert,
            'ouvert_at' => now(),
        ]);

        foreach ($sequence->intervenants as $index => $intervenant) {
            $sondage->candidats()->create([
                'intervenant_id' => $intervenant->id,
                'nom' => $intervenant->nomComplet,
                'ordre' => $index + 1,
            ]);
        }

        return $sondage;
    }
}
