<?php

namespace Tests\Feature;

use App\Models\DigitalPublication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DigitalPublicationTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $role = 'community_manager'): User
    {
        $user = User::factory()->create(['role' => $role]);
        $user->assignRole($role);

        return $user;
    }

    private function publication(User $owner, array $attributes = []): DigitalPublication
    {
        return DigitalPublication::create(array_replace(['title' => 'Sujet numérique', 'content' => 'Texte à préparer', 'platform' => 'facebook', 'status' => 'draft', 'created_by' => $owner->id], $attributes));
    }

    public function test_parcours_complet_de_preparation_validation_planification_et_confirmation(): void
    {
        $cm = $this->actor();
        $chef = $this->actor('chef_redaction');
        $this->actingAs($cm)->post(route('digital.store'), ['title' => 'Publication réelle', 'content' => 'Contenu rédigé', 'platform' => 'site', 'status' => 'published', 'created_by' => $chef->id])->assertRedirect();
        $p = DigitalPublication::sole();
        $this->assertSame('draft', $p->status);
        $this->assertSame($cm->id, $p->created_by);
        $this->post(route('digital.transition', $p), ['action' => 'submit'])->assertRedirect();
        $this->assertSame('awaiting_validation', $p->fresh()->status);
        $this->actingAs($chef)->post(route('digital.transition', $p), ['action' => 'approve'])->assertRedirect();
        $this->assertSame($chef->id, $p->fresh()->approved_by);
        $this->actingAs($cm)->post(route('digital.transition', $p), ['action' => 'schedule', 'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s')])->assertRedirect();
        $this->assertSame('scheduled', $p->fresh()->status);
        $this->post(route('digital.transition', $p), ['action' => 'publish', 'publication_url' => 'https://example.org/publication'])->assertRedirect();
        $this->assertSame('published', $p->fresh()->status);
        $this->assertNotNull($p->fresh()->published_at);
        $this->get(route('digital.show', $p))->assertOk()->assertSee('https://example.org/publication')->assertDontSee('Demander la validation');
    }

    public function test_un_cm_ne_peut_pas_valider_son_propre_contenu(): void
    {
        $cm = $this->actor();
        $p = $this->publication($cm, ['status' => 'awaiting_validation']);
        $this->actingAs($cm)->post(route('digital.transition', $p), ['action' => 'approve'])->assertForbidden();
        $this->assertSame('awaiting_validation', $p->fresh()->status);
    }

    public function test_un_cm_ne_peut_pas_planifier_ou_publier_un_brouillon(): void
    {
        $cm = $this->actor();
        $p = $this->publication($cm);
        $this->actingAs($cm)->postJson(route('digital.transition', $p), ['action' => 'schedule', 'scheduled_at' => now()->addDay()->toIso8601String()])->assertUnprocessable();
        $this->postJson(route('digital.transition', $p), ['action' => 'publish', 'publication_url' => 'https://example.org/article'])->assertUnprocessable();
        $this->assertSame('draft', $p->fresh()->status);
    }

    public function test_les_corrections_retirent_la_validation_et_permettent_une_nouvelle_soumission(): void
    {
        $cm = $this->actor();
        $chef = $this->actor('chef_edition');
        $p = $this->publication($cm, ['status' => 'scheduled', 'approved_by' => $chef->id, 'approved_at' => now(), 'scheduled_at' => now()->addDay()]);
        $this->actingAs($chef)->post(route('digital.transition', $p), ['action' => 'return', 'feedback' => 'Préciser la source'])->assertRedirect();
        $this->assertSame('changes_requested', $p->fresh()->status);
        $this->assertNull($p->fresh()->scheduled_at);
        $this->assertNull($p->fresh()->approved_by);
        $this->actingAs($cm)->patch(route('digital.update', $p), ['title' => $p->title, 'content' => 'Version corrigée avec sa source', 'platform' => 'site'])->assertRedirect();
        $this->assertSame('draft', $p->fresh()->status);
        $this->post(route('digital.transition', $p), ['action' => 'submit'])->assertRedirect();
        $this->assertSame('awaiting_validation', $p->fresh()->status);
    }

    public function test_la_date_future_et_le_lien_de_confirmation_sont_obligatoires(): void
    {
        $cm = $this->actor();
        $p = $this->publication($cm, ['status' => 'approved']);
        $this->actingAs($cm)->postJson(route('digital.transition', $p), ['action' => 'schedule', 'scheduled_at' => now()->subDay()->toIso8601String()])->assertUnprocessable()->assertJsonValidationErrors('scheduled_at');
        $this->postJson(route('digital.transition', $p), ['action' => 'publish', 'publication_url' => 'javascript:alert(1)'])->assertUnprocessable()->assertJsonValidationErrors('publication_url');
        $this->assertSame('approved', $p->fresh()->status);
    }

    public function test_un_autre_cm_ne_peut_ni_lire_ni_modifier_un_contenu(): void
    {
        $owner = $this->actor();
        $other = $this->actor();
        $p = $this->publication($owner);
        $this->actingAs($other)->get(route('digital.show', $p))->assertForbidden();
        $this->get(route('digital.index'))->assertOk()->assertDontSee($p->title);
        $this->patch(route('digital.update', $p), ['title' => 'Intrusion', 'content' => 'Texte', 'platform' => 'site'])->assertForbidden();
    }

    public function test_un_contenu_valide_ne_peut_pas_etre_modifie_sans_retour_en_correction(): void
    {
        $cm = $this->actor();
        $p = $this->publication($cm, ['status' => 'approved']);
        $this->actingAs($cm)->patch(route('digital.update', $p), ['title' => 'Version non validée', 'content' => 'Texte', 'platform' => 'site'])->assertForbidden();
    }

    public function test_les_textes_sont_echappes_et_les_formulaires_ont_une_protection_csrf(): void
    {
        $cm = $this->actor();
        $p = $this->publication($cm, ['title' => '<script>alert(1)</script>', 'content' => '<img src=x onerror=alert(2)>']);
        $this->actingAs($cm)->get(route('digital.show', $p))->assertOk()->assertSee($p->title)->assertDontSee($p->title, false)->assertDontSee($p->content, false)->assertSee('name="_token"', false);
    }

    #[DataProvider('refusedRoles')]
    public function test_les_autres_metiers_n_accedent_pas_aux_publications(string $role): void
    {
        $this->actingAs($this->actor($role))->get(route('digital.index'))->assertForbidden();
    }

    public static function refusedRoles(): array
    {
        return [['journaliste'], ['correspondant'], ['realisateur'], ['presentateur'], ['monteur'], ['documentaliste'], ['responsable_administratif'], ['lecture']];
    }
}
