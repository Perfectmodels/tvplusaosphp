<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatutConducteurEnum;
use App\Enums\StatutSondageEnum;
use App\Enums\TypeEmissionEnum;
use App\Models\Conducteur;
use App\Models\Emission;
use App\Models\Intervenant;
use App\Models\Sondage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ConducteurSondageChatUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_vue_unifiee_affiche_le_chat_et_la_creation_du_sondage(): void
    {
        $admin = $this->acteur('admin');
        $conducteur = $this->conducteur($admin);

        $html = $this->actingAs($admin)
            ->get(route('conducteurs.chef_edition', ['conducteurId' => $conducteur->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Discussion interne', $html);
        $this->assertStringContainsString('@tv-chat-open.window', $html);
        $this->assertStringContainsString('Sondage du débat', $html);
        $this->assertStringContainsString('Créer le sondage', $html);
        $this->assertLessThan(
            strpos($html, 'Programmation du conducteur'),
            strpos($html, 'Sondage du débat'),
            'Le sondage doit être placé avec les outils d’interaction, avant le tableau.'
        );
    }

    public function test_un_sondage_ouvert_affiche_le_qr_et_les_resultats(): void
    {
        $admin = $this->acteur('admin');
        $conducteur = $this->conducteur($admin);
        $sondage = Sondage::create([
            'emission_id' => $conducteur->emission_id,
            'question' => 'Qui a gagné le débat ?',
            'statut' => StatutSondageEnum::Ouvert,
        ]);
        foreach (['Alice', 'Bob'] as $nom) {
            $intervenant = Intervenant::create(['prenom' => 'Test', 'nom' => $nom, 'is_active' => true]);
            $sondage->candidats()->create([
                'intervenant_id' => $intervenant->id,
                'nom' => $nom,
                'ordre' => $sondage->candidats()->count() + 1,
            ]);
        }

        $html = $this->actingAs($admin)
            ->get(route('conducteurs.chef_edition', ['conducteurId' => $conducteur->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Qui a gagné le débat ?', $html);
        $this->assertStringContainsString('Page de vote', $html);
        $this->assertStringContainsString(route('sondages.qr', $sondage), $html);
        $this->assertStringContainsString('Clore le scrutin', $html);
        $this->assertStringContainsString('Alice', $html);
        $this->assertStringContainsString('Bob', $html);
    }

    public function test_lespace_sondage_est_visible_meme_sur_une_emission_non_debat(): void
    {
        $admin = $this->acteur('admin');
        $emission = Emission::create([
            'nom' => 'Journal test',
            'type' => TypeEmissionEnum::JT->value,
        ]);
        $conducteur = Conducteur::create([
            'emission_id' => $emission->id,
            'date_edition' => now()->toDateString(),
            'type' => 'emission',
            'owner_id' => $admin->id,
            'status' => StatutConducteurEnum::Brouillon,
        ]);

        $html = $this->actingAs($admin)
            ->get(route('conducteurs.chef_edition', ['conducteurId' => $conducteur->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Sondage du débat', $html);
        $this->assertStringContainsString('uniquement pour les émissions de type', $html);
        $this->assertStringNotContainsString('Créer le sondage', $html);
    }

    public function test_le_presentateur_voit_le_sondage_sans_les_actions_admin(): void
    {
        $admin = $this->acteur('admin');
        $presentateur = $this->acteur('presentateur');
        $conducteur = $this->conducteur($admin);
        Sondage::create([
            'emission_id' => $conducteur->emission_id,
            'question' => 'Sondage en cours',
            'statut' => StatutSondageEnum::Ouvert,
        ]);

        $html = $this->actingAs($presentateur)
            ->get(route('conducteurs.presentateur.show', $conducteur))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Sondage en cours', $html);
        $this->assertStringContainsString('Page de vote', $html);
        $this->assertStringNotContainsString('Clore le scrutin', $html);
        $this->assertStringNotContainsString('Créer le sondage', $html);
    }

    private function acteur(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate($role, 'web'));

        return $user;
    }

    private function conducteur(User $owner): Conducteur
    {
        $emission = Emission::create([
            'nom' => 'Débat avec sondage',
            'type' => TypeEmissionEnum::Debat->value,
        ]);

        return Conducteur::create([
            'emission_id' => $emission->id,
            'date_edition' => now()->toDateString(),
            'type' => 'emission',
            'owner_id' => $owner->id,
            'status' => StatutConducteurEnum::Brouillon,
        ]);
    }
}
