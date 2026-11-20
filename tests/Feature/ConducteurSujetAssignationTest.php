<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Conducteur;
use App\Models\ConducteurPresence;
use App\Models\Journaliste;
use App\Models\Sequence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * RÈGLE MÉTIER : seul un journaliste PRÉSENT à la réunion de rédaction
 * peut être assigné à un sujet du Conducteur de Rédaction.
 */
class ConducteurSujetAssignationTest extends TestCase
{
    use RefreshDatabase;

    private function chef(): User
    {
        foreach (['admin', 'chef_edition', 'journaliste'] as $r) {
            Role::findOrCreate($r);
        }
        $u = User::factory()->create();
        $u->assignRole('chef_edition');

        return $u;
    }

    private function contributeur(?Journaliste $journalisteLie = null): User
    {
        Role::findOrCreate('journaliste', 'web');
        $journalisteLie ??= Journaliste::create([
            'prenom' => 'Acteur',
            'nom' => 'Contributeur',
            'is_active' => true,
        ]);
        $u = User::factory()->create(['journaliste_id' => $journalisteLie->id]);
        $u->assignRole('journaliste');
        $u->givePermissionTo(
            Permission::findOrCreate('sequences.create_editorial', 'web'),
            Permission::findOrCreate('sequences.edit_own', 'web'),
        );

        return $u;
    }

    private function conducteur(): Conducteur
    {
        return Conducteur::create([
            'nom' => 'JT 12h45 — Test assignation',
            'date_edition' => '2026-10-02',
            'type' => 'redaction',
        ]);
    }

    private function journaliste(string $nom = 'Test'): Journaliste
    {
        return Journaliste::create(['nom' => $nom, 'prenom' => 'Jean', 'is_active' => true]);
    }

    private function sujetData(array $surcharge = []): array
    {
        return array_merge([
            'title' => 'Sujet de test pour assignation',
            'type' => 'SUJET',
        ], $surcharge);
    }

    // =========================================================
    //  ASSIGNATION REFUSÉE SI NON PRÉSENT
    // =========================================================

    public function test_assigner_un_journaliste_non_present_est_refuse(): void
    {
        $acteur = $this->contributeur();
        $cond = $this->conducteur();
        $absent = $this->journaliste('Absent');

        $this->actingAs($acteur)->postJson(
            "/conducteurs/redaction/{$cond->id}/sujets",
            $this->sujetData(['journaliste_responsable_id' => $absent->id])
        )->assertStatus(422)->assertJsonValidationErrors('journaliste_responsable_id');
    }

    public function test_assigner_un_journaliste_present_est_accepte(): void
    {
        $chef = $this->chef();
        $cond = $this->conducteur();
        $present = $this->journaliste('Présent');

        ConducteurPresence::create([
            'conducteur_id' => $cond->id,
            'journaliste_id' => $present->id,
            'arrivee_at' => '2026-10-02T14:00',
        ]);

        $this->actingAs($chef)->postJson(
            "/conducteurs/redaction/{$cond->id}/sujets",
            $this->sujetData(['journaliste_responsable_id' => $present->id])
        )->assertSuccessful();

        $this->assertDatabaseHas('sequences', [
            'conducteur_id' => $cond->id,
            'journaliste_responsable_id' => $present->id,
        ]);
    }

    public function test_un_sans_assignation_restee_possible(): void
    {
        $chef = $this->chef();
        $cond = $this->conducteur();

        $this->actingAs($chef)->postJson(
            "/conducteurs/redaction/{$cond->id}/sujets",
            $this->sujetData()
        )->assertSuccessful();

        $this->assertSame(1, Sequence::count());
    }

    // =========================================================
    //  AFFECTATION EXISTANTE CONSERVÉE
    // =========================================================

    public function test_une_affectation_existante_est_conservee_meme_sans_presence(): void
    {
        $chef = $this->chef();
        $cond = $this->conducteur();
        $ancien = $this->journaliste('Ancien assigné');

        // Le sujet est créé quand l'intéressé était présent
        ConducteurPresence::create([
            'conducteur_id' => $cond->id,
            'journaliste_id' => $ancien->id,
            'arrivee_at' => '2026-10-02T14:00',
        ]);

        $this->actingAs($chef)->postJson(
            "/conducteurs/redaction/{$cond->id}/sujets",
            $this->sujetData(['journaliste_responsable_id' => $ancien->id])
        )->assertSuccessful();

        $sujet = Sequence::first();

        // Puis il quitte la liste des présents
        ConducteurPresence::where('conducteur_id', $cond->id)->delete();

        // On modifie le titre SANS toucher à l'assignation → doit passer
        $this->actingAs($chef)->patchJson(
            "/conducteurs/redaction/{$cond->id}/sujets/{$sujet->id}",
            $this->sujetData([
                'title' => 'Titre modifié sans changer le responsable',
                'journaliste_responsable_id' => $ancien->id,
            ])
        )->assertSuccessful();

        $this->assertSame($ancien->id, (int) $sujet->fresh()->journaliste_responsable_id);
    }

