<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EtapeParcoursIntervenantEnum;
use App\Models\Conducteur;
use App\Models\Emission;
use App\Models\Intervenant;
use App\Models\ParcoursIntervenant;
use App\Models\Sequence;
use App\Models\SequenceIntervenantPivot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * LOT 12 — parcours fantômes.
 *
 * Le pivot `sequence_intervenants` est la source de vérité du conducteur ;
 * `parcours_intervenants` en est le reflet pour le suivi 9 étapes.
 *
 * Règle métier :
 *  - ajout via le conducteur (modale sujet) → reflet pur : retirer l'invité
 *    retire aussi sa ligne de parcours ;
 *  - ajout manuel (émission) ou convocation déjà envoyée (QR / confirmation)
 *    → on conserve la ligne, on la DÉLIE seulement.
 */
class ParcoursFantomesTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        Role::findOrCreate('admin');

        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function conducteur(): Conducteur
    {
        $emission = Emission::create([
            'nom' => 'JT Fantômes Test',
            'title' => 'JT Fantômes Test',
            'slug' => 'jt-fantomes-test-'.uniqid(),
            'is_active' => true,
        ]);

        return Conducteur::create([
            'emission_id' => $emission->id,
            'date_edition' => now()->toDateString(),
            'type' => 'emission',
        ]);
    }

    private function intervenant(string $nom, string $prenom): Intervenant
    {
        return Intervenant::create([
            'nom' => $nom,
            'prenom' => $prenom,
            'fonction' => 'Expert',
            'is_active' => true,
        ]);
    }

    private function sequence(Conducteur $conducteur, string $titre = 'Sujet test'): Sequence
    {
        return Sequence::create([
            'conducteur_id' => $conducteur->id,
            'title' => $titre,
            'type' => 'SUJET',
            'position' => 1,
        ]);
    }

    /** La modale du chef d'édition envoie la liste en JSON (voir le front). */
    private function majInvites(Sequence $sequence, array $ids): void
    {
        $this->actingAs($this->user())
            ->from('/conducteurs')
            ->patch(route('conducteurs.sequences.update', $sequence), [
                'title' => $sequence->title,
                'type' => 'SUJET',
                'intervenant_ids' => json_encode($ids),
            ])
            ->assertRedirect('/conducteurs');
    }

    private function parcoursDuCouple(int $intervenantId, int $sequenceId): ?ParcoursIntervenant
    {
        return ParcoursIntervenant::query()
            ->where('intervenant_id', $intervenantId)
            ->where('sequence_id', $sequenceId)
            ->first();
    }

    // =========================================================
    //  1. RETRAIT D'UN INVITÉ — le reflet suit la liste
    // =========================================================

    public function test_retirer_un_invite_supprime_sa_ligne_de_parcours(): void
    {
        $conducteur = $this->conducteur();
        $sequence = $this->sequence($conducteur, 'Débat');
        $awa = $this->intervenant('Dupont', 'Awa');
        $paul = $this->intervenant('Martin', 'Paul');

        $this->majInvites($sequence, [$awa->id, $paul->id]);

        // L'attachement au pivot a créé les deux lignes de parcours
        $this->assertSame(2, ParcoursIntervenant::where('sequence_id', $sequence->id)->count());

        $this->majInvites($sequence, [$awa->id]);

        $this->assertNull(
            $this->parcoursDuCouple($paul->id, $sequence->id),
            'Paul a été retiré du sujet : sa ligne de parcours doit disparaître.'
        );

        $awaParcours = $this->parcoursDuCouple($awa->id, $sequence->id);
        $this->assertNotNull($awaParcours, 'Awa reste invitée : sa ligne doit être conservée.');
        $this->assertNotNull($awaParcours->sequence_intervenant_id);
    }

    // =========================================================
    //  2. RETRAIT DE TOUS LES INVITÉS (liste vide)
    // =========================================================

    public function test_retirer_tous_les_invites_supprime_toutes_les_lignes(): void
    {
        $conducteur = $this->conducteur();
        $sequence = $this->sequence($conducteur, 'Sans invité');
        $awa = $this->intervenant('Dupont', 'Awa');
        $paul = $this->intervenant('Martin', 'Paul');

        $this->majInvites($sequence, [$awa->id, $paul->id]);
        $this->assertSame(2, ParcoursIntervenant::where('sequence_id', $sequence->id)->count());

        $this->majInvites($sequence, []);

        $this->assertSame(0, $sequence->intervenants()->count());
        $this->assertSame(
            0,
            ParcoursIntervenant::where('sequence_id', $sequence->id)->count(),
            'Plus aucun invité dans le sujet : plus aucune ligne de parcours.'
        );
    }

    // =========================================================
    //  3. SUPPRESSION DU SUJET
    // =========================================================

    public function test_supprimer_le_sujet_supprime_les_lignes_de_parcours(): void
    {
        $conducteur = $this->conducteur();
        $sequence = $this->sequence($conducteur, 'Sujet à supprimer');
        $awa = $this->intervenant('Dupont', 'Awa');

        $this->majInvites($sequence, [$awa->id]);
        $this->assertNotNull($this->parcoursDuCouple($awa->id, $sequence->id));

        $this->actingAs($this->user())
            ->from('/conducteurs')
            ->delete(route('conducteurs.sequences.destroy', $sequence))
            ->assertRedirect('/conducteurs');

        $this->assertSame(
            0,
            ParcoursIntervenant::where('sequence_id', $sequence->id)->count(),
            'Sujet supprimé : aucune ligne de parcours ne doit survivre.'
        );
    }

    // =========================================================
    //  4. RÉ-AJOUT APRÈS RETRAIT — ni fantôme, ni doublon
    // =========================================================

    public function test_re_apres_retrait_recree_un_parcours_propre_sans_doublon(): void
    {
        $conducteur = $this->conducteur();
        $sequence = $this->sequence($conducteur, 'Aller-retour');
        $awa = $this->intervenant('Dupont', 'Awa');

        $this->majInvites($sequence, [$awa->id]);
        $this->majInvites($sequence, []);
        $this->assertNull($this->parcoursDuCouple($awa->id, $sequence->id));

        $this->majInvites($sequence, [$awa->id]);

        $parcours = $this->parcoursDuCouple($awa->id, $sequence->id);
        $this->assertNotNull($parcours);
        $this->assertSame(
            1,
            ParcoursIntervenant::where('intervenant_id', $awa->id)->where('sequence_id', $sequence->id)->count(),
            'Le ré-ajout ne doit jamais dupliquer la ligne de parcours.'
        );

        // Nouveau parcours relié au NOUVEAU pivot, étape « Invité » validée
        $pivot = SequenceIntervenantPivot::query()
            ->where('sequence_id', $sequence->id)
            ->where('intervenant_id', $awa->id)
            ->firstOrFail();
        $this->assertSame($pivot->id, $parcours->sequence_intervenant_id);
        $this->assertNotNull($parcours->invitation_envoyee_at);
        $this->assertSame(EtapeParcoursIntervenantEnum::Invitation, $parcours->etapeActuelle);
    }

    // =========================================================
    //  5. MANUEL / CONVOQUÉ — on DÉLIE, on ne supprime pas
    // =========================================================

    public function test_parcours_manuel_ou_deja_convoque_est_delie_et_conserve(): void
    {
        $conducteur = $this->conducteur();
        $sequence = $this->sequence($conducteur, 'Convocations réelles');
        $awa = $this->intervenant('Dupont', 'Awa');
        $paul = $this->intervenant('Martin', 'Paul');

        $this->majInvites($sequence, [$awa->id, $paul->id]);

        // Awa : convocation déjà envoyée (QR)
        $parcoursAwa = $this->parcoursDuCouple($awa->id, $sequence->id);
        $parcoursAwa->update(['qr_envoye_at' => now()]);

        // Paul : programmé manuellement au niveau émission
        $parcoursPaul = $this->parcoursDuCouple($paul->id, $sequence->id);
        $parcoursPaul->update(['emission_id' => $conducteur->emission_id]);

        $this->majInvites($sequence, []);

        foreach ([$awa->id => $parcoursAwa, $paul->id => $parcoursPaul] as $intervenantId => $parcours) {
            $conserve = $this->parcoursDuCouple($intervenantId, $sequence->id);

            $this->assertNotNull($conserve, "Parcours de l'intervenant {$intervenantId} : la ligne doit être conservée.");
            $this->assertSame($parcours->id, $conserve->id);
            $this->assertNull($conserve->sequence_intervenant_id, 'La ligne doit être déliée du conducteur.');
            $this->assertNull($conserve->conducteur_id);
        }

        // Une ligne purement automatique (ni émission, ni convocation) aurait
        // été supprimée — c'est couvert par les tests 1 et 2.
        $this->assertSame(2, ParcoursIntervenant::where('sequence_id', $sequence->id)->count());
    }
}
