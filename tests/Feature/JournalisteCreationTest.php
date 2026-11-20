<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Journaliste;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * RÉGRESSION — Création de journaliste :
 *
 *  1. Le matricule était REQUIS mais jamais généré alors que le formulaire
 *     promet « Généré automatiquement » → échec `validation.required` systématique.
 *  2. Aucun fichier lang/fr/ → les messages de validation s'affichaient en
 *     CLÉ BRUTE ("validation.required") au lieu d'un message lisible.
 *  3. flash-status était inclus deux fois (layouts/app + partial du formulaire)
 *     → chaque erreur s'affichait DEUX fois.
 *
 * Ces trois défauts se cumulaient exactement sur la page de création.
 */
class JournalisteCreationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('admin', 'web');

        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'nom' => 'Ndiaye',
            'prenom' => 'Awa',
            'email' => fake()->unique()->safeEmail(),
        ], $overrides);
    }

    private function poster(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->admin())
            ->from(route('journalistes.create'))
            ->post(route('journalistes.store'), $this->payload($overrides));
    }

    // =====================================================================
    //  1. GÉNÉRATION AUTOMATIQUE DU MATRICULE
    // =====================================================================

    public function test_le_matricule_est_genere_automatiquement_quand_le_champ_est_vide(): void
    {
        $this->poster(['matricule' => ''])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $journaliste = Journaliste::firstOrFail();

        $this->assertMatchesRegularExpression('/^J-\d{4}-\d{4}$/', (string) $journaliste->matricule);
        $this->assertSame(date('Y'), substr((string) $journaliste->matricule, 2, 4));
        $this->assertDatabaseHas('journalistes', [
            'id' => $journaliste->id,
            'matricule' => $journaliste->matricule,
        ]);
    }

    public function test_le_matricule_est_genere_meme_si_le_champ_est_totallement_omis(): void
    {
        // champ totalement omis (pas même une chaîne vide)
        $this->poster()->assertRedirect()->assertSessionHasNoErrors();

        $this->assertMatchesRegularExpression(
            '/^J-\d{4}-\d{4}$/',
            (string) Journaliste::firstOrFail()->matricule
        );
    }

    public function test_deux_creations_successives_degenerent_des_matricules_distincts(): void
    {
        $this->poster(['matricule' => '', 'nom' => 'Ndiaye', 'prenom' => 'Awa']);
        $this->poster(['matricule' => '', 'nom' => 'Mabicka', 'prenom' => 'Sylvie']);

        $this->assertSame(2, Journaliste::count());

        $matricules = Journaliste::pluck('matricule')->all();
        $this->assertCount(
            2,
            array_unique($matricules),
            'Deux journalistes ne peuvent pas partager le même matricule : '.implode(', ', $matricules)
        );

        foreach ($matricules as $m) {
            $this->assertMatchesRegularExpression('/^J-\d{4}-\d{4}$/', (string) $m);
        }
    }

    public function test_le_matricule_saisi_manuellement_est_conserve(): void
    {
        $this->poster(['matricule' => 'J-2026-9999'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('journalistes', ['matricule' => 'J-2026-9999']);
    }

    public function test_le_matricule_genere_suit_le_max_de_lannee_en_cours(): void
    {
        Journaliste::create([
            'matricule' => 'J-'.date('Y').'-0007',
            'nom' => 'Existant',
            'prenom' => 'Max',
        ]);

        $this->poster(['matricule' => '']);

        $this->assertDatabaseHas('journalistes', [
            'matricule' => 'J-'.date('Y').'-0008',
        ]);
    }

    public function test_a_letedition_un_matricule_vide_conserve_la_valeur_existante(): void
    {
        $journaliste = Journaliste::create([
            'matricule' => 'J-2026-0420',
            'nom' => 'Diallo',
            'prenom' => 'Karim',
        ]);

        $this->actingAs($this->admin())
            ->put(route('journalistes.update', $journaliste), $this->payload([
                'matricule' => '',
                'prenom' => 'Karim-Moussa',
            ]))
            ->assertRedirect(route('journalistes.show', $journaliste));

        // L'identifiant ne doit PAS changer à l'édition.
        $this->assertDatabaseHas('journalistes', [
            'id' => $journaliste->id,
            'matricule' => 'J-2026-0420',
            'prenom' => 'Karim-Moussa',
        ]);
    }

    // =====================================================================
    //  2. MESSAGES D'ERREUR LISIBLES (plus de clés brutes)
    // =====================================================================

    public function test_une_champ_obligatoire_manquant_affiche_un_message_francais_lisible(): void
    {
        $response = $this->poster(['nom' => '']);

        $response->assertRedirect(route('journalistes.create'));
        $response->assertSessionHasErrors('nom');

        $html = (string) $response->getSession()->get('errors')->first('nom');

        $this->assertSame('Le champ nom est obligatoire.', $html);
        $this->assertStringNotContainsString('validation.required', $html);
    }

    public function test_aucune_cle_brute_de_validation_napparait_dans_la_page_rendue(): void
    {
        $response = $this->poster(['nom' => '', 'prenom' => '']);

        $response->assertSessionHasErrors(['nom', 'prenom']);

        $rendered = $this->actingAs($this->admin())
            ->get(route('journalistes.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('validation.required', $rendered);
        $this->assertStringContainsString('Le champ nom est obligatoire.', $rendered);
        $this->assertStringContainsString('Le champ prénom est obligatoire.', $rendered);
    }

    public function test_une_email_invalide_affiche_un_message_francais(): void
    {
        $response = $this->poster(['email' => 'pas-un-email']);

        $response->assertSessionHasErrors('email');

        $message = (string) $response->getSession()->get('errors')->first('email');

        $this->assertSame('Le champ adresse e-mail doit être une adresse e-mail valide.', $message);
        $this->assertStringNotContainsString('validation.email', $message);
    }

    // =====================================================================
    //  3. AFFICHAGE UNIQUE (plus de doublon)
    // =====================================================================

    public function test_un_message_derreur_napparaît_quune_seule_fois_dans_la_page(): void
    {
        $this->poster(['nom' => '']);

        $rendered = $this->actingAs($this->admin())
            ->get(route('journalistes.create'))
            ->assertOk()
            ->getContent();

        $this->assertSame(
            1,
            substr_count($rendered, 'Le champ nom est obligatoire.'),
            'Le message doit apparaître exactement une fois (inclusion flash-status en double).'
        );
    }

    public function test_une_erreur_nest_pas_affichee_deux_fois_sur_la_liste_des_journalistes(): void
    {
        $this->poster(['nom' => '']);

        $rendered = $this->actingAs($this->admin())
            ->get(route('journalistes.index'))
            ->assertOk()
            ->getContent();

        $this->assertSame(
            1,
            substr_count($rendered, 'Le champ nom est obligatoire.'),
            'La liste ne doit pas dupliquer le message (flash-status déjà géré par le layout).'
        );
    }

    // =====================================================================
    //  4. NON-RÉGRESSION
    // =====================================================================

    public function test_une_creation_valide_reussit_sans_erreur(): void
    {
        $this->poster(['matricule' => 'J-2026-0001'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Journaliste::count());
    }

    public function test_le_formulaire_de_creation_saffiche_sans_doublon_de_flash_status(): void
    {
        $rendered = $this->actingAs($this->admin())
            ->get(route('journalistes.create'))
            ->assertOk()
            ->getContent();

        // Le partial ne doit plus être inclus localement : seul le layout l'affiche.
        $this->assertStringNotContainsString(
            "@include('_partials.flash-status')",
            $rendered,
            'L\'inclusion locale de flash-status n\'a pas été retirée.'
        );
    }
}
