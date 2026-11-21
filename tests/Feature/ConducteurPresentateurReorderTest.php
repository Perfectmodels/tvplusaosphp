<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatutConducteurEnum;
use App\Enums\TypeEmissionEnum;
use App\Models\Conducteur;
use App\Models\Emission;
use App\Models\Intervenant;
use App\Models\Journaliste;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ConducteurPresentateurReorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_presentateur_peut_etre_change_apres_creation(): void
    {
        $admin = $this->acteur('admin');
        $conducteur = $this->conducteur($admin);
        $nouveau = Intervenant::create([
            'prenom' => 'Nouveau',
            'nom' => 'Presentateur',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)
            ->post(route('conducteurs.presentateur.update', $conducteur), [
                'presentateur_id' => (string) $nouveau->id,
            ])
            ->assertRedirect();

        $this->assertStringContainsString('maj=', (string) $response->headers->get('Location'));

        $this->assertSame($nouveau->id, (int) $conducteur->fresh()->presentateur_id);
    }

    public function test_un_journaliste_peut_etre_choisi_comme_presentateur(): void
    {
        $admin = $this->acteur('admin');
        $conducteur = $this->conducteur($admin);
        $journaliste = Journaliste::create([
            'prenom' => 'Jean',
            'nom' => 'Presentateur',
            'fonction' => 'journaliste',
            'email' => 'jean.presentateur@example.test',
        ]);

        $this->actingAs($admin)
            ->post(route('conducteurs.presentateur.update', $conducteur), [
                'presentateur_id' => 'j:'.$journaliste->id,
            ])
            ->assertRedirect();

        $this->assertNotNull($conducteur->fresh()->presentateur_id);
    }

    public function test_un_journaliste_ne_peut_pas_changer_le_presentateur(): void
    {
        $conducteur = $this->conducteur($this->acteur('admin'));

        $this->actingAs($this->acteur('journaliste'))
            ->post(route('conducteurs.presentateur.update', $conducteur), [
                'presentateur_id' => '',
            ])
            ->assertForbidden();
    }

    public function test_la_vue_chef_edition_propose_le_changement_et_les_fleches(): void
    {
        $admin = $this->acteur('admin');
        $conducteur = $this->conducteur($admin);
        $conducteur->sequences()->create(['title' => 'Séquence test', 'type' => 'SUJET', 'position' => 1]);

        $html = $this->actingAs($admin)
            ->get(route('conducteurs.chef_edition', ['conducteurId' => $conducteur->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('presentateur_id', $html);
        $this->assertStringContainsString('fa-chevron-up', $html);
        $this->assertStringContainsString('fa-chevron-down', $html);
        $this->assertStringContainsString('Antenne & notes présentateur', $html);
        $this->assertStringContainsString('chefAntennePanel()', $html);
        $this->assertStringContainsString("x-data='{ presentateurId:", $html);
        $this->assertStringContainsString("x-init='initialize({", $html);
        $this->assertLessThan(
            strpos($html, 'Programmation du conducteur'),
            strpos($html, 'Antenne & notes présentateur'),
            'Le panneau notes/chrono doit apparaître avant le tableau.'
        );
        $this->assertStringContainsString('QR code public', $html);
        $this->assertStringContainsString('max-w-4xl', $html);
        $this->assertStringNotContainsString('max-w-lg', $html);
        $this->assertStringNotContainsString('flex flex-col gap-6 h-full', $html);
        $this->assertStringNotContainsString('data-bulk-all', $html);
        $this->assertStringNotContainsString('data-bulk-bar', $html);
        $this->assertStringNotContainsString('Tout sélectionner sur cette page', $html);
        $this->assertStringContainsString(
            'window.chefAntennePanel',
            (string) file_get_contents(resource_path('js/chef-antenne-panel.js')),
            'Le composant Alpine du chrono doit être enregistré globalement.'
        );
        $this->assertStringContainsString('Conducteur ·', $html);
        $this->assertStringContainsString('prompter-sequence', $html);
        $this->assertStringContainsString('x-text="seq.notes_preview"', $html);
        $this->assertStringContainsString('tv-sequence-active', $html);
        $this->assertStringContainsString('tv-sequence-live', $html);
        $this->assertStringContainsString('@tv-sequence-active.window', $html);
        $this->assertStringContainsString('tv-bulle-sequence', $html);
        $this->assertStringContainsString('Message en cours', $html);
        $this->assertStringContainsString('tv-public-message', $html);
        $this->assertStringContainsString('tv-notif-stack', $html);
        $this->assertStringContainsString('Afficher le prompteur', $html);
        $this->assertStringContainsString('x-ref="prompterTexte"', $html);
        $this->assertStringContainsString('x-ref="prompterPanel"', $html);
        $this->assertStringContainsString('startDrag($event)', $html);
        $this->assertStringContainsString('scheduleSave()', $html);
        $this->assertStringContainsString(
            'requestAnimationFrame',
            (string) file_get_contents(resource_path('js/chef-antenne-panel.js'))
        );
        $this->assertStringContainsString(
            "if (!cfg || typeof cfg !== 'object') return;",
            (string) file_get_contents(resource_path('js/conducteur-redaction.js'))
        );
    }

    public function test_les_fleches_enregistrent_le_nouvel_ordre(): void
    {
        $admin = $this->acteur('admin');
        $conducteur = $this->conducteur($admin);
        $premiere = $conducteur->sequences()->create(['title' => 'Premier', 'type' => 'SUJET', 'position' => 1]);
        $deuxieme = $conducteur->sequences()->create(['title' => 'Deuxième', 'type' => 'SUJET', 'position' => 2]);

        $this->actingAs($admin)
            ->patchJson(route('conducteurs.reorder', $conducteur), [
                'ordered_ids' => [$deuxieme->id, $premiere->id],
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertSame(1, (int) $deuxieme->fresh()->position);
        $this->assertSame(2, (int) $premiere->fresh()->position);
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
            'nom' => 'Émission test',
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
