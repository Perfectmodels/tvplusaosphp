<?php

namespace Tests\Feature;

use App\Enums\StatutEmissionEnum;
use App\Enums\TypeEmissionEnum;
use App\Models\Emission;
use App\Models\Intervenant;
use App\Models\Journaliste;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FormSelectorsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($user);

        return $user;
    }

    private function dom(string $html): \DOMXPath
    {
        $dom = new \DOMDocument;
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);

        return new \DOMXPath($dom);
    }

    public function test_les_suggestions_utilisent_les_fiches_existantes_et_restent_des_champs_libres(): void
    {
        $this->admin();
        Intervenant::factory()->create(['fonction' => 'Chercheuse spécialisée', 'organisme' => 'Centre & recherche',
            'poste' => 'Directrice scientifique', 'ville_residence' => 'Ville saisie', 'domaines' => ['Climat local']]);
        $xpath = $this->dom($this->get(route('intervenants.create'))->assertOk()->getContent());
        foreach (['fonction' => 'Chercheuse spécialisée', 'organisme' => 'Centre & recherche', 'ville_residence' => 'Ville saisie', 'pays_residence' => 'Gabon'] as $field => $value) {
            $input = $xpath->query('//input[@name="'.$field.'"]')->item(0);
            $this->assertNotNull($input);
            $options = $xpath->query('//datalist[@id="'.$input->getAttribute('list').'"]/option');
            $this->assertContains($value, array_map(fn ($option) => $option->getAttribute('value'), iterator_to_array($options)));
        }
        $fonction = $xpath->query('//input[@name="fonction"]')->item(0);
        $this->assertSame(1, $xpath->query('//datalist[@id="'.$fonction->getAttribute('list').'"]/option[@value="Directrice scientifique"]')->length);
        $this->assertSame(0, $xpath->query('//input[@name="poste"]')->length);
        $this->assertSame(1, $xpath->query('//select[contains(@id,"domain-select")]/option[@value="Climat local"]')->length);
        $this->post(route('intervenants.store'), ['nom' => 'Fiche libre', 'prenom' => 'Choix', 'type' => 'invite',
            'fonction' => 'Fonction nouvelle', 'organisme' => 'Nouvel organisme', 'ville_residence' => 'Autre ville'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('intervenants', ['nom' => 'Fiche libre', 'fonction' => 'Fonction nouvelle', 'organisme' => 'Nouvel organisme']);
    }

    public function test_les_specialites_existantes_sont_proposees_sans_interpreter_du_html(): void
    {
        $this->admin();
        $specialite = 'Climat <img src=x onerror=alert(1)>';
        Journaliste::factory()->create(['specialites' => [$specialite]]);
        $xpath = $this->dom($this->get(route('journalistes.create'))->assertOk()->getContent());
        $select = $xpath->query('//select[@data-add-to-lines="specialites-textarea"]')->item(0);
        $this->assertNotNull($select);
        $this->assertStringContainsString($specialite, $select->textContent);
        $this->assertSame(0, $xpath->query('//select[@data-add-to-lines]//img')->length);
    }

    public function test_le_choix_du_chef_affiche_les_comptes_eligibles_et_conserve_le_responsable_inactif(): void
    {
        $admin = $this->admin();
        $chef = User::factory()->create(['is_active' => true]);
        $chef->assignRole(Role::findOrCreate('chef_edition', 'web'));
        $inactif = User::factory()->create(['is_active' => false]);
        $inactif->assignRole('chef_edition');
        $xpath = $this->dom($this->get(route('emissions.create'))->assertOk()->getContent());
        $this->assertSame(1, $xpath->query('//select[@name="chef_edition_id"]/option[@value="'.$chef->id.'"]')->length);
        foreach ([$admin, $inactif] as $excluded) {
            $this->assertSame(0, $xpath->query('//select[@name="chef_edition_id"]/option[@value="'.$excluded->id.'"]')->length);
        }
        $this->post(route('emissions.store'), ['nom' => 'Programme avec responsable', 'type' => TypeEmissionEnum::JT->value,
            'heure_debut' => '20:00', 'heure_fin' => '20:30', 'statut' => StatutEmissionEnum::Planifiee->value,
            'chef_edition_id' => $chef->id, 'langues' => ['Français', 'Anglais']])->assertSessionHasNoErrors();
        $emission = Emission::firstOrFail();
        $this->assertSame($chef->id, $emission->chef_edition_id);
        $this->assertSame(['Français', 'Anglais'], $emission->langues);
        $emission->update(['chef_edition_id' => $inactif->id]);
        $xpath = $this->dom($this->get(route('emissions.edit', $emission))->assertOk()->getContent());
        $this->assertSame(1, $xpath->query('//select[@name="chef_edition_id"]/option[@value="'.$inactif->id.'"][@selected]')->length);
        $this->assertSame(1, $xpath->query('//select[@data-langue-select]/option[@value="Anglais"]')->length);
    }

    public function test_la_source_presente_des_pays_lisibles_et_enregistre_le_code(): void
    {
        $this->admin();
        $xpath = $this->dom($this->get(route('veille.sources'))->assertOk()->getContent());
        $this->assertSame('Gabon', trim($xpath->query('//select[@name="country"]/option[@value="GA"]')->item(0)->textContent));
        $this->assertSame(1, $xpath->query('//input[@name="language"][@list="source-language-options"]')->length);
        $this->post(route('veille.sources.store'), ['name' => 'Source choisie', 'url' => 'https://93.184.215.14/feed/', 'country' => 'GA', 'language' => 'fr'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('source_veille', ['name' => 'Source choisie', 'country' => 'GA', 'language' => 'fr']);
    }
}
