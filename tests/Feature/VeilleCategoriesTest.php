<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PoleRubriqueEnum;
use App\Models\SourceVeille;
use App\Models\User;
use App\Models\VeilleSubject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * CATÉGORIES DE VEILLE — vocabulaire unique (PoleRubriqueEnum)
 *
 * Avant : catégorie en texte libre (« Économie », « finance », « High-Tech »…),
 * 4 pills de filtre codées en dur, et 176 sujets infiltrables.
 *
 * Après : valeurs normalisées, filtres dynamiques avec compteurs,
 * saisie par liste déroulante, combinaison avec les filtres de statut.
 */
class VeilleCategoriesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('admin', 'web');

        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function source(array $overrides = []): SourceVeille
    {
        return SourceVeille::create(array_merge([
            'name' => 'Source test',
            'url' => 'https://exemple.test/rss.xml',
            'type' => 'rss',
            'frequency' => '15min',
            'status' => 'active',
        ], $overrides));
    }

    private function sujet(array $overrides = []): VeilleSubject
    {
        static $i = 0;
        $i++;

        return VeilleSubject::create(array_merge([
            'title' => 'Sujet de test '.$i,
            'status' => 'pending',
        ], $overrides));
    }

    private function pageListe(array $query = []): string
    {
        return $this->actingAs($this->admin())
            ->get(route('veille.sujets.index', $query))
            ->assertOk()
            ->getContent();
    }

    // =====================================================================
    //  1. NORMALISATION DU VOCABULAIRE
    // =====================================================================

    public function test_try_from_loose_convertit_les_libelles_sauvages(): void
    {
        $cas = [
            'International' => PoleRubriqueEnum::International,
            'Économie' => PoleRubriqueEnum::Economie,
            'finance' => PoleRubriqueEnum::Economie,
            'High-Tech' => PoleRubriqueEnum::Tech,
            'Tech & Société' => null,                 // inclassable → Non classé
            'Politique' => PoleRubriqueEnum::Politique,
            'FAITS DIVERS' => PoleRubriqueEnum::FaitsDivers,
            'Santé' => PoleRubriqueEnum::Sante,
            'faits_divers' => PoleRubriqueEnum::FaitsDivers,
        ];

        foreach ($cas as $brut => $attendu) {
            $this->assertSame(
                $attendu,
                PoleRubriqueEnum::tryFromLoose($brut),
                "« {$brut} » devrait donner « ".($attendu?->value ?? 'null').' ».'
            );
        }

        $this->assertNull(PoleRubriqueEnum::tryFromLoose(null));
        $this->assertNull(PoleRubriqueEnum::tryFromLoose('   '));
        $this->assertNull(PoleRubriqueEnum::tryFromLoose('n\'importe quoi'));
    }

    public function test_la_migration_normalise_les_anciens_libelles_et_rembourse_les_non_classes(): void
    {
        $source = $this->source(['category' => PoleRubriqueEnum::Economie->value]);

        // Anciennes valeurs "sauvages" insérées en brut, comme avant la migration
        foreach ([
            ['title' => 'Ancien 1', 'category' => 'Économie'],
            ['title' => 'Ancien 2', 'category' => 'finance'],
            ['title' => 'Ancien 3', 'category' => 'High-Tech'],
            ['title' => 'Ancien 4', 'category' => 'Bidule'],
            ['title' => 'Ancien 5', 'category' => null, 'source_id' => $source->id],
        ] as $ligne) {
            DB::table('veille_subjects')->insert(array_merge([
                'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
            ], $ligne));
        }

        // Rejoue la migration de normalisation
        (require base_path('database/migrations/2026_11_10_000001_normalise_categories_veille.php'))->up();

        $valeurs = DB::table('veille_subjects')->orderBy('id')->pluck('category', 'title')->all();

        $this->assertSame('economie', $valeurs['Ancien 1']);
        $this->assertSame('economie', $valeurs['Ancien 2']);
        $this->assertSame('tech', $valeurs['Ancien 3']);
        $this->assertNull($valeurs['Ancien 4'], 'Un libellé inclassable devient NULL (Non classé).');
        $this->assertSame('economie', $valeurs['Ancien 5'], 'Un sujet sans catégorie hérite de celle de sa source.');
    }

    // =====================================================================
    //  2. FILTRES
    // =====================================================================

    public function test_le_filtre_par_rubrique_ne_renvoie_que_cette_rubrique(): void
    {
        $eco = $this->sujet(['category' => PoleRubriqueEnum::Economie->value]);
        $intl = $this->sujet(['category' => PoleRubriqueEnum::International->value]);
        $this->sujet(['category' => null]);

        $html = $this->pageListe(['category' => 'economie']);

        $this->assertStringContainsString($eco->title, $html);
        $this->assertStringNotContainsString($intl->title, $html);
    }

    public function test_le_filtre_non_classes_renvoie_les_sans_rubrique(): void
    {
        $classé = $this->sujet(['category' => PoleRubriqueEnum::Sport->value]);
        $sansRang = $this->sujet(['category' => null]);

        $html = $this->pageListe(['category' => 'none']);

        $this->assertStringContainsString($sansRang->title, $html);
        $this->assertStringNotContainsString($classé->title, $html);
    }

    public function test_les_filtres_rubrique_et_statut_se_combinent(): void
    {
        $ecoPending = $this->sujet(['category' => PoleRubriqueEnum::Economie->value, 'status' => 'pending']);
        $ecoLive = $this->sujet(['category' => PoleRubriqueEnum::Economie->value, 'status' => 'approved']);
        $intlPend = $this->sujet(['category' => PoleRubriqueEnum::International->value, 'status' => 'pending']);

        $html = $this->pageListe(['category' => 'economie', 'status' => 'pending']);

        $this->assertStringContainsString($ecoPending->title, $html);
        $this->assertStringNotContainsString($ecoLive->title, $html);
        $this->assertStringNotContainsString($intlPend->title, $html);
    }

    public function test_une_rubrique_inconnue_renvoie_une_liste_vide(): void
    {
        $this->sujet(['category' => PoleRubriqueEnum::Economie->value]);

        $html = $this->pageListe(['category' => 'bidule']);

        $this->assertStringNotContainsString('Sujet de test', $html);
    }

    public function test_la_liste_deroulante_affiche_les_compteurs_reels(): void
    {
        $this->sujet(['category' => PoleRubriqueEnum::Economie->value]);
        $this->sujet(['category' => PoleRubriqueEnum::Economie->value]);
        $this->sujet(['category' => null]);

        $html = $this->pageListe();

        // La barre de filtre est une liste déroulante (plus de pills en ligne)
        $this->assertStringContainsString('<select name="category"', $html);
        $this->assertStringNotContainsString(
            'text-[11px] opacity-70 font-semibold',
            $html,
            'Les anciennes pills de rubrique (compteur en span) ne doivent plus être générées.'
        );

        // Options avec compteurs, générées dynamiquement
        $this->assertStringContainsString('Toutes les rubriques', $html);
        $this->assertMatchesRegularExpression(
            '#<option[^>]*value="economie"[^>]*>Économie \(2\)</option>#u',
            $html,
            'L’option « Économie » doit afficher le compteur 2.'
        );
        $this->assertMatchesRegularExpression(
            '#<option[^>]*value="none"[^>]*>Non classés \(1\)</option>#u',
            $html,
            'L’option « Non classés » doit afficher le compteur 1.'
        );

        // Le bouton de soumission sert de repli sans JavaScript
        $this->assertStringContainsString('>Filtrer</button>', $html);
    }

    public function test_la_rubrique_active_est_preservee_dans_la_liste_deroulante(): void
    {
        $this->sujet(['category' => PoleRubriqueEnum::Economie->value]);

        $html = $this->pageListe(['category' => 'economie']);

        $this->assertMatchesRegularExpression(
            '#<option[^>]*value="economie"[^>]*selected[^>]*>Économie \(1\)</option>#u',
            $html,
            'L’option active doit porter l’attribut selected.'
        );

        // L’action de réinitialisation n’apparaît qu’un filtre actif
        $this->assertStringContainsString('Réinitialiser', $html);
    }

    // =====================================================================
    //  3. SAISIE (plus de texte libre)
    // =====================================================================

    public function test_une_rubrique_invalide_est_rejetee_a_la_mise_a_jour(): void
    {
        $sujet = $this->sujet();

        $this->actingAs($this->admin())
            ->from(route('veille.sujets.index'))
            ->put(route('veille.sujets.update', $sujet), [
                'title' => $sujet->title,
                'category' => 'finance',      // texte libre refusé
            ])
            ->assertRedirect(route('veille.sujets.index'))
            ->assertSessionHasErrors('category');
    }

    public function test_une_rubrique_valide_est_enregistree_et_affichee(): void
    {
        $sujet = $this->sujet();

        $this->actingAs($this->admin())
            ->put(route('veille.sujets.update', $sujet), [
                'title' => $sujet->title,
                'category' => PoleRubriqueEnum::Economie->value,
            ])
            ->assertRedirect();

        $this->assertSame(PoleRubriqueEnum::Economie, $sujet->fresh()->category);

        // La fiche affiche le libellé FR de la rubrique, pas la valeur brute
        $html = $this->actingAs($this->admin())
            ->get(route('veille.sujets.show', $sujet))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Économie / Finance', $html);
        $this->assertStringNotContainsString('<input name="category"', $html); // plus de champ texte libre
    }

    public function test_le_formulaire_de_sujet_propose_la_liste_des_rubriques(): void
    {
        $sujet = $this->sujet();

        $html = $this->actingAs($this->admin())
            ->get(route('veille.sujets.show', $sujet))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('<select name="category"', $html);
        $this->assertStringContainsString('— Non classé —', $html);
        $this->assertStringContainsString('International / Afrique / Monde', $html);
    }

    public function test_une_source_peut_etre_creee_avec_une_rubrique(): void
    {
        $this->actingAs($this->admin())
            ->post(route('veille.sources.store'), [
                'name' => 'RFI test',
                'url' => 'https://93.184.215.14/flux.xml',
                'category' => PoleRubriqueEnum::International->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('source_veille', [
            'name' => 'RFI test',
            'category' => 'international',
        ]);
    }

    public function test_une_rubrique_invalide_est_rejetee_sur_la_source(): void
    {
        $this->actingAs($this->admin())
            ->from(route('veille.sources'))
            ->post(route('veille.sources.store'), [
                'name' => 'Source invalide',
                'url' => 'https://exemple.test/flux2.xml',
                'category' => 'High-Tech',
            ])
            ->assertSessionHasErrors('category');

        $this->assertDatabaseMissing('source_veille', ['name' => 'Source invalide']);
    }
}
