<?php

namespace Tests\Feature;

use App\Enums\TypeEmissionEnum;
use App\Models\Conducteur;
use App\Models\ConducteurPresence;
use App\Models\Emission;
use App\Models\Intervenant;
use App\Models\Journaliste;
use App\Models\Sequence;
use App\Models\SourceVeille;
use App\Models\User;
use App\Models\VeilleRemix;
use App\Models\VeilleSubject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BulkDeletionTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function actor(string $role = 'admin'): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate($role, 'web'));

        return $user;
    }

    public static function resources(): array
    {
        return array_combine($names = ['journalistes', 'intervenants', 'emissions', 'utilisateurs', 'conducteurs-redaction', 'conducteurs-emission', 'sujets-veille', 'sources-veille', 'remixes-veille', 'sujets-redaction', 'sequences-emission', 'presences-conference'], array_map(fn ($name) => [$name], $names));
    }

    private function parent(string $resource): ?Model
    {
        return match ($resource) {
            'sujets-redaction', 'sequences-emission' => Conducteur::create(['nom' => 'Édition préparée', 'type' => $resource === 'sujets-redaction' ? 'redaction' : 'emission', 'date_edition' => today()]),
            'remixes-veille' => $this->record('sujets-veille'),
            'presences-conference' => Conducteur::create(['nom' => 'Conférence préparée', 'type' => 'redaction', 'date_edition' => today()]),
            default => null,
        };
    }

    private function record(string $resource, ?Model $parent = null, int $position = 1): Model
    {
        return match ($resource) {
            'journalistes' => Journaliste::factory()->create(['is_active' => true]),
            'intervenants' => Intervenant::factory()->create(),
            'utilisateurs' => User::factory()->create(),
            'emissions' => Emission::create(['nom' => 'Programme '.fake()->uuid(), 'type' => TypeEmissionEnum::JT->value]),
            'conducteurs-redaction', 'conducteurs-emission' => Conducteur::create(['nom' => 'Édition '.fake()->uuid(), 'type' => $resource === 'conducteurs-redaction' ? 'redaction' : 'emission', 'date_edition' => today()]),
            'sources-veille' => SourceVeille::create(['name' => 'Source '.fake()->uuid(), 'url' => 'https://example.test/feed/', 'type' => 'rss', 'is_active' => true]),
            'sujets-veille' => VeilleSubject::create(['title' => 'Article '.fake()->uuid(), 'slug' => fake()->uuid(), 'status' => 'pending']),
            'remixes-veille' => VeilleRemix::create(['subject_id' => $parent->id, 'title' => 'Remix saisi', 'content' => 'Texte rédigé.', 'type' => 'resume', 'status' => 'draft']),
            'presences-conference' => ConducteurPresence::create(['conducteur_id' => $parent->id, 'journaliste_id' => Journaliste::factory()->create()->id, 'arrivee_at' => now()]),
            'sujets-redaction', 'sequences-emission' => Sequence::create(['conducteur_id' => $parent->id, 'title' => 'Sujet '.$position, 'type' => 'SUJET', 'position' => $position, 'duration_seconds' => 60]),
        };
    }

    #[DataProvider('resources')]
    public function test_seule_la_selection_est_traitee_selon_l_action_du_module(string $resource): void
    {
        $parent = $this->parent($resource);
        $first = $this->record($resource, $parent, 1);
        $second = $this->record($resource, $parent, 2);
        $other = $this->record($resource, $parent, 3);
        $this->actingAs($this->actor())->from('/dashboard')->post(route('bulk.destroy', $resource), ['ids' => [$first->id, $second->id], 'parent_id' => $parent?->id])
            ->assertSessionHasNoErrors()->assertRedirect('/dashboard');
        foreach ([$first, $second] as $selected) {
            if ($resource === 'sources-veille') {
                $this->assertFalse($selected->fresh()->is_active);
            } elseif ($resource === 'sujets-veille') {
                $this->assertSame('archived', $selected->fresh()->status);
            } else {
                $this->assertModelMissing($selected);
            }
        }
        $this->assertModelExists($other);
        if ($resource === 'sources-veille') {
            $this->assertTrue($other->fresh()->is_active);
        }
        if ($resource === 'sujets-veille') {
            $this->assertSame('pending', $other->fresh()->status);
        }
    }

    #[DataProvider('resources')]
    public function test_un_compte_en_lecture_ne_peut_pas_supprimer_en_masse(string $resource): void
    {
        $parent = $this->parent($resource);
        $record = $this->record($resource, $parent);
        $this->actingAs($this->actor('lecture'))->postJson(route('bulk.destroy', $resource), ['ids' => [$record->id], 'parent_id' => $parent?->id])->assertForbidden();
        $this->assertModelExists($record);
    }

    public function test_un_refus_dans_le_lot_protege_tous_les_comptes(): void
    {
        $admin = $this->actor();
        $other = User::factory()->create();
        $this->actingAs($admin)->postJson(route('bulk.destroy', 'utilisateurs'), ['ids' => [$other->id, $admin->id]])->assertForbidden();
        $this->assertModelExists($admin);
        $this->assertModelExists($other);
    }

    public function test_la_suppression_des_comptes_conserve_et_dissocie_les_fiches_rh(): void
    {
        $user = User::factory()->create();
        $journaliste = Journaliste::factory()->create(['user_id' => $user->id]);
        $user->update(['journaliste_id' => $journaliste->id]);
        $this->actingAs($this->actor())->post(route('bulk.destroy', 'utilisateurs'), ['ids' => [$user->id]])->assertSessionHasNoErrors();
        $this->assertModelMissing($user);
        $this->assertModelExists($journaliste);
        $this->assertNull($journaliste->fresh()->user_id);
    }

    public function test_une_selection_incomplete_ou_d_un_autre_type_ne_supprime_rien(): void
    {
        $redaction = $this->record('conducteurs-redaction');
        $emission = $this->record('conducteurs-emission');
        $this->actingAs($this->actor())->postJson(route('bulk.destroy', 'conducteurs-redaction'), ['ids' => [$redaction->id, $emission->id]])->assertUnprocessable()->assertJsonValidationErrors('ids');
        $this->assertModelExists($redaction);
        $this->assertModelExists($emission);
        $this->postJson(route('bulk.destroy', 'conducteurs-redaction'), ['ids' => [$redaction->id, 999999]])->assertUnprocessable();
        $this->assertModelExists($redaction);
    }

    public function test_les_sequences_d_un_autre_conducteur_sont_refusees_et_l_ordre_reste_coherent(): void
    {
        $parent = $this->parent('sequences-emission');
        $otherParent = $this->parent('sequences-emission');
        $first = $this->record('sequences-emission', $parent, 1);
        $second = $this->record('sequences-emission', $parent, 2);
        $third = $this->record('sequences-emission', $parent, 3);
        $outside = $this->record('sequences-emission', $otherParent);
        $parent->update(['active_sequence_id' => $second->id]);
        $this->actingAs($this->actor())->postJson(route('bulk.destroy', 'sequences-emission'), ['ids' => [$first->id, $outside->id], 'parent_id' => $parent->id])->assertUnprocessable();
        $this->assertModelExists($first);
        $this->assertModelExists($outside);
        $this->post(route('bulk.destroy', 'sequences-emission'), ['ids' => [$first->id, $second->id], 'parent_id' => $parent->id])->assertSessionHasNoErrors();
        $this->assertSame(1, $third->fresh()->position);
        $this->assertNull($parent->fresh()->active_sequence_id);
        $this->assertModelExists($outside);
    }

    public function test_un_conducteur_verrouille_refuse_la_suppression_de_sequences(): void
    {
        $parent = $this->parent('sequences-emission');
        $record = $this->record('sequences-emission', $parent);
        $parent->update(['date_locked_at' => now()]);
        $this->actingAs($this->actor())->postJson(route('bulk.destroy', 'sequences-emission'), ['ids' => [$record->id], 'parent_id' => $parent->id])->assertForbidden();
        $this->assertModelExists($record);
    }

    public function test_une_erreur_de_base_annule_tout_le_lot(): void
    {
        $first = $this->record('emissions');
        $second = $this->record('emissions');
        DB::unprepared('CREATE TRIGGER refuse_second BEFORE DELETE ON emissions WHEN OLD.id = '.(int) $second->id." BEGIN SELECT RAISE(ABORT, 'Échec simulé'); END");
        Exceptions::fake();
        $this->actingAs($this->actor())->postJson(route('bulk.destroy', 'emissions'), ['ids' => [$first->id, $second->id]])->assertInternalServerError();
        $this->assertModelExists($first);
        $this->assertModelExists($second);
        Exceptions::assertReported(QueryException::class);
    }

    public static function invalidSelections(): array
    {
        return ['vide' => [[]], 'doublon' => [[1, 1]], 'texte' => [['article']], 'trop longue' => [range(1, 101)]];
    }

    #[DataProvider('invalidSelections')]
    public function test_la_selection_invalide_est_refusee(array $ids): void
    {
        $this->actingAs($this->actor())->postJson(route('bulk.destroy', 'emissions'), ['ids' => $ids])->assertUnprocessable();
    }

    public function test_le_contexte_d_un_lot_de_remixes_est_obligatoire(): void
    {
        $this->actingAs($this->actor())->postJson(route('bulk.destroy', 'remixes-veille'), ['ids' => [1]])->assertUnprocessable()->assertJsonValidationErrors('parent_id');
    }

    public function test_un_visiteur_est_redirige_vers_la_connexion(): void
    {
        $this->post(route('bulk.destroy', 'emissions'), ['ids' => [1]])->assertRedirect(route('login'));
    }

    public function test_une_ressource_inconnue_est_refusee(): void
    {
        $this->actingAs($this->actor())->post(route('bulk.destroy', 'inconnue'), ['ids' => [1]])->assertNotFound();
    }

    public static function listPages(): array
    {
        return [
            ['journalistes', 'journalistes.index'], ['intervenants', 'intervenants.index'],
            ['emissions', 'emissions.index'], ['utilisateurs', 'users.index'],
            ['conducteurs-redaction', 'conducteurs.redaction.index'], ['conducteurs-emission', 'conducteurs.emission.index'],
            ['sujets-veille', 'veille.sujets.index'], ['sources-veille', 'veille.sources'],
        ];
    }

    #[DataProvider('listPages')]
    public function test_les_listes_affichent_un_formulaire_separe_et_les_cases_autorisees(string $resource, string $route): void
    {
        $this->record($resource);
        $html = $this->actingAs($this->actor())->get(route($route))->assertOk()->getContent();
        $document = new \DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//form[@id="bulk-'.$resource.'"][@method="POST"]')->length);
        $this->assertGreaterThanOrEqual(1, $xpath->query('//input[@data-bulk-item][@form="bulk-'.$resource.'"]')->length);
        $this->assertSame(1, $xpath->query('//form[@id="bulk-'.$resource.'"]/input[@name="_token"]')->length);
    }

    public static function nestedPages(): array
    {
        return [
            ['sujets-redaction', 'conducteurs.redaction.show'],
            ['sequences-emission', 'conducteurs.presentateur.show'],
            ['sequences-emission', 'conducteurs.chef_edition'],
            ['remixes-veille', 'veille.sujets.show'],
            ['presences-conference', 'conducteurs.redaction.show'],
        ];
    }

    #[DataProvider('nestedPages')]
    public function test_les_listes_internes_conservent_le_contexte_du_parent(string $resource, string $route): void
    {
        $parent = $this->parent($resource);
        if ($parent instanceof Conducteur && $parent->type === 'emission') {
            $emission = $this->record('emissions');
            $parent->update(['emission_id' => $emission->id]);
        }
        $this->record($resource, $parent);
        $html = $this->actingAs($this->actor())->get(route($route, $parent->id))->assertOk()->getContent();
        $document = new \DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//form[@id="bulk-'.$resource.'"]//input[@name="parent_id"][@value="'.$parent->id.'"]')->length);
    }
}