    public function test_changer_vers_un_non_present_est_refuse(): void
    {
        $cond = $this->conducteur();
        $present = $this->journaliste('Présent');
        $acteur = $this->contributeur($present);
        $autre = $this->journaliste('Autre absent');

        ConducteurPresence::create([
            'conducteur_id' => $cond->id,
            'journaliste_id' => $present->id,
            'arrivee_at' => '2026-10-02T14:00',
        ]);

        $this->actingAs($acteur)->postJson(
            "/conducteurs/redaction/{$cond->id}/sujets",
            $this->sujetData(['journaliste_responsable_id' => $present->id])
        )->assertSuccessful();

        $sujet = Sequence::first();

        $this->actingAs($acteur)->patchJson(
            "/conducteurs/redaction/{$cond->id}/sujets/{$sujet->id}",
            $this->sujetData(['journaliste_responsable_id' => $autre->id])
        )->assertStatus(422)->assertJsonValidationErrors('journaliste_responsable_id');
    }

    // =========================================================
    //  RESPONSABLES D'ÉDITION — Pas de contrainte de présence
    // =========================================================

    public function test_un_chef_edition_peut_assigner_un_journaliste_absent(): void
    {
        $chef = $this->chef();
        $cond = $this->conducteur();
        $absent = $this->journaliste('JRI absent');

        $this->actingAs($chef)->postJson(
            "/conducteurs/redaction/{$cond->id}/sujets",
            $this->sujetData(['journaliste_responsable_id' => $absent->id])
        )->assertSuccessful();

        $this->assertDatabaseHas('sequences', [
            'conducteur_id' => $cond->id,
            'journaliste_responsable_id' => $absent->id,
        ]);
    }

    public function test_le_selecteur_du_chef_edition_propose_aussi_les_absents(): void
    {
        $chef = $this->chef();
        $cond = $this->conducteur();
        $absent = $this->journaliste('JRI absent du sélecteur');

        $html = $this->actingAs($chef)
            ->get("/conducteurs/redaction/{$cond->id}")
            ->assertOk()
            ->getContent();

        $options = $this->jrOptionsDepuisHtml($html);
        $this->assertContains($absent->id, array_column($options, 'value'));
    }

    // =========================================================
    //  SÉLECTEUR
    // =========================================================

    public function test_le_selecteur_ne_propose_que_les_presents(): void
    {
        $acteur = $this->contributeur();
        $cond = $this->conducteur();

        $present = $this->journaliste('Présent Ok');
        $absent = $this->journaliste('Absent Ok');

        ConducteurPresence::create([
            'conducteur_id' => $cond->id,
            'journaliste_id' => $present->id,
            'arrivee_at' => '2026-10-02T14:00',
        ]);

        $html = $this->actingAs($acteur)
            ->get("/conducteurs/redaction/{$cond->id}")
            ->assertOk()
            ->getContent();

        // Les options JRI sont sérialisées dans jrOptions (et NON dans journalistes,
        // qui reste alimenté avec tout le monde pour le panneau Présences).
        $options = $this->jrOptionsDepuisHtml($html);
        $ids = array_column($options, 'value');

        $this->assertContains($present->id, $ids, 'Le journaliste présent doit figurer dans jrOptions.');
        $this->assertNotContains($absent->id, $ids, 'Le journaliste absent ne doit PAS figurer dans jrOptions.');
    }

    public function test_le_selecteur_est_vide_sans_presence(): void
    {
        $acteur = $this->contributeur();
        $cond = $this->conducteur();
        $this->journaliste('Seul dispo');

        $html = $this->actingAs($acteur)
            ->get("/conducteurs/redaction/{$cond->id}")
            ->assertOk()
            ->getContent();

        $this->assertSame([], $this->jrOptionsDepuisHtml($html), 'jrOptions doit être vide sans présence.');
    }

    /** Extrait le tableau jrOptions sérialisé dans le x-init de la vue. */
    private function jrOptionsDepuisHtml(string $html): array
    {
        if (preg_match('/jrOptions:\s*(\[[^\]]*\])\s*,/s', $html, $m)) {
            $data = json_decode($m[1], true);

            return is_array($data) ? $data : [];
        }

        return [];
    }
}
