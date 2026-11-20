<?php

namespace App\Services;

use App\Events\ConducteurChanged;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class BulkDeletionService
{
    private const MODELS = [
        'journalistes' => Journaliste::class,
        'intervenants' => Intervenant::class,
        'emissions' => Emission::class,
        'utilisateurs' => User::class,
        'conducteurs-redaction' => Conducteur::class,
        'conducteurs-emission' => Conducteur::class,
        'sujets-veille' => VeilleSubject::class,
        'sources-veille' => SourceVeille::class,
        'remixes-veille' => VeilleRemix::class,
        'sujets-redaction' => Sequence::class,
        'sequences-emission' => Sequence::class,
        'presences-conference' => ConducteurPresence::class,
    ];

    public function delete(string $resource, array $ids, ?int $parentId = null): int
    {
        abort_unless(isset(self::MODELS[$resource]), 404);

        return DB::transaction(function () use ($resource, $ids, $parentId): int {
            $parent = null;
            if (in_array($resource, ['sujets-redaction', 'sequences-emission'], true)) {
                $parent = Conducteur::query()->whereKey($parentId)->lockForUpdate()->firstOrFail();
                if ($resource === 'sequences-emission') {
                    abort_if($parent->date_locked_at !== null, 403, 'Ce conducteur est verrouillé.');
                }
            }
            $records = $this->query($resource, $parentId)->whereKey($ids)->orderBy('id')->lockForUpdate()->get();
            if ($records->count() !== count($ids)) {
                throw ValidationException::withMessages(['ids' => 'Certains éléments ne sont plus disponibles dans cette liste. Rechargez la page.']);
            }
            $ability = match ($resource) {
                'sujets-redaction' => 'deleteSequenceRedaction',
                'sequences-emission' => 'deleteSequencePresentateur',
                default => 'delete',
            };
            foreach ($records as $record) {
                abort_if($record instanceof User && (int) $record->id === (int) auth()->id(), 403, 'Vous ne pouvez pas supprimer votre propre compte.');
                Gate::authorize($ability, $record);
            }
            foreach ($records as $record) {
                $this->remove($resource, $record);
            }
            if ($parent !== null) {
                if (in_array((int) $parent->active_sequence_id, array_map('intval', $ids), true)) {
                    $parent->updateQuietly(['active_sequence_id' => null]);
                }
                $parent->sequences()->orderBy('position')->orderBy('id')->get()->each(function (Sequence $sequence, int $index): void {
                    $sequence->timestamps = false;
                    $sequence->updateQuietly(['position' => $index + 1]);
                });
                ConducteurCalculatorService::recalculerHeures($parent->fresh());
                DB::afterCommit(fn () => ConducteurChanged::dispatch((int) $parent->id));
            }

            return $records->count();
        });
    }

    private function query(string $resource, ?int $parentId): Builder
    {
        $query = self::MODELS[$resource]::query();

        return match ($resource) {
            'conducteurs-redaction' => $query->where('type', 'redaction'),
            'conducteurs-emission' => $query->where('type', 'emission'),
            'sujets-redaction' => $query->with('conducteur')->where('conducteur_id', $parentId)->whereHas('conducteur', fn (Builder $q) => $q->where('type', 'redaction')),
            'sequences-emission' => $query->with('conducteur')->where('conducteur_id', $parentId)->whereHas('conducteur', fn (Builder $q) => $q->where('type', 'emission')),
            'remixes-veille' => $query->where('subject_id', $parentId),
            'presences-conference' => $query->where('conducteur_id', $parentId)->whereHas('conducteur', fn (Builder $q) => $q->where('type', 'redaction')),
            default => $query,
        };
    }

    private function remove(string $resource, Model $record): void
    {
        if ($resource === 'sources-veille') {
            $record->update(['is_active' => false]);

            return;
        }
        if ($resource === 'sujets-veille') {
            $record->update(['status' => 'archived']);

            return;
        }
        if ($record instanceof Conducteur) {
            $record->sequencesAvecMasques()->delete();
        }
        if ($record instanceof User) {
            Journaliste::where('user_id', $record->id)->update(['user_id' => null]);
            $record->journaliste?->update(['user_id' => null]);
            DB::table('model_has_roles')->where('model_type', User::class)->where('model_id', $record->id)->delete();
        }
        $record->delete();
    }
}
