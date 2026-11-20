<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Conducteur;
use App\Models\Emission;
use App\Models\Intervenant;
use App\Models\ParcoursIntervenant;
use App\Models\Sequence;
use App\Models\SequenceIntervenantPivot;
use App\Models\User;
use App\Services\InvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * QR permanent + invitation WhatsApp + CONFIRMATION PAR SCAN.
 *
 * Parcours cible :
 *   programmation → envoi WhatsApp (QR) → un UTILISATEUR scanne le QR
 *   → statut « confirmé » + placement automatique dans le conducteur.
 *
 * L'intervenant ne confirme pas lui-même : c'est l'opérateur qui valide au scan.
 */
class InvitationWhatsappTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================
    //  HELPERS
    // =========================================================

    private function user(): User
    {
        foreach (['admin', 'chef_edition'] as $r) {
            Role::findOrCreate($r);
        }
        $u = User::factory()->create();
        $u->assignRole('admin');

        return $u;
    }

    private function intervenant(array $attrs = []): Intervenant
    {
        return Intervenant::create(array_merge([
            'nom' => 'Mballa',
            'prenom' => 'Jean',
            'telephone_1' => '061234567',
            'is_active' => true,
        ], $attrs));
    }

    private function emission(): Emission
    {
        return Emission::create([
            'nom' => 'JT 18h',
            'title' => 'JT 18h',
            'slug' => 'jt-18h-'.uniqid(),
            'is_active' => true,
        ]);
    }

    /**
     * Parcours programmé au niveau ÉMISSION (sans pivot) + conducteur du jour.
     *
     * @return array{0: ParcoursIntervenant, 1: Emission, 2: Conducteur}
     */
    private function parcoursProgramme(?Intervenant $intervenant = null): array
    {
        $emission = $this->emission();
        $conducteur = Conducteur::create([
            'nom' => 'Conducteur test',
            'date_edition' => now()->toDateString(),
            'type' => 'redaction',
            'emission_id' => $emission->id,
        ]);

        $intervenant ??= $this->intervenant();

        $parcours = ParcoursIntervenant::create([
            'intervenant_id' => $intervenant->id,
            'emission_id' => $emission->id,
            'heure_passage_prevue' => now()->setTime(18, 30),
        ]);

        return [$parcours, $emission, $conducteur];
    }

    /** Parcours rattaché à une séquence mais pas encore au pivot (acceptation attendue). */
    private function parcoursAvecSequence(Intervenant $intervenant): array
    {
        $emission = $this->emission();
        $conducteur = Conducteur::create([
            'nom' => 'Conducteur séquence',
            'date_edition' => now()->toDateString(),
            'type' => 'redaction',
            'emission_id' => $emission->id,
        ]);
        $sequence = Sequence::create([
            'conducteur_id' => $conducteur->id,
            'title' => 'Sujet test',
            'type' => 'SUJET',
            'position' => 1,
        ]);

        $parcours = ParcoursIntervenant::create([
            'intervenant_id' => $intervenant->id,
            'sequence_id' => $sequence->id,
            'emission_id' => $emission->id,
            'heure_passage_prevue' => now()->setTime(18, 30),
        ]);

        return [$parcours, $sequence];
    }

    /** Simule le scan du QR par un utilisateur connecté (poste « Invitation »). */
    private function scannerInvitation(User $user, Intervenant $intervenant)
    {
        return $this->actingAs($user)->postJson('/scanner-qr/scan', [
            'poste' => 'invitation',
            'code' => $intervenant->code_intervenant,
            'uuid' => $intervenant->uuid_intervenant,
        ]);
    }

    // =========================================================
    //  JETON & URL
    // =========================================================

    public function test_le_booking_prepare_whatsapp_avec_les_details_et_le_lien_du_qr(): void
    {
        $intervenant = $this->intervenant(['telephone_1' => '', 'phone' => '061234567']);
        $emission = $this->emission();

        $response = $this->actingAs($this->user())->postJson(route('parcours-intervenants.programmer'), [
            'intervenant_id' => $intervenant->id,
            'emission_id' => $emission->id,
            'heure_passage' => '2026-11-20T18:30',
            'notes' => 'Note logistique interne à ne pas partager',
            'ouvrir_whatsapp' => true,
        ])->assertOk()->assertJsonPath('ok', true);

        $url = $response->json('whatsapp_url');
        $this->assertSame('wa.me', parse_url($url, PHP_URL_HOST));
        $this->assertSame('/24161234567', parse_url($url, PHP_URL_PATH));
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertStringContainsString('*TV+ Afrique | Votre invitation à notre émission*', $query['text']);
        $this->assertStringContainsString('Bonjour Jean,', $query['text']);
        $this->assertStringContainsString('📺 *Émission :* JT 18h', $query['text']);
        $this->assertStringContainsString('📅 *Date :* Vendredi 20 novembre 2026', $query['text']);
        $this->assertStringContainsString('🕒 *Heure :* 18h30 (heure de Libreville)', $query['text']);
        $this->assertStringContainsString('*À très bientôt sur TV+ Afrique !*', $query['text']);
        $parcours = ParcoursIntervenant::findOrFail($response->json('parcours.id'));
        $this->assertStringContainsString($parcours->fresh()->urlQr(), $query['text']);
        $this->assertStringNotContainsString('Note logistique interne', $query['text']);
        $this->assertStringNotContainsString('confirmer votre participation en ouvrant', $query['text']);

        $this->assertNotNull($parcours->qr_envoye_at);
        $this->assertNotNull($parcours->confirmation_expires_at);
        $this->assertNull($parcours->confirme_at);
        $this->assertNull($parcours->sequence_intervenant_id);
        $this->get(route('invitations.qr', ['token' => $intervenant->fresh()->invitation_token]))
            ->assertOk()->assertHeader('Content-Type', 'image/png');

        $this->actingAs($this->user())->postJson(route('parcours-intervenants.programmer'), [
            'intervenant_id' => $intervenant->id,
            'emission_id' => $emission->id,
            'heure_passage' => '2026-11-20T18:30',
            'ouvrir_whatsapp' => true,
        ])->assertConflict();
        $this->assertSame(1, ParcoursIntervenant::where('intervenant_id', $intervenant->id)->count());
    }

    public function test_un_booking_whatsapp_sans_telephone_ne_cree_pas_d_invitation(): void
    {
        $intervenant = $this->intervenant(['telephone_1' => null, 'phone' => null]);
        $emission = $this->emission();

        $this->actingAs($this->user())->postJson(route('parcours-intervenants.programmer'), [
            'intervenant_id' => $intervenant->id,
            'emission_id' => $emission->id,
            'heure_passage' => '2026-11-20T18:30',
            'ouvrir_whatsapp' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('intervenant_id');

        $this->assertDatabaseMissing('parcours_intervenants', ['intervenant_id' => $intervenant->id]);
    }

    public function test_le_bouton_booker_de_la_fiche_ouvre_la_programmation_du_bon_intervenant(): void
    {
        $intervenant = $this->intervenant();

        $this->actingAs($this->user())->get(route('intervenants.show', $intervenant))
            ->assertOk()
            ->assertSee(route('parcours-intervenants.index', ['intervenant_id' => $intervenant->id]), false)
            ->assertDontSee('href="#booker"', false);
    }

    public function test_la_fiche_invite_affiche_les_scans_reels_sans_evaluation_inventee(): void
    {
        $intervenant = $this->intervenant();

        $this->actingAs($this->user())->get(route('intervenants.show', $intervenant))
            ->assertOk()
            ->assertSee('Scans enregistrés')
            ->assertDontSee('évaluations');

        $this->assertDatabaseCount('intervenants_scan_logs', 0);
    }

    public function test_le_jeton_est_genere_a_la_demande_et_reste_stable(): void
    {
        $i = $this->intervenant();

        $this->assertNull($i->invitation_token, 'Le jeton ne doit pas être créé à la volée sans demande.');

        $token = $i->tokenInvitation();

        $this->assertSame(48, strlen($token));
        $this->assertSame($token, $i->fresh()->invitation_token);
        $this->assertSame($token, $i->tokenInvitation(), 'Le jeton doit rester stable.');
    }

    public function test_l_url_publique_est_stable_et_ne_depend_pas_de_l_authentification(): void
    {
        $i = $this->intervenant();

        $url = $i->urlInvitation();

        $this->assertStringContainsString('/i/'.$i->invitation_token, $url);
        $this->assertSame($url, $i->urlInvitation());
    }

    public function test_le_qr_public_ne_contient_que_le_jeton_opaque_de_l_invitation(): void
    {
        $i = $this->intervenant();

        $url = $i->urlInvitationComplete();
        $this->assertSame($i->urlInvitation(), $url);
        $this->assertStringStartsWith('/i/', (string) parse_url($url, PHP_URL_PATH));
        $this->assertNull(parse_url($url, PHP_URL_QUERY));
        $this->assertStringNotContainsString($i->code_intervenant, $url);
        $this->assertStringNotContainsString($i->uuid_intervenant, $url);
    }

    public function test_regenerer_le_jeton_invalide_l_ancien_qr(): void
    {
        $i = $this->intervenant();
        $ancien = $i->tokenInvitation();

        $nouveau = $i->regenererTokenInvitation();

        $this->assertNotSame($ancien, $nouveau);
        $this->assertSame($nouveau, $i->fresh()->invitation_token);
        $this->get('/i/'.$ancien)->assertNotFound();
        $this->get('/i/'.$nouveau)->assertOk();
    }

    // =========================================================
    //  PAGE PUBLIQUE (simple fiche, sans bouton de confirmation)
    // =========================================================

    public function test_la_page_publique_est_accessible_sans_authentification(): void
    {
        $i = $this->intervenant();
        $i->tokenInvitation();

        $this->get('/i/'.$i->invitation_token)
            ->assertOk()
            ->assertSee($i->nom_complet)
            ->assertSee('Votre invitation');
    }

    public function test_la_page_publique_n_offre_pas_de_bouton_de_confirmation(): void
    {
        [$parcours] = $this->parcoursProgramme();
        $i = $parcours->intervenant;
        $i->tokenInvitation();

        $page = $this->get('/i/'.$i->invitation_token)->assertOk()->getContent();

        // La confirmation est validée PAR SCAN par un utilisateur, pas par l'intervenant.
        $this->assertStringNotContainsString('Je confirme ma participation', $page);
        $this->assertStringContainsString('vérifiera votre invitation avant d’enregistrer votre arrivée', $page);
    }

    public function test_un_jeton_inconnu_affiche_une_page_404_en_francais(): void
    {
        $this->get('/i/'.str_repeat('a', 48))
            ->assertNotFound()
            ->assertSee('Lien invalide');
    }

    public function test_la_page_publique_affiche_les_invitations_en_attente(): void
    {
        [$parcours, $emission] = $this->parcoursProgramme();
        $i = $parcours->intervenant;
        $i->tokenInvitation();

        $this->get('/i/'.$i->invitation_token)
            ->assertOk()
            ->assertSee($emission->nom)
            ->assertSee('18:30');
    }

    public function test_la_page_publique_n_expose_pas_les_donnees_sensibles(): void
    {
        [$parcours] = $this->parcoursProgramme($this->intervenant(['email' => 'invitation-confidentielle@example.test']));
        $i = $parcours->intervenant;
        $i->tokenInvitation();

        $page = $this->get('/i/'.$i->invitation_token)->assertOk()->getContent();

        $this->assertStringNotContainsString('061234567', $page);
        $this->assertStringNotContainsString((string) $i->email, $page);
    }

    // =========================================================
    //  CONFIRMATION PAR SCAN (utilisateur connecté)
    // =========================================================

    public function test_le_scan_confirme_et_place_dans_le_conducteur(): void
    {
        $i = $this->intervenant();
        [$parcours, $sequence] = $this->parcoursAvecSequence($i);

        $this->scannerInvitation($this->user(), $i)
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('place_dans_conducteur', true);

        $parcours->refresh();

        $this->assertNotNull($parcours->confirme_at);
        $this->assertSame(InvitationService::MEDIUM_QR, $parcours->confirme_medium);
        $this->assertNotNull($parcours->sequence_intervenant_id, 'Le scan doit placer l’intervenant dans le conducteur.');
        $this->assertDatabaseHas('sequence_intervenants', [
            'sequence_id' => $sequence->id,
            'intervenant_id' => $i->id,
        ]);
    }

    public function test_le_scan_est_idempotent(): void
    {
        $i = $this->intervenant();
        [$parcours, $sequence] = $this->parcoursAvecSequence($i);

        $this->scannerInvitation($this->user(), $i)->assertOk();
        $this->scannerInvitation($this->user(), $i)
            ->assertOk()
            ->assertJsonPath('deja_fait', true);

        $this->assertSame(
            1,
            SequenceIntervenantPivot::query()
                ->where('sequence_id', $sequence->id)
                ->where('intervenant_id', $i->id)
                ->count(),
            'Un double scan ne doit pas créer un second pivot.'
        );
        $this->assertNotNull($parcours->fresh()->confirme_at);
    }

    public function test_le_scan_trace_l_utilisateur_qui_valide(): void
    {
        $i = $this->intervenant();
        [$parcours] = $this->parcoursProgramme($i);
        $operateur = $this->user();

        $this->scannerInvitation($operateur, $i)->assertOk();

        $this->assertSame($operateur->id, $parcours->fresh()->last_trigger_user_id);
    }

    public function test_une_invitation_expiree_ne_peut_pas_etre_confirmee(): void
    {
        [$parcours] = $this->parcoursProgramme();
        $i = $parcours->intervenant;

        $parcours->confirmation_expires_at = now()->subMinute();
        $parcours->save();

        $this->scannerInvitation($this->user(), $i)
            ->assertStatus(422)
            ->assertJsonPath('ok', false);

        $this->assertNull($parcours->fresh()->confirme_at);
    }

    public function test_un_code_inconnu_est_refuse(): void
    {
        $this->intervenant(); // au moins un intervenant

        $this->actingAs($this->user())
            ->postJson('/scanner-qr/scan', [
                'poste' => 'invitation',
                'code' => 'INT-XXXX-9999',
            ])
            ->assertNotFound()
            ->assertJsonPath('ok', false);
    }

    public function test_le_scan_sans_invitation_signale_l_absence(): void
    {
        // Intervenant sans aucun parcours programmé.
        $i = $this->intervenant();

        $this->scannerInvitation($this->user(), $i)
            ->assertStatus(422)
            ->assertJsonPath('ok', false);
    }

    // =========================================================
    //  WHATSAPP
    // =========================================================

    public function test_le_message_whatsapp_suit_le_modele_editorial(): void
    {
        $intervenant = $this->intervenant(['prenom' => 'Franck']);
        $emission = Emission::create([
            'nom' => 'REPORTAGES',
            'title' => 'REPORTAGES',
            'slug' => 'reportages-'.uniqid(),
            'is_active' => true,
        ]);

        $parcours = ParcoursIntervenant::create([
            'intervenant_id' => $intervenant->id,
            'emission_id' => $emission->id,
            'heure_passage_prevue' => '2026-11-14 15:51:00',
        ]);

        $qr = $parcours->urlQr();

        $attendu = "*TV+ Afrique | Votre invitation à notre émission*\n\n"
            ."Bonjour Franck,\n\n"
            ."C’est avec grand plaisir que nous vous accueillerons sur le plateau de *TV+ Afrique* à l’occasion de notre émission *REPORTAGES*.\n\n"
            ."Nous sommes ravis de vous compter parmi nos invités et avons hâte de partager ce moment avec vous !\n\n"
            ."*Voici les détails de votre passage :*\n\n"
            ."📺 *Émission :* REPORTAGES\n"
            ."📅 *Date :* Samedi 14 novembre 2026\n"
            ."🕒 *Heure :* 15h51 (heure de Libreville)\n\n"
            ."*Votre invitation personnelle :*\n"
            .$intervenant->urlInvitation()."\n\n"
            ."*Votre QR code d’accès :*\n"
            .$qr."\n\n"
            ."À votre arrivée, notre équipe de production sera heureuse de vous accueillir. "
            ."Il vous suffira de présenter votre QR code pour faciliter votre prise en charge.\n\n"
            ."Nous vous remercions pour votre disponibilité et nous réjouissons de vous retrouver très prochainement.\n\n"
            ."*À très bientôt sur TV+ Afrique !*\n\n"
            ."_L’équipe de production_\n"
            ."*TV+ Afrique*";

        $this->assertSame($attendu, app(InvitationService::class)->message($intervenant, $parcours));
    }

    public function test_le_lien_whatsapp_est_bien_forme(): void
    {
        [$parcours, $emission] = $this->parcoursProgramme();
        $i = $parcours->intervenant;

        $lien = app(InvitationService::class)->lienWhatsApp($i, $parcours);

        $this->assertNotNull($lien);
        $this->assertStringStartsWith('https://wa.me/24161234567?text=', $lien);

        parse_str((string) parse_url($lien, PHP_URL_QUERY), $query);
        $this->assertStringContainsString($emission->nom, $query['text']);
        $this->assertStringContainsString($i->urlInvitation(), $query['text']);
    }

    public function test_l_envoi_whatsapp_ouvre_la_fenetre_de_sept_jours(): void
    {
        [$parcours] = $this->parcoursProgramme();
        $i = $parcours->intervenant;

        $this->actingAs($this->user())
            ->post('/parcours-intervenants/'.$parcours->id.'/whatsapp')
            ->assertRedirectContains('wa.me');

        $parcours->refresh();

        $this->assertNotNull($parcours->invitation_envoyee_at);
        $this->assertNotNull($parcours->qr_envoye_at);
        $this->assertSame('whatsapp', $parcours->qr_medium);
        $this->assertNotNull($parcours->confirmation_expires_at);
        $this->assertTrue(
            $parcours->confirmation_expires_at->between(now()->addDays(6)->addHour(), now()->addDays(7)->addHour()),
            'La fenêtre doit expirer dans 7 jours.'
        );
    }

    public function test_un_renvoi_whatsapp_relance_la_fenetre(): void
    {
        [$parcours] = $this->parcoursProgramme();

        $parcours->confirmation_expires_at = now()->addDay();
        $parcours->save();

        $this->actingAs($this->user())
            ->post('/parcours-intervenants/'.$parcours->id.'/whatsapp')
            ->assertRedirectContains('wa.me');

        $this->assertTrue(
            $parcours->fresh()->confirmation_expires_at->greaterThan(now()->addDays(6)),
            'Un renvoi doit repartir sur 7 jours pleins.'
        );
    }

    public function test_un_intervenant_sans_telephone_ne_peut_pas_recevoir_whatsapp(): void
    {
        [$parcours] = $this->parcoursProgramme($this->intervenant(['telephone_1' => null]));

        $this->actingAs($this->user())
            ->post('/parcours-intervenants/'.$parcours->id.'/whatsapp')
            ->assertRedirect();

        $this->assertNull($parcours->fresh()->qr_envoye_at);
    }

    // =========================================================
    //  SÉCURITÉ
    // =========================================================

    public function test_les_routes_publiques_sont_limitees_en_debit(): void
    {
        $route = collect(Route::getRoutes())->first(
            static fn ($r) => $r->getName() === 'invitations.show'
        );

        $this->assertNotNull($route);
        $this->assertContains('throttle:30,1', $route->gatherMiddleware());
    }

    public function test_la_csp_autorise_la_redirection_vers_whatsapp(): void
    {
        $csp = (string) $this->get('/login')->assertOk()->headers->get('Content-Security-Policy');

        $this->assertStringContainsString('https://wa.me', $csp, 'Sans cette autorisation, le navigateur bloque la redirection du formulaire vers WhatsApp.');
    }

    public function test_le_qr_png_est_une_image(): void
    {
        $i = $this->intervenant();
        $i->tokenInvitation();

        $response = $this->get('/i/'.$i->invitation_token.'/qr.png');

        $response->assertOk();
        $this->assertSame('image/png', $response->headers->get('Content-Type'));
        $this->assertNotEmpty($response->getContent());
    }
}
