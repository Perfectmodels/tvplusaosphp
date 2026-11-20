<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatutConducteurEnum;
use App\Enums\StatutDispoIntervenantEnum;
use App\Enums\StatutEmissionEnum;
use App\Enums\StatutEtapeWorkflowEnum;
use App\Models\Conducteur;
use App\Models\Emission;
use App\Models\Intervenant;
use App\Models\Journaliste;
use App\Models\ParcoursIntervenant;
use App\Models\Sequence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Dashboard dynamique — planning, suivi des intervenants, alertes et
 * progression sont calculés par DashboardController : plus aucune donnée
 * codée en dur (« Le Grand Matin », « En loge 07:45 », 3 alertes, 64 %…).
 */
class DashboardDynamicTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'chef_edition'): User
    {
        Role::findOrCreate($role);
        $u = User::factory()->create();
        $u->assignRole($role);

        return $u;
    }

    private function emission(array $attrs = []): Emission
    {
        return Emission::create(array_merge([
            'nom' => 'JT 18h',
            'title' => 'JT 18h',
            'slug' => 'jt-18h-'.uniqid(),
            'studio' => 'Studio B',
            'heure_debut' => '18:00:00',
            'is_active' => true,
        ], $attrs));
    }

    private function conducteur(Emission $emission, array $attrs = []): Conducteur
    {
        return Conducteur::create(array_merge([
            'nom' => 'Conducteur du jour',
            'date_edition' => now()->toDateString(),
            'type' => 'redaction',
            'emission_id' => $emission->id,
            'status' => StatutConducteurEnum::En_direct->value,
        ], $attrs));
    }

    private function intervenant(array $attrs = []): Intervenant
    {
        return Intervenant::create(array_merge([
            'nom' => 'Doe',
            'prenom' => 'John',
            'fonction' => 'Économiste',
            'organisme' => 'RFI',
            'is_active' => true,
        ], $attrs));
    }

    private function page(): string
    {
        return $this->actingAs($this->user())
            ->get('/dashboard')
            ->assertOk()
            ->getContent();
    }

    // =========================================================
    //  1. PLANNING — conducteurs du jour, pas de fausses lignes
    // =========================================================

    public static function rolesProduction(): array
    {
        return ['chef d’édition' => ['chef_edition'], 'administration' => ['admin']];
    }

    #[DataProvider('rolesProduction')]
    public function test_planning_et_invites_sont_visibles_dans_les_colonnes_sans_deplier_un_panneau(string $role): void
    {
        $user = $this->user($role);
        if ($role === 'admin') {
            $user->assignRole(Role::findOrCreate('chef_edition'));
        }
        $html = $this->actingAs($user)->get(route('dashboard', ['role' => 'chef_edition']))->assertOk()->getContent();
        $document = new \DOMDocument;
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);

        foreach (['ed-planning-title', 'ed-guests-title'] as $id) {
            $this->assertSame(1, $xpath->query('//*[@id="'.$id.'"]')->length);
            $this->assertSame(0, $xpath->query('//*[@id="'.$id.'"]/ancestor::details[not(@open)]')->length);
            $this->assertSame(1, $xpath->query('//*[@id="'.$id.'"]/ancestor::div[contains(concat(" ", normalize-space(@class), " "), " ed-grid ")]')->length);
        }
        $this->assertLessThan(strpos($html, '<details class="ed-operations">'), strpos($html, 'id="ed-planning-title"'));
        $this->assertLessThan(strpos($html, '<details class="ed-operations">'), strpos($html, 'id="ed-guests-title"'));
    }

    public function test_le_planning_affiche_les_conducteurs_du_jour(): void
    {
        $emission = $this->emission();
        $this->conducteur($emission, ['nom' => 'Conducteur JT 18h']);

        $html = $this->page();

        $this->assertStringContainsString('JT 18h', $html);
        $this->assertStringContainsString('Studio B', $html);
        $this->assertStringContainsString('18:00', $html);
        $this->assertStringContainsString('En direct', $html);
        $this->assertStringContainsString('Présenté par', $html);
    }

    public function test_les_lignes_du_prototype_n_existent_plus(): void
    {
        $html = $this->page();

        $this->assertStringNotContainsString('Le Grand Matin', $html);
        $this->assertStringNotContainsString('Éco Afrique', $html);
        $this->assertStringNotContainsString('Reportage Spécial', $html);
        $this->assertStringNotContainsString('Salle Montage 2', $html);
    }

    public function test_les_conducteurs_des_autres_jours_ne_sont_pas_au_planning(): void
    {
        $emission = $this->emission();
        $this->conducteur($emission, [
            'nom' => 'Conducteur d hier',
            'date_edition' => now()->subDay()->toDateString(),
        ]);

        $html = $this->page();

        $this->assertStringNotContainsString('Conducteur d hier', $html);
        $this->assertStringContainsString('Aucune émission programmée', $html);
    }

    // =========================================================
    //  2. SUIVI DES INTERVENANTS — parcours réels
    // =========================================================

    public function test_le_suivi_affiche_les_parcours_reels_du_jour(): void
    {
        $emission = $this->emission();
        $conducteur = $this->conducteur($emission);
        $intervenant = $this->intervenant();

        ParcoursIntervenant::create([
            'intervenant_id' => $intervenant->id,
            'conducteur_id' => $conducteur->id,
            'emission_id' => $emission->id,
            'confirme_at' => now()->subHours(2),
            'arrive_accueil_at' => now()->subMinutes(30),
            'en_maquillage_at' => now()->subMinutes(15),
            'heure_passage_prevue' => now()->setTime(18, 30),
        ]);

        $html = $this->page();

        $this->assertStringContainsString('John Doe', $html);
        $this->assertStringContainsString('Économiste', $html);
        $this->assertStringContainsString('RFI', $html);
        // Étape réelle issue du parcours (« En loge » était un libellé en dur)
        $this->assertStringContainsString('Maquillage', $html);
        $this->assertStringContainsString('Passage 18:30', $html);
        $this->assertStringNotContainsString('En loge', $html);
        $this->assertStringNotContainsString('En transit', $html);
        $this->assertStringNotContainsString('07:45', $html);
    }

    public function test_le_suivi_est_vide_sans_parcours_du_jour(): void
    {
        $html = $this->page();

        $this->assertStringContainsString('Aucun invité programmé', $html);
        $this->assertStringContainsString(route('suivi-intervenants.index'), $html);
    }

    // =========================================================
    //  3. ALERTES — compteur calculé, jamais 3 en dur
    // =========================================================

    public function test_les_alertes_sont_calculees_depuis_la_base(): void
    {
        $emission = $this->emission();
        $conducteur = $this->conducteur($emission, ['status' => StatutConducteurEnum::A_valider->value]);
        $intervenant = $this->intervenant();

        // 1 invité en retard + 1 conducteur à valider = 2 (pas 3)
        ParcoursIntervenant::create([
            'intervenant_id' => $intervenant->id,
            'conducteur_id' => $conducteur->id,
            'emission_id' => $emission->id,
            'retard_minutes' => 15,
        ]);

        $html = $this->page();

        $this->assertMatchesRegularExpression(
            '#<div class="text-3xl font-black mb-1 text-error">2</div>#u',
            $html,
            'Les alertes doivent afficher 2 (1 en retard + 1 à valider).'
        );
        $this->assertStringContainsString('1 invité(s) en retard', $html);
        $this->assertStringContainsString('1 conducteur(s) à valider', $html);
        $this->assertStringNotContainsString('Action requise immédiatement', $html);
    }

    public function test_les_alertes_sont_nulles_quand_rien_n_est_a_signaler(): void
    {
        $html = $this->page();

        $this->assertMatchesRegularExpression(
            '#<div class="text-3xl font-black mb-1 text-on-surface-variant">0</div>#u',
            $html
        );
        $this->assertStringContainsString('Aucune action requise', $html);
    }

    // =========================================================
    //  4. PROGRESSION — moyenne réelle des séquences, plus de 64 %
    // =========================================================

    public function test_la_progression_est_la_moyenne_des_sequences_du_jour(): void
    {
        $emission = $this->emission();

        $termine = $this->conducteur($emission, ['nom' => 'Conducteur A']);
        Sequence::create([
            'conducteur_id' => $termine->id,
            'title' => 'Sujet diffusé',
            'type' => 'SUJET',
            'position' => 1,
            'statut_diffusion' => StatutEtapeWorkflowEnum::Termine->value,
        ]);

        $enAttente = $this->conducteur($emission, ['nom' => 'Conducteur B']);
        Sequence::create([
            'conducteur_id' => $enAttente->id,
            'title' => 'Sujet en attente',
            'type' => 'SUJET',
            'position' => 1,
            'statut_diffusion' => StatutEtapeWorkflowEnum::En_attente->value,
        ]);

        $html = $this->page();

        // (100 % + 0 %) / 2 = 50 %
        $this->assertStringContainsString('>50%</div>', $html);
    }

    public function test_la_progression_est_nulle_sans_conducteur_du_jour(): void
    {
        $html = $this->page();

        // L'ancien fallback codé en dur (64 %) a disparu
        $this->assertStringNotContainsString('64%', $html);
        $this->assertStringContainsString('>0%</div>', $html);
    }

    // =========================================================
    //  5. KPI « ÉMISSIONS DIFFUSÉES » — statut réel, plus la somme
    // =========================================================

    public function test_le_kpi_diffusions_compte_les_emissions_diffusees(): void
    {
        $this->emission(['nom' => 'JT 12h', 'statut' => StatutEmissionEnum::Diffusee->value]);
        $this->emission(['nom' => 'JT 20h', 'statut' => StatutEmissionEnum::Diffusee->value]);
        $this->emission(['nom' => 'Éco Afrique', 'statut' => StatutEmissionEnum::Planifiee->value]);

        $html = $this->page();

        // 2 diffusées sur 3 au catalogue — plus la somme absurde
        // conducteurs + émissions affichée avant
        $this->assertMatchesRegularExpression(
            '#<div class="text-3xl font-black text-on-primary-fixed mb-1">2</div>#u',
            $html,
            'Le KPI doit afficher 2 émissions diffusées.'
        );
        $this->assertStringContainsString('sur 3 émissions au catalogue', $html);
        $this->assertStringContainsString('Émissions Diffusées', $html);
        $this->assertStringNotContainsString('Diffusions Totales', $html);
        $this->assertStringNotContainsString(' conducteurs · ', $html);
    }

    // =========================================================
    //  6. KPI « INVITÉS DISPONIBLES » — externes uniquement
    // =========================================================

    public function test_le_kpi_invites_compte_les_intervenants_disponibles(): void
    {
        $this->intervenant(); // disponible (défaut)
        $this->intervenant(); // disponible (défaut)
        $this->intervenant([
            'nom' => 'Occupé',
            'disponibilite' => StatutDispoIntervenantEnum::Occupe->value,
        ]);

        // Journalistes internes promus présentateurs : ne sont PAS des invités
        Journaliste::create(['nom' => 'A', 'prenom' => 'Présentateur', 'is_presentateur' => true]);
        Journaliste::create(['nom' => 'B', 'prenom' => 'Présentatrice', 'is_presentateur' => true]);

        $html = $this->page();

        // 2 disponibles sur 3 référencés — pas 2 + 2 présentateurs = 4
        $this->assertMatchesRegularExpression(
            '#<div class="text-3xl font-black text-on-primary-fixed mb-1">2</div>#u',
            $html,
            'Le KPI doit afficher 2 invités disponibles.'
        );
        $this->assertStringContainsString('sur 3 invités référencés', $html);
        $this->assertStringContainsString('Invités Disponibles', $html);
        $this->assertStringNotContainsString('Invités Actifs', $html);
        $this->assertStringNotContainsString('présentateurs ·', $html);
    }

    // =========================================================
    //  7. ÉLÉMENTS MORTS SUPPRIMÉS
    // =========================================================

    public function test_les_elements_morts_du_prototype_ont_disparu(): void
    {
        $html = $this->page();

        $this->assertStringNotContainsString('Créer une alerte', $html);
        $this->assertStringNotContainsString('Voir la carte flotte', $html);
        // La carte flotte était une image Google externe en fond
        $this->assertStringNotContainsString('lh3.googleusercontent.com', $html);
    }
}
