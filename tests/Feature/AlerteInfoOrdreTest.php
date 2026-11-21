<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AlerteInfo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AlerteInfoOrdreTest extends TestCase
{
    use RefreshDatabase;

    public function test_l_ordre_des_alertes_est_classe_par_ordre_choisi(): void
    {
        $this->acteur();

        AlerteInfo::create(['titre' => 'Basse', 'message' => 'Message A', 'niveau' => 'info', 'ordre' => 5]);
        AlerteInfo::create(['titre' => 'Haute', 'message' => 'Message B', 'niveau' => 'info', 'ordre' => 10]);
        AlerteInfo::create(['titre' => 'Milieu', 'message' => 'Message C', 'niveau' => 'info', 'ordre' => 7]);

        $ordres = AlerteInfo::query()->actives()->get()->pluck('ordre')->all();
        $this->assertSame([10, 7, 5], $ordres);
    }

    public function test_l_admin_choisit_l_ordre_a_la_diffusion(): void
    {
        $admin = $this->acteur();

        $this->actingAs($admin)->post(route('alertes-info.store'), [
            'titre' => 'Alerte A', 'message' => 'Message', 'niveau' => 'info', 'ordre' => 3,
        ])->assertRedirect();
        $this->actingAs($admin)->post(route('alertes-info.store'), [
            'titre' => 'Alerte B', 'message' => 'Message', 'niveau' => 'info', 'ordre' => 9,
        ])->assertRedirect();

        $alertes = AlerteInfo::query()->orderByDesc('ordre')->get();
        $this->assertSame(9, (int) $alertes->values()->get(0)->ordre);
        $this->assertSame(3, (int) $alertes->values()->get(1)->ordre);
    }

    public function test_une_alerte_sans_ordre_passe_au_dessus_des_alertes_precedentes(): void
    {
        $admin = $this->acteur();

        $this->actingAs($admin)->post(route('alertes-info.store'), [
            'titre' => 'Alerte A', 'message' => 'Message', 'niveau' => 'info', 'ordre' => 2,
        ])->assertRedirect();
        $this->actingAs($admin)->post(route('alertes-info.store'), [
            'titre' => 'Alerte B', 'message' => 'Message', 'niveau' => 'info',
        ])->assertRedirect();

        $alertes = AlerteInfo::query()->orderByDesc('ordre')->get();
        $this->assertSame(3, (int) $alertes->values()->get(0)->ordre);
        $this->assertSame(2, (int) $alertes->values()->get(1)->ordre);
    }

    private function acteur(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('admin', 'web'));

        return $user;
    }
}
