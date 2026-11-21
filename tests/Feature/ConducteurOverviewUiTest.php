<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatutConducteurEnum;
use App\Models\Conducteur;
use App\Models\Emission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ConducteurOverviewUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-21 12:00:00', 'Africa/Libreville'));
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('admin', 'web'));

        return $user;
    }

    private function conducteur(string $date, string $title, string $type = 'redaction', string $status = 'Brouillon'): Conducteur
    {
        return Conducteur::create([
            'date_edition' => $date,
            'nom' => $title,
            'type' => $type,
            'status' => $status,
            'emission_id' => $type === 'emission'
                ? Emission::create([
                    'nom' => 'Programme '.$title,
                    'title' => 'Programme '.$title,
                    'slug' => Str::uuid()->toString(),
                    'is_active' => true,
                ])->id : null,
        ]);
    }

    public function test_la_vue_par_date_reste_le_comportement_initial(): void
    {
        $current = $this->conducteur('2026-11-21', 'Réunion courante');
        $this->conducteur('2026-11-20', 'Réunion antérieure');

        $this->actingAs($this->admin())->get(route('conducteurs.redaction.index'))
            ->assertOk()
            ->assertViewHas('conducteurs', fn ($rows) => $rows->modelKeys() === [$current->id])
            ->assertViewHas('allDates', false)
            ->assertSee('Tous les conducteurs');
    }

    public function test_la_vue_ensemble_retrouve_toutes_les_dates_et_permet_de_rechercher(): void
    {
        $old = $this->conducteur('2026-11-19', 'Conseil de rédaction A');
        $recent = $this->conducteur('2026-11-21', 'Réunion du matin');
        $this->actingAs($this->admin());

        $this->get(route('conducteurs.redaction.index', ['mode' => 'all']))
            ->assertOk()
            ->assertViewHas('allDates', true)
            ->assertViewHas('conducteurs', fn ($rows) =>
                $rows->total() === 2 && $rows->getCollection()->modelKeys() === [$recent->id, $old->id])
            ->assertSee('Rechercher un conducteur ou une émission');

        $this->get(route('conducteurs.redaction.index', ['mode' => 'all', 'q' => 'Conseil']))
            ->assertOk()
            ->assertViewHas('conducteurs', fn ($rows) =>
                $rows->total() === 1 && $rows->getCollection()->modelKeys() === [$old->id]);
    }

    public function test_filtrage_statut_et_pagination_de_toutes_dates(): void
    {
        for ($i = 1; $i <= 15; $i++) {
            $this->conducteur('2026-11-20', 'Conducteur '.$i, 'redaction',
                $i < 4 ? StatutConducteurEnum::Valide->value : StatutConducteurEnum::Brouillon->value);
        }
        $this->actingAs($this->admin());

        $this->get(route('conducteurs.redaction.index', ['mode' => 'all']))
            ->assertOk()
            ->assertViewHas('conducteurs', fn ($rows) =>
                $rows->total() === 15 && $rows->count() === 12)
            ->assertSee('Conducteurs 1–12');

        $this->get(route('conducteurs.redaction.index', ['mode' => 'all', 'page' => 2]))
            ->assertOk()
            ->assertViewHas('conducteurs', fn ($rows) =>
                $rows->total() === 15 && $rows->count() === 3);

        $this->get(route('conducteurs.redaction.index', ['mode' => 'all', 'status' => StatutConducteurEnum::Valide->value]))
            ->assertOk()
            ->assertViewHas('conducteurs', fn ($rows) => $rows->total() === 3);
    }

    public function test_la_vue_ensemble_separe_redaction_et_emission(): void
    {
        $redac = $this->conducteur('2026-11-19', 'Conducteur de rédaction');
        $emission = $this->conducteur('2026-11-21', 'Journal télévisé', 'emission');
        $this->actingAs($this->admin());

        $this->get(route('conducteurs.redaction.index', ['mode' => 'all']))
            ->assertOk()
            ->assertViewHas('conducteurs', fn ($rows) =>
                $rows->getCollection()->modelKeys() === [$redac->id]);

        $this->get(route('conducteurs.emission.index', ['mode' => 'all']))
            ->assertOk()
            ->assertViewHas('conducteurs', fn ($rows) =>
                $rows->getCollection()->modelKeys() === [$emission->id]);
    }

    public function test_parametres_de_recherche_invalides_sont_refuses(): void
    {
        $this->actingAs($this->admin());

        $this->getJson(route('conducteurs.redaction.index', ['mode' => 'invalide']))
            ->assertUnprocessable()->assertJsonValidationErrors('mode');
        $this->getJson(route('conducteurs.redaction.index', ['mode' => 'all', 'status' => 'inventé']))
            ->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_la_page_de_redaction_expose_une_recherche_et_des_actions_protegees(): void
    {
        $conducteur = $this->conducteur('2026-11-21', 'Conducteur atelier');
        $this->actingAs($this->admin())->get(route('conducteurs.redaction.show', $conducteur))
            ->assertOk()
            ->assertSee('redaction-sujet-recherche')
            ->assertSee('redaction-sujet-filtre')
            ->assertSee('Programmation et suivi des sujets')
            ->assertSee('s.can_edit_redaction')
            ->assertSee('s.can_delete_redaction');
    }
}
