<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EtapeParcoursIntervenantEnum;
use App\Models\Emission;
use App\Models\Intervenant;
use App\Models\IntervenantCheckinAttempt;
use App\Models\ParcoursIntervenant;
use App\Models\ParcoursIntervenantEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InvitationCheckinTest extends TestCase
{
    use RefreshDatabase;

    private function operator(): User
    {
        Role::findOrCreate('admin');
        $operator = User::factory()->create();
        $operator->assignRole('admin');

        return $operator;
    }

    private function invitation(?string $scheduledAt = null): array
    {
        $emission = Emission::create([
            'nom' => 'Journal du soir',
            'title' => 'Journal du soir',
            'slug' => 'journal-du-soir-'.uniqid(),
            'is_active' => true,
        ]);
        $guest = Intervenant::create([
            'nom' => 'Mensah',
            'prenom' => 'Koffi',
            'telephone_1' => '061234567',
            'is_active' => true,
        ]);
        $parcours = ParcoursIntervenant::create([
            'intervenant_id' => $guest->id,
            'emission_id' => $emission->id,
            'heure_passage_prevue' => $scheduledAt ?? now()->addHour(),
        ]);

        return [$parcours, $emission, $guest];
    }

    private function lookup(User $operator, ParcoursIntervenant $parcours, Emission $emission, string $method = 'camera')
    {
        return $this->actingAs($operator)->postJson(route('scanner-qr.lookup'), [
            'code' => $parcours->qr_token,
            'emission_id' => $emission->id,
            'scan_method' => $method,
        ]);
    }

    public function test_a_valid_scan_requires_confirmation_and_records_separate_scan_and_validation_times(): void
    {
        [$parcours, $emission, $guest] = $this->invitation();
        $operator = $this->operator();

        $lookup = $this->lookup($operator, $parcours, $emission)
            ->assertOk()
            ->assertJsonPath('result', 'pending')
            ->assertJsonPath('guest.nom', $guest->nom_complet)
            ->assertJsonPath('parcours.etapes.0.value', 'invitation_envoyee')
            ->assertJsonPath('parcours.etapes.4.value', 'arrive_accueil')
            ->assertJsonPath('parcours.etapes.4.fait', false);

        $attemptId = $lookup->json('attempt_id');
        $scannedAt = $lookup->json('scanned_at');
        $this->assertNull($parcours->fresh()->arrive_accueil_at);

        $this->travel(30)->seconds();
        $confirmation = $this->actingAs($operator)->postJson(route('scanner-qr.confirm'), [
            'attempt_id' => $attemptId,
            'token' => $parcours->qr_token,
            'emission_id' => $emission->id,
        ])->assertOk()
            ->assertJsonPath('result', 'accepted')
            ->assertJsonPath('parcours.etapes.4.fait', true);

        $parcours->refresh();
        $this->assertNotSame($scannedAt, $parcours->arrive_accueil_at->toIso8601String());
        $this->assertSame(
            $confirmation->json('validated_at'),
            $parcours->arrive_accueil_at->toIso8601String(),
        );
        $this->assertDatabaseHas('parcours_intervenant_events', [
            'parcours_intervenant_id' => $parcours->id,
            'etape' => EtapeParcoursIntervenantEnum::ArriveAccueil->value,
            'recorded_at' => $parcours->arrive_accueil_at->toDateTimeString(),
            'triggered_by_user_id' => $operator->id,
        ]);
        $this->assertDatabaseHas('intervenants_scan_logs', [
            'parcours_intervenant_id' => $parcours->id,
            'scanned_by_user_id' => $operator->id,
            'scan_method' => 'camera',
            'etat_resultat' => 'accepted',
        ]);
        $this->assertDatabaseHas('intervenant_checkin_attempts', [
            'id' => $attemptId,
            'result' => 'accepted',
            'scanned_by_user_id' => $operator->id,
        ]);
    }

    public function test_second_validation_is_idempotent_and_later_scan_is_reported_as_duplicate(): void
    {
        [$parcours, $emission] = $this->invitation();
        $operator = $this->operator();
        $attemptId = $this->lookup($operator, $parcours, $emission)->json('attempt_id');
        $payload = [
            'attempt_id' => $attemptId,
            'token' => $parcours->qr_token,
            'emission_id' => $emission->id,
        ];

        $this->actingAs($operator)->postJson(route('scanner-qr.confirm'), $payload)
            ->assertOk()->assertJsonPath('result', 'accepted');
        $firstArrival = $parcours->fresh()->arrive_accueil_at->toIso8601String();

        $this->actingAs($operator)->postJson(route('scanner-qr.confirm'), $payload)
            ->assertOk()->assertJsonPath('result', 'accepted');
        $this->lookup($operator, $parcours, $emission)
            ->assertStatus(422)
            ->assertJsonPath('result', 'already_recorded')
            ->assertJsonPath('parcours.etapes.4.fait', true);

        $this->assertSame($firstArrival, $parcours->fresh()->arrive_accueil_at->toIso8601String());
        $this->assertSame(1, ParcoursIntervenantEvent::query()
            ->where('parcours_intervenant_id', $parcours->id)
            ->where('etape', EtapeParcoursIntervenantEnum::ArriveAccueil->value)
            ->count());
    }

    public function test_invalid_expired_and_unscheduled_qrs_are_audited_without_storing_the_token(): void
    {
        [$expired, $emission] = $this->invitation(now()->subHours(25)->toDateTimeString());
        $operator = $this->operator();

        $this->lookup($operator, $expired, $emission)
            ->assertStatus(422)
            ->assertJsonPath('result', 'expired');
        $this->actingAs($operator)->postJson(route('scanner-qr.lookup'), [
            'code' => str_repeat('x', 48),
            'emission_id' => $emission->id,
            'scan_method' => 'manual',
        ])->assertStatus(422)->assertJsonPath('result', 'invalid');

        $unscheduled = ParcoursIntervenant::create([
            'intervenant_id' => $expired->intervenant_id,
            'emission_id' => $emission->id,
            'heure_passage_prevue' => null,
        ]);
        $this->lookup($operator, $unscheduled, $emission)
            ->assertStatus(422)
            ->assertJsonPath('result', 'invalid');

        $this->assertDatabaseHas('intervenant_checkin_attempts', [
            'result' => 'expired',
            'scan_method' => 'camera',
        ]);
        $this->assertDatabaseHas('intervenant_checkin_attempts', [
            'result' => 'invalid',
            'scan_method' => 'manual',
        ]);
        $this->assertArrayNotHasKey('code', (new IntervenantCheckinAttempt)->getAttributes());
        $this->assertArrayNotHasKey('token', (new IntervenantCheckinAttempt)->getAttributes());
    }

    public function test_qr_for_another_emission_is_rejected_and_cancellation_does_not_confirm_arrival(): void
    {
        [$parcours, $emission] = $this->invitation();
        $otherEmission = Emission::create([
            'nom' => 'Débat',
            'title' => 'Débat',
            'slug' => 'debat-'.uniqid(),
            'is_active' => true,
        ]);
        $operator = $this->operator();

        $this->actingAs($operator)->postJson(route('scanner-qr.lookup'), [
            'code' => $parcours->qr_token,
            'emission_id' => $otherEmission->id,
            'scan_method' => 'manual',
        ])->assertStatus(422)->assertJsonPath('result', 'invalid');

        $attemptId = $this->lookup($operator, $parcours, $emission)->json('attempt_id');
        $this->actingAs($operator)->postJson(route('scanner-qr.cancel'), [
            'attempt_id' => $attemptId,
        ])->assertOk()->assertJsonPath('result', 'cancelled');

        $this->assertNull($parcours->fresh()->arrive_accueil_at);
        $this->assertDatabaseHas('intervenant_checkin_attempts', [
            'id' => $attemptId,
            'result' => 'cancelled',
        ]);
    }

    public function test_checkin_rejects_the_legacy_public_invitation_qr(): void
    {
        [, $emission, $guest] = $this->invitation();

        $this->actingAs($this->operator())->postJson(route('scanner-qr.lookup'), [
            'code' => $guest->urlInvitation(),
            'emission_id' => $emission->id,
            'scan_method' => 'manual',
        ])->assertStatus(422)->assertJsonPath('result', 'invalid');
    }

    public function test_a_legacy_link_with_an_explicit_event_token_keeps_its_emission_binding(): void
    {
        [$parcours, $emission, $guest] = $this->invitation();

        $this->actingAs($this->operator())->postJson(route('scanner-qr.lookup'), [
            'code' => $guest->urlInvitationComplete().'&'.http_build_query(['invitation' => $parcours->qr_token]),
            'emission_id' => $emission->id,
            'scan_method' => 'photo',
        ])->assertOk()->assertJsonPath('result', 'pending')
            ->assertJsonPath('guest.parcours_id', $parcours->id);

        $this->assertNull($parcours->fresh()->arrive_accueil_at);
    }

    public function test_qr_image_is_event_specific_and_expires_24_hours_after_scheduled_passage(): void
    {
        [$parcours, , $guest] = $this->invitation();
        $url = $parcours->urlQr();
        $this->assertStringContainsString('/i/checkin/'.$parcours->qr_token.'.png', $url);
        $this->assertStringNotContainsString($guest->nom, $url);
        $this->assertStringNotContainsString($guest->code_intervenant, $url);

        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');
        $parcours->forceFill(['heure_passage_prevue' => now()->subHours(25)])->save();
        $this->get($url)->assertStatus(410);
    }

    public function test_an_unscheduled_public_invitation_explains_why_no_access_qr_is_available(): void
    {
        [$parcours, , $guest] = $this->invitation();
        $parcours->forceFill(['heure_passage_prevue' => null])->save();

        $this->get($guest->urlInvitation())->assertOk()
            ->assertSee('L’horaire de passage n’est pas encore programmé.')
            ->assertDontSee('Télécharger le QR d’accès');
    }

    public function test_scanner_routes_require_the_scan_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('scanner-qr.index'))->assertForbidden();
        $this->actingAs($user)->postJson(route('scanner-qr.lookup'), [])->assertForbidden();
    }

    public function test_scanner_tracking_endpoint_requires_scan_permission(): void
    {
        [$parcours] = $this->invitation();
        $user = User::factory()->create();

        $this->actingAs($user)->getJson(route('scanner-qr.tracking', $parcours))->assertForbidden();
    }

    public function test_scanner_can_read_the_current_journey_after_a_valid_scan(): void
    {
        [$parcours] = $this->invitation();
        $operator = $this->operator();

        $this->actingAs($operator)
            ->getJson(route('scanner-qr.tracking', $parcours))
            ->assertOk()
            ->assertJsonPath('parcours.id', $parcours->id)
            ->assertJsonPath('parcours.etapes.4.value', 'arrive_accueil')
            ->assertJsonPath('parcours.etapes.4.fait', false);
    }

    public function test_scanner_page_renders_the_live_journey_modal(): void
    {
        $this->actingAs($this->operator())
            ->get(route('scanner-qr.index'))
            ->assertOk()
            ->assertSee('Parcours de l’invité')
            ->assertSee('data-tracking-url-template', false)
            ->assertSee('data-guest-qr-reader', false)
            ->assertSee('id="qr-reader"', false)
            ->assertSee('Choisir dans la galerie')
            ->assertSee('Code INT, code d’accès ou lien d’invitation')
            ->assertDontSee('id="qr-video"', false)
            ->assertDontSee('data-qr-scanner', false);
    }

    public function test_a_scan_links_to_the_matching_guest_profile_when_the_operator_can_view_it(): void
    {
        [$parcours, $emission, $guest] = $this->invitation();

        $this->lookup($this->operator(), $parcours, $emission)
            ->assertOk()->assertJsonPath('guest.intervenant_id', $guest->id)
            ->assertJsonPath('guest.code_intervenant', $guest->code_intervenant)
            ->assertJsonPath('guest.fiche_url', route('intervenants.show', $guest));
    }

    public function test_a_scan_operator_without_profile_access_gets_no_profile_link_or_prefilled_profile(): void
    {
        [$parcours, $emission, $guest] = $this->invitation();
        Permission::findOrCreate('intervenants.view', 'web');
        $scan = Permission::findOrCreate('intervenants.scan', 'web');
        $operator = User::factory()->create();
        $operator->givePermissionTo($scan);

        $this->lookup($operator, $parcours, $emission)->assertOk()->assertJsonPath('guest.fiche_url', null);
        $this->actingAs($operator)->get(route('scanner-qr.index', ['intervenant' => $guest->id]))->assertForbidden();
    }

    public function test_the_profile_opens_the_reader_with_its_guest_code_prefilled(): void
    {
        [, , $guest] = $this->invitation();
        $operator = $this->operator();
        $url = route('scanner-qr.index', ['intervenant' => $guest->id]);

        $this->actingAs($operator)->get(route('intervenants.show', $guest))->assertOk()->assertSee($url, false);
        $this->get($url)->assertOk()->assertSee('Retour à la fiche de l’intervenant')
            ->assertSee('value="'.$guest->code_intervenant.'"', false)
            ->assertSee('data-intervenant-id="'.$guest->id.'"', false);
    }

    public function test_gallery_selection_does_not_force_the_mobile_camera(): void
    {
        $html = $this->actingAs($this->operator())->get(route('scanner-qr.index'))->assertOk()->getContent();
        preg_match('/<input[^>]*id="photo-input"[^>]*>/', $html, $input);

        $this->assertNotEmpty($input);
        $this->assertStringContainsString('accept="image/*"', $input[0]);
        $this->assertStringNotContainsString('capture=', $input[0]);
    }

    public function test_the_printed_guest_code_can_be_entered_manually_and_still_requires_confirmation(): void
    {
        [$parcours, $emission, $guest] = $this->invitation();
        $operator = $this->operator();
        $code = strtolower($guest->code_intervenant);

        $lookup = $this->actingAs($operator)->postJson(route('scanner-qr.lookup'), [
            'code' => $code, 'emission_id' => $emission->id, 'scan_method' => 'manual',
        ])->assertOk()->assertJsonPath('result', 'pending')->assertJsonPath('guest.parcours_id', $parcours->id);
        $this->assertNull($parcours->fresh()->arrive_accueil_at);

        $this->postJson(route('scanner-qr.confirm'), [
            'attempt_id' => $lookup->json('attempt_id'), 'token' => $code, 'emission_id' => $emission->id,
        ])->assertOk()->assertJsonPath('result', 'accepted');
        $this->assertNotNull($parcours->fresh()->arrive_accueil_at);
    }

    public function test_a_guest_number_does_not_match_an_invitation_for_another_emission(): void
    {
        [$parcours, , $guest] = $this->invitation();
        [, $otherEmission] = $this->invitation();

        $this->actingAs($this->operator())->postJson(route('scanner-qr.lookup'), [
            'code' => $guest->code_intervenant, 'emission_id' => $otherEmission->id, 'scan_method' => 'manual',
        ])->assertStatus(422)->assertJsonPath('result', 'invalid')->assertJsonPath('guest', null);
        $this->assertNull($parcours->fresh()->arrive_accueil_at);
    }

    public function test_ambiguous_manual_numbers_require_the_event_specific_qr_code(): void
    {
        [$parcours, $emission, $guest] = $this->invitation();
        $other = ParcoursIntervenant::create([
            'intervenant_id' => $guest->id, 'emission_id' => $emission->id, 'heure_passage_prevue' => now()->addHours(2),
        ]);
        $operator = $this->operator();

        $this->actingAs($operator)->postJson(route('scanner-qr.lookup'), [
            'code' => $guest->code_intervenant, 'emission_id' => $emission->id, 'scan_method' => 'manual',
        ])->assertStatus(422)->assertJsonPath('result', 'invalid')
            ->assertJsonPath('message', 'Plusieurs invitations correspondent à ce code pour cette émission. Saisissez le code d’accès indiqué sous le QR de l’invitation.');
        $this->lookup($operator, $other, $emission, 'manual')->assertOk()->assertJsonPath('guest.parcours_id', $other->id);
        $this->assertNull($parcours->fresh()->arrive_accueil_at);
        $this->assertNull($other->fresh()->arrive_accueil_at);
    }

    public function test_a_number_cannot_replace_the_scanned_token_during_camera_confirmation(): void
    {
        [$parcours, $emission, $guest] = $this->invitation();
        $operator = $this->operator();
        $attemptId = $this->lookup($operator, $parcours, $emission)->json('attempt_id');

        $this->postJson(route('scanner-qr.confirm'), [
            'attempt_id' => $attemptId, 'token' => $guest->code_intervenant, 'emission_id' => $emission->id,
        ])->assertStatus(422)->assertJsonPath('result', 'invalid');
        $this->assertNull($parcours->fresh()->arrive_accueil_at);
    }

    public function test_a_pasted_invitation_link_can_also_be_confirmed(): void
    {
        [$parcours, $emission, $guest] = $this->invitation();
        $operator = $this->operator();
        $url = $guest->urlInvitation().'?'.http_build_query(['invitation' => $parcours->qr_token, 'reference' => str_repeat('a', 100)]);

        $lookup = $this->actingAs($operator)->postJson(route('scanner-qr.lookup'), [
            'code' => $url, 'emission_id' => $emission->id, 'scan_method' => 'manual',
        ])->assertOk();
        $this->postJson(route('scanner-qr.confirm'), [
            'attempt_id' => $lookup->json('attempt_id'), 'token' => $url, 'emission_id' => $emission->id,
        ])->assertOk()->assertJsonPath('result', 'accepted');
        $this->assertNotNull($parcours->fresh()->arrive_accueil_at);
    }

    public function test_opening_the_reader_from_a_profile_rejects_another_guests_qr(): void
    {
        [$parcours, $emission] = $this->invitation();
        [, , $other] = $this->invitation();

        $this->actingAs($this->operator())->postJson(route('scanner-qr.lookup'), [
            'code' => $parcours->qr_token, 'emission_id' => $emission->id, 'scan_method' => 'photo', 'intervenant_id' => $other->id,
        ])->assertStatus(422)->assertJsonPath('result', 'invalid')
            ->assertJsonPath('message', 'Ce QR appartient à un autre intervenant que la fiche sélectionnée.');
        $this->assertNull($parcours->fresh()->arrive_accueil_at);
    }

    public function test_manual_numbers_detect_a_previous_arrival_and_prefer_the_only_pending_invitation(): void
    {
        [$parcours, $emission, $guest] = $this->invitation();
        $parcours->forceFill(['arrive_accueil_at' => now()])->save();
        $operator = $this->operator();
        $payload = ['code' => $guest->code_intervenant, 'emission_id' => $emission->id, 'scan_method' => 'manual'];

        $this->actingAs($operator)->postJson(route('scanner-qr.lookup'), $payload)
            ->assertStatus(422)->assertJsonPath('result', 'already_recorded');

        $next = ParcoursIntervenant::create([
            'intervenant_id' => $guest->id, 'emission_id' => $emission->id, 'heure_passage_prevue' => now()->addHours(2),
        ]);
        $this->postJson(route('scanner-qr.lookup'), $payload)
            ->assertOk()->assertJsonPath('result', 'pending')->assertJsonPath('guest.parcours_id', $next->id);
        $this->assertNull($next->fresh()->arrive_accueil_at);
    }
}
