<?php

namespace Tests\Feature;

use App\Enums\StatutEmissionEnum;
use App\Enums\StatutJournalisteEnum;
use App\Enums\TypeEmissionEnum;
use App\Models\Emission;
use App\Models\Intervenant;
use App\Models\Journaliste;
use App\Models\SourceVeille;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FormWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('admin', 'web'));

        return $user;
    }

    private function programme(array $values = []): array
    {
        return array_replace(['nom' => 'Journal programmé', 'type' => TypeEmissionEnum::JT->value,
            'heure_debut' => '20:00', 'heure_fin' => '20:30', 'duree_minutes' => 30,
            'statut' => StatutEmissionEnum::Planifiee->value], $values);
    }

    private function invite(array $values = []): array
    {
        return array_replace(['nom' => 'Nom saisi', 'prenom' => 'Prénom saisi', 'type' => 'invite'], $values);
    }

    private function dom(string $html): \DOMXPath
    {
        $dom = new \DOMDocument;
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);

        return new \DOMXPath($dom);
    }

    public static function horaires(): array
    {
        return ['journée' => ['12:00', '13:00', 60], 'après minuit' => ['23:45', '00:15', 30]];
    }

    #[DataProvider('horaires')]
    public function test_la_duree_est_calculee_depuis_les_horaires_et_le_statut_pilote_le_brouillon(string $debut, string $fin, int $minutes): void
    {
        $this->actingAs($this->admin())->post(route('emissions.store'), $this->programme([
            'heure_debut' => $debut, 'heure_fin' => $fin, 'duree_minutes' => 7, 'is_brouillon' => 1,
        ]))->assertSessionHasNoErrors()->assertRedirect();
        $emission = Emission::firstOrFail();
        $this->assertSame($minutes, $emission->duree_minutes);
        $this->assertSame($minutes, $emission->duration_minutes);
        $this->assertFalse($emission->is_brouillon);
    }

    public static function invalidProgrammes(): array
    {
        return [
            'horaire identique' => [['heure_debut' => '20:00', 'heure_fin' => '20:00'], 'heure_fin'],
            'durée trop longue' => [['heure_debut' => '08:00', 'heure_fin' => '17:00'], 'duree_minutes'],
            'jour inconnu' => [['jours_diffusion' => ['mardi']], 'jours_diffusion.0'],
            'fuseau inconnu' => [['fuseau_horaire' => 'Libreville'], 'fuseau_horaire'],
            'couleur incorrecte' => [['couleur_habillage' => 'rouge'], 'couleur_habillage'],
        ];
    }

    #[DataProvider('invalidProgrammes')]
    public function test_les_incoherences_de_programmation_sont_refusees(array $values, string $field): void
    {
        $this->actingAs($this->admin())->post(route('emissions.store'), $this->programme($values))
            ->assertSessionHasErrors([$field]);
        $this->assertDatabaseCount('emissions', 0);
    }

    public function test_le_formulaire_permet_de_vider_les_listes_et_les_presentateurs(): void
    {
        $emission = Emission::create($this->programme(['jours_diffusion' => ['lun'], 'langues' => ['Français']]));
        $presentateur = Journaliste::factory()->create(['is_presentateur' => true]);
        $emission->ajouterPresentateur($presentateur);
        $this->actingAs($this->admin())->put(route('emissions.update', $emission), $this->programme(['_emission_form' => 1]))
            ->assertSessionHasNoErrors();
        $this->assertSame([], $emission->fresh()->jours_diffusion);
        $this->assertSame([], $emission->fresh()->langues);
        $this->assertSame(0, $emission->presentateurs()->count());
    }

    public function test_une_mise_a_jour_sans_ces_champs_conserve_les_affectations_et_la_desactivation(): void
    {
        $emission = Emission::create($this->programme(['is_active' => false, 'is_brouillon' => false]));
        $presentateur = Journaliste::factory()->create(['is_presentateur' => true]);
        $emission->ajouterPresentateur($presentateur);
        $this->actingAs($this->admin())->put(route('emissions.update', $emission), $this->programme(['nom' => 'Titre corrigé']))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('Titre corrigé', $emission->fresh()->nom);
        $this->assertFalse($emission->fresh()->is_active);
        $this->assertSame([$presentateur->id], $emission->presentateurs()->pluck('journalistes.id')->all());
    }

    public function test_les_presentateurs_ajoutes_sont_retrouves_apres_un_echec_de_validation(): void
    {
        $presentateur = Journaliste::factory()->create(['is_presentateur' => true, 'statut' => StatutJournalisteEnum::Actif]);
        $this->actingAs($this->admin())->from(route('emissions.create'))->post(route('emissions.store'), $this->programme([
            'nom' => '', '_emission_form' => 1, 'presentateurs' => [['journaliste_id' => $presentateur->id]],
        ]))->assertSessionHasErrors('nom');
        $html = $this->get(route('emissions.create'))->assertOk()->getContent();
        $xpath = $this->dom($html);
        $fields = $xpath->query('//*[@data-pres-row]//input[contains(@name,"journaliste_id")]');
        $this->assertSame(1, $fields->length);
        $this->assertSame((string) $presentateur->id, $fields->item(0)->getAttribute('value'));
    }

    public function test_un_nouveau_programme_n_invente_pas_de_creneau_ou_de_jours(): void
    {
        $html = $this->actingAs($this->admin())->get(route('emissions.create'))->assertOk()->getContent();
        $xpath = $this->dom($html);
        $this->assertSame('', $xpath->query('//input[@name="heure_debut"]')->item(0)->getAttribute('value'));
        $this->assertSame('', $xpath->query('//input[@name="heure_fin"]')->item(0)->getAttribute('value'));
        $this->assertSame(0, $xpath->query('//input[@name="jours_diffusion[]"][@checked]')->length);
    }

    public function test_un_intervenant_sans_email_ne_recoit_pas_une_adresse_inventee(): void
    {
        $this->actingAs($this->admin())->post(route('intervenants.store'), $this->invite(['_person_form' => 1, 'is_active' => 0]))->assertSessionHasNoErrors();
        $intervenant = Intervenant::firstOrFail();
        $this->assertNull($intervenant->email);
        $this->assertFalse($intervenant->is_active);
        $this->assertSame([], $intervenant->langues);
    }

    public function test_un_intervenant_peut_etre_desactive_et_ses_champs_optionnels_vides(): void
    {
        $intervenant = Intervenant::factory()->create(['telephone_1' => '+24107000000', 'phone' => '+24107000000', 'organisme' => 'Ancien organisme', 'organization' => 'Ancien organisme', 'domaines' => ['Politique'], 'langues' => ['fr']]);
        $this->actingAs($this->admin())->put(route('intervenants.update', $intervenant), $this->invite([
            '_person_form' => 1, 'is_active' => 0, 'telephone_1' => '', 'organisme' => '',
        ]))->assertSessionHasNoErrors();
        $intervenant->refresh();
        $this->assertFalse($intervenant->is_active);
        $this->assertNull($intervenant->telephone_1);
        $this->assertNull($intervenant->phone);
        $this->assertNull($intervenant->organization);
        $this->assertSame([], $intervenant->domaines);
        $this->assertSame([], $intervenant->langues);
    }

    public function test_les_domaines_restent_lisibles_et_les_champs_de_naissance_sont_absents_des_formulaires(): void
    {
        $domaines = ['Économie & "société"'];
        $intervenant = Intervenant::factory()->create(['domaines' => $domaines, 'date_naissance' => '1980-02-03']);
        $html = $this->actingAs($this->admin())->get(route('intervenants.edit', $intervenant))->assertOk()->getContent();
        $xpath = $this->dom($html);
        $this->assertSame($domaines, json_decode($xpath->query('//input[@name="domaines_json"]')->item(0)->getAttribute('value'), true));
        $this->assertSame(0, $xpath->query('//input[@name="date_naissance"]')->length);
        $this->assertSame(0, $xpath->query('//input[@name="lieu_naissance"]')->length);

        $createHtml = $this->get(route('intervenants.create'))->assertOk()->getContent();
        $createXpath = $this->dom($createHtml);
        $this->assertSame(0, $createXpath->query('//input[@name="date_naissance"]')->length);
        $this->assertSame(0, $createXpath->query('//input[@name="lieu_naissance"]')->length);
    }

    public function test_les_specialites_du_journaliste_sont_enregistrees_sans_dependre_du_javascript(): void
    {
        $this->actingAs($this->admin())->post(route('journalistes.store'), [
            'nom' => 'Nom saisi', 'prenom' => 'Prénom saisi', 'email' => fake()->unique()->safeEmail(),
            'specialites_text' => "Politique\nCulture\nPolitique",
        ])->assertSessionHasNoErrors();
        $this->assertSame(['Politique', 'Culture'], Journaliste::firstOrFail()->specialites);
    }

    public function test_un_message_de_confirmation_n_est_affiche_qu_une_fois(): void
    {
        $intervenant = Intervenant::factory()->create();
        $html = $this->actingAs($this->admin())->followingRedirects()->put(route('intervenants.update', $intervenant), $this->invite())->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'Intervenant mis à jour.'));
    }

    public function test_un_intervenant_ne_peut_pas_avoir_une_date_de_naissance_future(): void
    {
        $this->freezeTime();
        $this->actingAs($this->admin())->post(route('intervenants.store'), $this->invite([
            'date_naissance' => today()->addDay()->toDateString(),
        ]))->assertSessionHasErrors('date_naissance');
        $this->assertDatabaseCount('intervenants', 0);
    }

    public function test_une_liste_videe_n_est_pas_retablie_apres_une_erreur(): void
    {
        $intervenant = Intervenant::factory()->create(['domaines' => ['Politique'], 'langues' => ['fr']]);
        $this->actingAs($this->admin())->from(route('intervenants.edit', $intervenant))->put(route('intervenants.update', $intervenant), $this->invite([
            '_person_form' => 1, 'nom' => '',
        ]))->assertSessionHasErrors('nom');
        $xpath = $this->dom($this->get(route('intervenants.edit', $intervenant))->assertOk()->getContent());
        $this->assertSame('[]', $xpath->query('//input[@name="domaines_json"]')->item(0)->getAttribute('value'));
        $this->assertSame(0, $xpath->query('//input[@name="langues[]"][@checked]')->length);
    }

    public function test_la_reactivation_d_une_source_prepare_un_nouveau_passage(): void
    {
        $this->freezeTime();
        $source = SourceVeille::create(['name' => 'Source suspendue', 'url' => 'https://93.184.215.14/feed/', 'type' => 'rss',
            'is_active' => false, 'last_error' => 'Ancienne erreur', 'consecutive_failures' => 3]);
        $this->actingAs($this->admin())->put(route('veille.sources.update', $source), [
            'name' => $source->name, 'url' => $source->url, 'status' => 'active',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertTrue($source->fresh()->is_active);
        $this->assertNull($source->fresh()->last_error);
        $this->assertSame(0, $source->fresh()->consecutive_failures);
        $this->assertSame(now()->toDateTimeString(), $source->fresh()->collection_requested_at->toDateTimeString());
    }

    public function test_les_jours_et_langues_vides_du_programme_restent_vides_apres_une_erreur(): void
    {
        $emission = Emission::create($this->programme(['jours_diffusion' => ['lun'], 'langues' => ['Français']]));
        $this->actingAs($this->admin())->from(route('emissions.edit', $emission))
            ->put(route('emissions.update', $emission), $this->programme(['nom' => '', '_emission_form' => 1]))
            ->assertSessionHasErrors('nom');
        $xpath = $this->dom($this->get(route('emissions.edit', $emission))->assertOk()->getContent());
        $this->assertSame(0, $xpath->query('//input[@name="jours_diffusion[]"][@checked]')->length);
        $this->assertSame(0, $xpath->query('//input[@name="langues[]"]')->length);
    }

    public function test_la_detection_automatique_reconnait_les_urls_feed(): void
    {
        $this->actingAs($this->admin())->post(route('veille.sources.store'), ['name' => 'Flux renseigné', 'url' => 'https://93.184.215.14/feed/', 'type' => ''])->assertSessionHasNoErrors();
        $this->assertSame('rss', SourceVeille::firstOrFail()->type);
    }

    public function test_la_creation_d_intervenant_ne_redemande_pas_les_informations_personnelles_et_les_doublons(): void
    {
        $html = $this->actingAs($this->admin())->get(route('intervenants.create'))->assertOk()->getContent();
        $xpath = $this->dom($html);

        foreach (['date_naissance', 'lieu_naissance', 'piece_identite', 'poste', 'biographie_longue', 'observations_admin', 'taille_habillement', 'regime_alimentaire', 'sujets_sensibles'] as $field) {
            $this->assertSame(0, $xpath->query('//*[@name="'.$field.'"]')->length, $field);
        }
        foreach (['notes', 'presentation_courte'] as $field) {
            $this->assertSame(1, $xpath->query('//textarea[@name="'.$field.'"]')->length);
        }
        $this->assertSame(1, $xpath->query('//select[@name="type"][@required]/option[@value="invite"][@selected]')->length);
        $this->assertSame('', $xpath->query('//input[@name="pays_residence"]')->item(0)->getAttribute('value'));
        $this->assertStringContainsString('Téléphone / WhatsApp', $html);
    }

    public function test_le_formulaire_simplifie_preserve_les_informations_deja_enregistrees(): void
    {
        $stored = ['date_naissance' => '1980-02-03', 'lieu_naissance' => 'Lieu conservé', 'piece_identite' => 'Ancien document',
            'poste' => 'Poste conservé', 'observations_admin' => 'Observation conservée', 'biographie_longue' => 'Parcours conservé'];
        $intervenant = Intervenant::factory()->create($stored);

        $this->actingAs($this->admin())->put(route('intervenants.update', $intervenant), $this->invite(['_person_form' => 1, 'notes' => 'Consigne actualisée']))
            ->assertSessionHasNoErrors()->assertRedirect();
        $intervenant->refresh();
        $this->assertSame('1980-02-03', $intervenant->date_naissance->format('Y-m-d'));
        foreach (array_diff_key($stored, ['date_naissance' => true]) as $field => $value) {
            $this->assertSame($value, $intervenant->{$field});
        }
        $this->assertSame('Consigne actualisée', $intervenant->notes);
    }

    public function test_un_ancien_poste_reste_accessible_dans_le_champ_fonction_unique(): void
    {
        $intervenant = Intervenant::factory()->create(['fonction' => null, 'poste' => 'Poste existant']);
        $html = $this->actingAs($this->admin())->get(route('intervenants.edit', $intervenant))->assertOk()->getContent();
        $xpath = $this->dom($html);

        $this->assertSame('Poste existant', $xpath->query('//input[@name="fonction"]')->item(0)->getAttribute('value'));
        $this->assertSame(0, $xpath->query('//input[@name="poste"]')->length);
    }
}
