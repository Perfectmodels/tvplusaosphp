<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatutEtapeWorkflowEnum;
use App\Enums\TypeSequenceEnum;
use App\Models\Journaliste;
use App\Models\Sequence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * RÉGRESSION — Les fiches/listes de journalistes affichaient des nombres de
 * sujets FABRIQUÉS à partir de l'id (`100 + (id*37)%150`, `42 + (id%20)`,
 * `((id*3+7)%9)+2`…). Un journaliste créé se voyait donc « attribuer »
 * automatiquement des sujets qui n'existaient pas en base.
 *
 * Il n'y avait aucune logique d'attribution automatique (0 ligne en base,
 * aucun observer/listener) : le défaut était purement d'affichage.
 *
 * Désormais, seuls les SUJETS RÉELLEMENT attribués manuellement
 * (`sequences.journaliste_responsable_id`) sont comptés.
 */
class JournalisteSujetsReelsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('admin', 'web');

        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function creerJournaliste(array $overrides = []): Journaliste
    {
        return Journaliste::create(array_merge([
            'nom'    => 'Ndiaye',
            'prenom' => 'Awa',
        ], $overrides));
    }

    private function creerSujet(Journaliste $journaliste, array $overrides = []): Sequence
    {
        return Sequence::create(array_merge([
            'title'                      => 'Sujet attribué manuellement',
            'type'                       => TypeSequenceEnum::Sujet->value,
            'journaliste_responsable_id' => $journaliste->id,
        ], $overrides));
    }

    private function pageShow(Journaliste $journaliste): string
    {
        return $this->actingAs($this->admin())
            ->get(route('journalistes.show', $journaliste))
            ->assertOk()
            ->getContent();
    }

    private function pageIndex(): string
    {
        return $this->actingAs($this->admin())
            ->get(route('journalistes.index'))
            ->assertOk()
            ->getContent();
    }

    /**
     * Un compteur Blade est rendu ainsi :
     *   <p class="…">LIBELLÉ</p>\n<p class="…">VALEUR</p>
     */
    private function assertCompteur(string $html, string $libelle, int $valeur): void
    {
        $pattern = '#' . preg_quote($libelle, '#') . '</p>\s*<p[^>]*>\s*' . $valeur . '\s*</p>#u';

        $this->assertMatchesRegularExpression(
            $pattern,
            $html,
            "Le compteur « {$libelle} » devrait afficher {$valeur}."
        );
    }

    // =====================================================================
    //  1. UN JOURNALISTE CRÉÉ N'A AUCUN SUJET
    // =====================================================================

    public function test_un_journaliste_fraichement_cree_affiche_zero_sujet_partout(): void
    {
        $journaliste = $this->creerJournaliste();
        $html        = $this->pageShow($journaliste);

        $this->assertCompteur($html, 'Sujets traités', 0);
        $this->assertCompteur($html, 'Sujets en cours', 0);
        $this->assertCompteur($html, 'Total sujets', 0);
        $this->assertCompteur($html, 'En cours', 0);
        $this->assertCompteur($html, 'Diffusés', 0);
        $this->assertCompteur($html, 'À venir', 0);

        // badge de l'onglet « Sujets »
        $this->assertMatchesRegularExpression(
            '#Sujets <span[^>]*>\s*0\s*</span>#u',
            $html,
            'Le badge de l\'onglet doit afficher 0.'
        );

        // état vide de la table
        $this->assertStringContainsString('Aucun sujet attribué pour l\'instant', $html);
    }

    public function test_les_anciens_nombres_fictifs_napparaissent_plus_dans_la_fiche(): void
    {
        $journaliste = $this->creerJournaliste();
        $html        = $this->pageShow($journaliste);

        // Valeurs que l'ancienne vue fabriquait depuis l'id du journaliste
        $fictifs = [
            100 + (($journaliste->id * 37) % 150),  // « Sujets traités »
            2 + (($journaliste->id * 3) % 7),       // « Charge du jour »
            42 + ($journaliste->id % 20),           // badge de l'onglet
        ];

        foreach ($fictifs as $valeur) {
            $this->assertDoesNotMatchRegularExpression(
                '#>\s*' . $valeur . '\s*<#u',
                $html,
                "L'ancien chiffre fictif {$valeur} ne doit plus être affiché."
            );
        }

        $this->assertStringNotContainsString(
            'Les sujets apparaîtront ici une fois le module Conducteurs activé.',
            $html,
            'Le message codé en dur (faux) a été remplacé par la liste réelle.'
        );
    }

    // =====================================================================
    //  2. L'ATTRIBUTION MANUELLE SE REFLETE DANS LES COMPTEURS
    // =====================================================================

    public function test_un_sujet_attribue_manuellement_apparait_dans_la_fiche(): void
    {
        $journaliste = $this->creerJournaliste();
        $this->creerSujet($journaliste, ['title' => 'Le vrai sujet de Karim']);

        $html = $this->pageShow($journaliste);

        $this->assertCompteur($html, 'Sujets traités', 1);
        $this->assertCompteur($html, 'Total sujets', 1);

        $this->assertMatchesRegularExpression(
            '#Sujets <span[^>]*>\s*1\s*</span>#u',
            $html,
            'Le badge de l\'onglet doit passer à 1.'
        );

        // le sujet apparaît réellement dans la table de l'onglet
        $this->assertStringContainsString('Le vrai sujet de Karim', $html);
        $this->assertStringContainsString('Journaliste responsable', $html);
        $this->assertStringNotContainsString('Aucun sujet attribué pour l\'instant', $html);
    }

    public function test_les_sujets_des_autres_journalistes_ne_sont_pas_comptes(): void
    {
        $autre    = $this->creerJournaliste(['nom' => 'Mabicka', 'prenom' => 'Sylvie']);
        $moi      = $this->creerJournaliste(['nom' => 'Ndiaye', 'prenom' => 'Awa']);
        $sansAuteur = $this->creerJournaliste(['nom' => 'Diallo', 'prenom' => 'Karim']);

        $this->creerSujet($autre, ['title' => 'Sujet de Sylvie']);
        $this->creerSujet($autre, ['title' => 'Encore un de Sylvie']);

        $html = $this->pageShow($moi);

        $this->assertCompteur($html, 'Sujets traités', 0);
        $this->assertStringNotContainsString('Sujet de Sylvie', $html);

        // la relation morte journaliste_id ne doit pas compter non plus
        $this->creerSujet($sansAuteur, [
            'title'      => 'Sujet mal référencé',
            'journaliste_id' => $moi->id,       // colonne historique (jamais écrite par l'app)
            'journaliste_responsable_id' => $sansAuteur->id,
        ]);

        $this->assertCompteur($this->pageShow($moi), 'Sujets traités', 0);
    }

    // =====================================================================
    //  3. VENTILATION COHÉRENTE AVEC LE WORKFLOW
    // =====================================================================

    public function test_la_ventilation_avenir_en_cours_diffuse_reflete_le_workflow(): void
    {
        $journaliste = $this->creerJournaliste();

        // À venir : terrain non démarré
        $this->creerSujet($journaliste, [
            'title'         => 'Sujet à venir',
            'statut_terrain' => StatutEtapeWorkflowEnum::En_attente->value,
        ]);
        // En cours : terrain démarré, pas diffusé
        $this->creerSujet($journaliste, [
            'title'          => 'Sujet en cours',
            'statut_terrain' => StatutEtapeWorkflowEnum::En_cours->value,
        ]);
        // Diffusé : workflow terminé
        $this->creerSujet($journaliste, [
            'title'           => 'Sujet diffusé',
            'statut_terrain'  => StatutEtapeWorkflowEnum::Termine->value,
            'statut_montage'  => StatutEtapeWorkflowEnum::Termine->value,
            'statut_habillage'=> StatutEtapeWorkflowEnum::Termine->value,
            'statut_livraison'=> StatutEtapeWorkflowEnum::Termine->value,
            'statut_diffusion'=> StatutEtapeWorkflowEnum::Termine->value,
        ]);

        $stats = $this->actingAs($this->admin())
            ->get(route('journalistes.show', $journaliste))
            ->assertOk()
            ->viewData('stats');

        $this->assertSame(3, $stats['total']);
        $this->assertSame(1, $stats['avenir']);
        $this->assertSame(1, $stats['enCours']);
        $this->assertSame(1, $stats['diffuses']);

        // cohérence : la ventilation reconstitue exactement le total
        $this->assertSame(
            $stats['total'],
            $stats['avenir'] + $stats['enCours'] + $stats['diffuses']
        );
        $this->assertSame(33, $stats['diffusePct']);

        // et les mêmes valeurs sont rendues dans la page
        $html = $this->pageShow($journaliste);
        $this->assertCompteur($html, 'Total sujets', 3);
        $this->assertCompteur($html, 'À venir', 1);
        $this->assertCompteur($html, 'En cours', 1);
        $this->assertCompteur($html, 'Diffusés', 1);
    }

    // =====================================================================
    //  4. LISTE DES JOURNALISTES
    // =====================================================================

    public function test_la_colonne_charge_affiche_le_vrai_compteur(): void
    {
        $vide  = $this->creerJournaliste(['nom' => 'AAA', 'prenom' => 'Zéro']);
        $charge = $this->creerJournaliste(['nom' => 'BBB', 'prenom' => 'Une']);

        $this->creerSujet($charge, ['title' => 'Sujet de BBB']);

        $html = $this->pageIndex();

        $this->assertStringContainsString('0 sujet(s)', $html, 'Journaliste sans sujet → 0.');
        $this->assertStringContainsString('1 sujet(s)', $html, 'Journaliste avec 1 sujet attribué → 1.');

        // l'ancien nombre fabriqué ne doit plus apparaître
        $ancienFictif = ((($vide->id * 3 + 7) % 9) + 2);
        $this->assertDoesNotMatchRegularExpression(
            '#>\s*' . $ancienFictif . '\s+sujets?<#u',
            $html,
            "L'ancien nombre fictif {$ancienFictif} ne doit plus être affiché."
        );
    }

    public function test_le_compteur_de_liste_est_calcule_cote_controleur_sans_requete_par_ligne(): void
    {
        foreach (range(1, 5) as $i) {
            $j = $this->creerJournaliste(['nom' => "Journaliste{$i}", 'prenom' => 'Prénom']);
            $this->creerSujet($j);
        }

        DB::enableQueryLog();
        $response = $this->actingAs($this->admin())
            ->get(route('journalistes.index'))
            ->assertOk();
        $requetes = DB::getQueryLog();
        DB::disableQueryLog();

        // avecCount → une requête agrégée, et surtout AUCUNE requête par ligne
        $requetesSequences = array_filter(
            $requetes,
            fn (array $q) => str_contains(strtolower($q['query']), 'sequences')
        );

        $this->assertLessThanOrEqual(
            2,
            count($requetesSequences),
            'Le compteur doit être chargé via withCount (2 agrégats max), pas requête par journaliste.'
        );

        $journalistes = $response->viewData('journalistes');
        $this->assertSame(1, $journalistes->first()->sujets_count);
        $this->assertSame(0, $journalistes->first()->sujets_diffuses);
    }
}
