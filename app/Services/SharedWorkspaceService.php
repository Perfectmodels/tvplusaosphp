<?php

namespace App\Services;

use App\Enums\TypeSequenceEnum;
use App\Mail\NotificationDossierMail;
use App\Models\DigitalPublication;
use App\Models\EditorialStory;
use App\Models\Sequence;
use App\Models\User;
use App\Models\WorkspaceAttachment;
use App\Models\WorkspaceComment;
use App\Models\WorkspaceDossier;
use App\Models\WorkspaceNotification;
use App\Models\WorkspaceTask;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class SharedWorkspaceService
{
    public function isSubject(Sequence $sequence): bool
    {
        return $sequence->conducteur?->type === 'redaction' && ! in_array($sequence->type, [TypeSequenceEnum::Ouverture, TypeSequenceEnum::Generique, TypeSequenceEnum::Titres, TypeSequenceEnum::Meteo, TypeSequenceEnum::Agenda, TypeSequenceEnum::Annonce, TypeSequenceEnum::Publicite, TypeSequenceEnum::Conclusion], true);
    }

    public function syncStory(EditorialStory $story, ?User $actor = null, bool $record = true): WorkspaceDossier
    {
        return DB::transaction(function () use ($story, $actor, $record): WorkspaceDossier {
            $locked = EditorialStory::lockForUpdate()->findOrFail($story->id);
            $dossier = WorkspaceDossier::firstOrCreate(['source_key' => 'story:'.$locked->id], ['editorial_story_id' => $locked->id]);
            $created = $dossier->wasRecentlyCreated;
            $dossier = WorkspaceDossier::whereKey($dossier->id)->lockForUpdate()->firstOrFail();
            $ids = $locked->sequences()->get(['journaliste_id', 'journaliste_responsable_id'])->flatMap(fn ($s) => [$s->journaliste_id, $s->journaliste_responsable_id])->push($locked->assigned_journaliste_id)->filter()->unique()->all();
            $this->syncMembers($dossier, $locked->created_by, $this->staffUsers($ids));
            if ($created) {
                $this->activity($dossier, $actor, 'opened', 'Dossier partagé ouvert à partir du sujet enregistré.');
            } elseif ($record && $story->wasChanged(['title', 'summary', 'angle', 'assigned_journaliste_id', 'status', 'rejection_reason'])) {
                $this->activity($dossier, $actor, 'story_updated', 'Le sujet éditorial ou son affectation a été mis à jour.');
            }
            if ($record && $story->wasChanged('assigned_journaliste_id') && $locked->assigned_journaliste_id) {
                $this->notify($dossier, $actor, 'Un sujet de production vous a été affecté.', $this->staffUsers([$locked->assigned_journaliste_id]));
            }

            return $dossier;
        });
    }

    public function syncSequence(Sequence $sequence, ?User $actor = null, bool $record = true): ?WorkspaceDossier
    {
        return DB::transaction(function () use ($sequence, $actor, $record): ?WorkspaceDossier {
            $current = Sequence::lockForUpdate()->findOrFail($sequence->id);
            if ($current->editorial_story_id) {
                $story = EditorialStory::find($current->editorial_story_id);
                if (! $story) {
                    return null;
                }
                $dossier = $this->syncStory($story, $actor, false);
                $old = WorkspaceDossier::where('source_key', 'sequence:'.$current->id)->whereNull('merged_into_id')->first();
                if ($old && $old->id !== $dossier->id) {
                    $this->merge($old, $dossier, $actor);
                }
            } else {
                if (! $this->isSubject($current)) {
                    return null;
                }
                $dossier = WorkspaceDossier::firstOrCreate(['source_key' => 'sequence:'.$current->id], ['sequence_id' => $current->id]);
                $created = $dossier->wasRecentlyCreated;
                $dossier = WorkspaceDossier::whereKey($dossier->id)->lockForUpdate()->firstOrFail();
                $this->syncMembers($dossier, $current->conducteur?->owner_id, $this->staffUsers(array_filter([$current->journaliste_id, $current->journaliste_responsable_id])));
                if ($created) {
                    $this->activity($dossier, $actor, 'opened', 'Dossier partagé ouvert à partir du sujet de rédaction.');
                }
            }
            if ($record && ($sequence->wasRecentlyCreated || $sequence->wasChanged(['title', 'summary', 'angle_editorial', 'journaliste_id', 'journaliste_responsable_id', 'statut_terrain', 'statut_montage', 'statut_habillage', 'statut_livraison', 'statut_diffusion', 'video_url', 'live_url', 'heure_livraison_prevue', 'duration_seconds']))) {
                $this->activity($dossier, $actor, 'production_updated', 'Le conducteur, les médias ou l’avancement de production a été mis à jour.');
                $this->notify($dossier, $actor, 'L’avancement du sujet partagé a changé.');
            }

            return $dossier;
        });
    }

    public function syncStaff(array $ids, ?User $actor = null): void
    {
        if ($ids === []) {
            return;
        }
        $refresh = function (EditorialStory|Sequence $source) use ($actor): void {
            $key = $source instanceof EditorialStory ? 'story:'.$source->id : ($source->editorial_story_id ? 'story:'.$source->editorial_story_id : 'sequence:'.$source->id);
            $previous = WorkspaceDossier::where('source_key', $key)->first()?->members()->pluck('users.id')->sort()->values()->all() ?? [];
            $dossier = $source instanceof EditorialStory ? $this->syncStory($source, $actor, false) : $this->syncSequence($source, $actor, false);
            if ($dossier && $previous !== $dossier->members()->pluck('users.id')->sort()->values()->all()) {
                $this->activity($dossier, $actor, 'team_updated', 'L’équipe affectée au sujet a été actualisée depuis le compte employé.');
            }
        };
        EditorialStory::whereIn('assigned_journaliste_id', $ids)->chunkById(100, function ($stories) use ($refresh): void {
            foreach ($stories as $story) {
                $refresh($story);
            }
        });
        Sequence::where(fn ($s) => $s->whereIn('journaliste_id', $ids)->orWhereIn('journaliste_responsable_id', $ids))->chunkById(100, function ($sequences) use ($refresh): void {
            foreach ($sequences as $sequence) {
                $refresh($sequence);
            }
        });
    }

    private function staffUsers(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return User::where(fn ($u) => $u->whereIn('journaliste_id', $ids)->orWhere(fn ($u) => $u->whereNull('journaliste_id')->whereHas('journaliste', fn ($j) => $j->whereIn('id', $ids))))->pluck('id')->all();
    }

    private function syncMembers(WorkspaceDossier $dossier, ?int $creator, array $assignees): void
    {
        $existing = $dossier->members()->get()->keyBy('id');
        $ids = array_unique(array_filter([...$existing->keys()->all(), ...$assignees, $creator]));
        foreach ($ids as $id) {
            $invited = (bool) ($existing->get($id)?->pivot->is_invited ?? false);
            $isCreator = (int) $id === (int) $creator;
            $isAssignee = in_array((int) $id, array_map('intval', $assignees), true);
            if (! $invited && ! $isCreator && ! $isAssignee) {
                $dossier->members()->detach($id);
            } else {
                $dossier->members()->syncWithoutDetaching([$id => ['is_creator' => $isCreator, 'is_assignee' => $isAssignee, 'is_invited' => $invited]]);
            }
        }
        $dossier->unsetRelation('members');
    }

    private function merge(WorkspaceDossier $old, WorkspaceDossier $target, ?User $actor): void
    {
        DB::transaction(function () use ($old, $target, $actor): void {
            WorkspaceDossier::whereIn('id', [$old->id, $target->id])->orderBy('id')->lockForUpdate()->get();
            foreach ($old->members()->pluck('users.id') as $id) {
                $target->members()->syncWithoutDetaching([$id => ['is_invited' => true]]);
            }
            foreach (['workspace_tasks', 'workspace_comments', 'workspace_attachments', 'workspace_activities', 'workspace_notifications'] as $table) {
                DB::table($table)->where('workspace_dossier_id', $old->id)->update(['workspace_dossier_id' => $target->id]);
            }
            $old->update(['merged_into_id' => $target->id, 'sequence_id' => null]);
            $this->activity($target, $actor, 'merged', 'Les échanges et tâches du sujet ont été regroupés dans ce dossier.');
        });
    }

    public function sourceRemoved(string $key, ?User $actor = null): void
    {
        $dossier = WorkspaceDossier::where('source_key', $key)->whereNull('merged_into_id')->first();
        if ($dossier) {
            $this->activity($dossier, $actor, 'source_removed', 'La source a été supprimée. L’historique partagé reste consultable.');
        }
    }

    public function publicationChanged(DigitalPublication $publication, ?User $actor = null): void
    {
        $keys = [];
        foreach ([$publication->editorial_story_id, $publication->getOriginal('editorial_story_id')] as $id) {
            if ($id) {
                $keys[] = 'story:'.$id;
            }
        }
        foreach ([$publication->source_sequence_id, $publication->getOriginal('source_sequence_id')] as $id) {
            if ($id) {
                $keys[] = 'sequence:'.$id;
            }
        }
        foreach (WorkspaceDossier::whereIn('source_key', array_unique($keys))->get() as $dossier) {
            $dossier = $dossier->mergedInto ?? $dossier;
            $this->activity($dossier, $actor, 'publication_updated', ($publication->exists ? 'Publication numérique : '.config('workspaces.digital_statuses.'.$publication->status, $publication->status).'.' : 'Le contenu numérique associé a été supprimé.'));
            $this->notify($dossier, $actor, 'Le contenu numérique associé au sujet a été mis à jour.');
        }
    }

    public function activity(WorkspaceDossier $dossier, ?User $actor, string $kind, string $message): void
    {
        $dossier->activities()->create(['actor_id' => $actor?->id, 'actor_name' => $actor?->name, 'kind' => $kind, 'message' => mb_substr($message, 0, 500)]);
        $dossier->touch();
    }

    private function notify(WorkspaceDossier $dossier, ?User $actor, string $message, ?array $only = null): void
    {
        $members = $dossier->members()->where('is_active', true)->when($only !== null, fn ($u) => $u->whereIn('users.id', $only))->get();
        foreach ($members as $member) {
            if ($member->id !== $actor?->id && $member->checkPermissionTo('workspace.view', 'web')) {
                $dossier->notifications()->create(['user_id' => $member->id, 'message' => mb_substr($message, 0, 500)]);
                try {
                    Mail::to($member->email)->send(new NotificationDossierMail(
                        (string) ($dossier->title ?: 'Dossier partagé'),
                        (string) $message,
                        $dossier,
                    ));
                } catch (Throwable) {
                    // L’e-mail est optionnel : la notification in-app reste acquise.
                }
            }
        }
    }

    public function addMember(WorkspaceDossier $dossier, User $actor, User $target): void
    {
        Gate::forUser($actor)->authorize('manageMembers', $dossier);
        if (! $target->is_active || ! $target->checkPermissionTo('workspace.view', 'web')) {
            throw ValidationException::withMessages(['user_id' => 'Cette personne ne dispose pas d’un accès actif à l’espace partagé.']);
        }
        DB::transaction(function () use ($dossier, $actor, $target): void {
            $dossier = WorkspaceDossier::whereKey($dossier->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('manageMembers', $dossier);
            if ($dossier->hasMember($target)) {
                return;
            }
            $dossier->members()->syncWithoutDetaching([$target->id => ['is_invited' => true]]);
            $this->activity($dossier, $actor, 'member_added', $target->name.' rejoint le dossier partagé.');
            $this->notify($dossier, $actor, 'Vous avez rejoint l’équipe de ce dossier.', [$target->id]);
        });
    }

    public function removeMember(WorkspaceDossier $dossier, User $actor, User $target): void
    {
        Gate::forUser($actor)->authorize('manageMembers', $dossier);
        DB::transaction(function () use ($dossier, $actor, $target): void {
            $dossier = WorkspaceDossier::whereKey($dossier->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('manageMembers', $dossier);
            $member = $dossier->members()->whereKey($target->id)->firstOrFail();
            if ($member->pivot->is_creator || $member->pivot->is_assignee) {
                throw ValidationException::withMessages(['user_id' => 'Cette personne est responsable ou affectée au sujet source. Modifiez son affectation depuis la fiche du sujet.']);
            }
            $dossier->members()->detach($target->id);
            $this->activity($dossier, $actor, 'member_removed', $target->name.' ne participe plus au dossier.');
        });
    }

    public function comment(WorkspaceDossier $dossier, User $actor, string $body): WorkspaceComment
    {
        Gate::forUser($actor)->authorize('comment', $dossier);

        return DB::transaction(function () use ($dossier, $actor, $body): WorkspaceComment {
            $dossier = WorkspaceDossier::whereKey($dossier->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('comment', $dossier);
            $comment = $dossier->comments()->create(['user_id' => $actor->id, 'body' => trim($body)]);
            $this->activity($dossier, $actor, 'comment_added', 'Un commentaire a été ajouté au dossier.');
            $this->notify($dossier, $actor, $actor->name.' a ajouté un commentaire.');

            return $comment;
        });
    }

    private function assignee(WorkspaceDossier $dossier, User $actor, int $id): User
    {
        $target = User::where('is_active', true)->findOrFail($id);
        if (! $dossier->hasMember($target)) {
            $this->addMember($dossier, $actor, $target);
        }
        if (! $target->checkPermissionTo('workspace.view', 'web')) {
            throw ValidationException::withMessages(['assigned_to' => 'Cette personne n’a pas accès à l’espace partagé.']);
        }

        return $target;
    }

    public function createTask(WorkspaceDossier $dossier, User $actor, array $data): WorkspaceTask
    {
        Gate::forUser($actor)->authorize('createTask', $dossier);

        return DB::transaction(function () use ($dossier, $actor, $data): WorkspaceTask {
            $dossier = WorkspaceDossier::whereKey($dossier->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('createTask', $dossier);
            $target = $this->assignee($dossier, $actor, (int) $data['assigned_to']);
            $task = $dossier->tasks()->create(['title' => $data['title'], 'instructions' => $data['instructions'] ?? null, 'type' => $data['type'], 'due_at' => $data['due_at'] ?? null, 'assigned_to' => $target->id, 'created_by' => $actor->id, 'status' => 'todo']);
            $this->activity($dossier, $actor, 'task_created', 'Tâche attribuée : '.$task->title);
            $this->notify($dossier, $actor, 'Tâche attribuée : '.$task->title, [$target->id]);

            return $task;
        });
    }

    public function updateTask(WorkspaceDossier $dossier, WorkspaceTask $task, User $actor, array $data): void
    {
        abort_unless($task->workspace_dossier_id === $dossier->id, 404);
        Gate::forUser($actor)->authorize('changeTask', $dossier);
        DB::transaction(function () use ($dossier, $task, $actor, $data): void {
            $dossier = WorkspaceDossier::whereKey($dossier->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('changeTask', $dossier);
            $locked = WorkspaceTask::lockForUpdate()->findOrFail($task->id);
            abort_unless($locked->assigned_to === $actor->id || $actor->checkPermissionTo('workspace.manage_tasks', 'web'), 403);
            $attributes = ['status' => $data['status'], 'completed_at' => $data['status'] === 'done' ? ($locked->completed_at ?? now()) : null, 'blocked_reason' => $data['status'] === 'blocked' ? trim($data['blocked_reason']) : null];
            if (isset($data['assigned_to']) && (int) $data['assigned_to'] !== $locked->assigned_to) {
                abort_unless($actor->checkPermissionTo('workspace.manage_tasks', 'web'), 403);
                $attributes['assigned_to'] = $this->assignee($dossier, $actor, (int) $data['assigned_to'])->id;
            }
            $locked->fill($attributes);
            if (! $locked->isDirty()) {
                return;
            }
            $locked->save();
            $this->activity($dossier, $actor, 'task_updated', $locked->title.' : '.config('workspaces.task_statuses.'.$locked->status).'.');
            $this->notify($dossier, $actor, 'Une tâche a changé d’état : '.$locked->title);
        });
    }

    public function attach(WorkspaceDossier $dossier, User $actor, UploadedFile $file): WorkspaceAttachment
    {
        Gate::forUser($actor)->authorize('upload', $dossier);
        $path = $file->store('private/workspace/'.$dossier->id, ['disk' => 'local', 'visibility' => 'private']);
        if (! $path) {
            throw ValidationException::withMessages(['file' => 'Le document n’a pas pu être enregistré.']);
        }
        try {
            return DB::transaction(function () use ($dossier, $actor, $file, $path): WorkspaceAttachment {
                $dossier = WorkspaceDossier::whereKey($dossier->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize('upload', $dossier);
                $name = preg_replace('/[\x00-\x1f\x7f]/u', '', basename($file->getClientOriginalName())) ?: 'piece-jointe';
                $attachment = $dossier->attachments()->create(['uploaded_by' => $actor->id, 'name' => mb_substr($name, 0, 255), 'path' => $path, 'mime_type' => $file->getMimeType() ?: 'application/octet-stream', 'size' => $file->getSize()]);
                $this->activity($dossier, $actor, 'attachment_added', 'Pièce jointe ajoutée : '.$attachment->name);
                $this->notify($dossier, $actor, 'Une nouvelle pièce jointe est disponible.');

                return $attachment;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
    }

    public function notifications(User $user): Builder
    {
        return WorkspaceNotification::where('user_id', $user->id)->whereHas('dossier', fn ($d) => $d->accessibleTo($user));
    }

    public function unreadCount(User $user): int
    {
        return $user->is_active && $user->checkPermissionTo('workspace.view', 'web') ? $this->notifications($user)->whereNull('read_at')->count() : 0;
    }

    public function dashboard(User $user, ?string $role = null): ?array
    {
        if (! $user->is_active || ! $user->checkPermissionTo('workspace.view', 'web')) {
            return null;
        }
        $dossiers = WorkspaceDossier::accessibleTo($user)->whereNull('merged_into_id');
        $tasks = WorkspaceTask::whereHas('dossier', fn ($d) => $d->accessibleTo($user)->whereNull('merged_into_id'));
        $administrator = $user->hasAnyRole(['admin', 'superadmin']);
        $management = ($administrator || in_array($role, ['chef_edition', 'chef_redaction', 'admin', 'superadmin', 'directeur_general'], true)) && $user->checkPermissionTo('workspace.view_all', 'web');
        if (! $management) {
            $tasks->where('assigned_to', $user->id);
        }
        $types = $role ? config('workspaces.task_types_by_role.'.$role) : null;
        if ($types && (! $management || $administrator)) {
            $tasks->whereIn('type', $types);
        }
        $pending = (clone $tasks)->where('status', '!=', 'done');

        return ['dossiers' => (clone $dossiers)->count(), 'pending' => (clone $pending)->count(), 'overdue' => (clone $pending)->where('due_at', '<', now())->count(), 'blocked' => (clone $tasks)->where('status', 'blocked')->count(), 'tasks' => (clone $pending)->with('dossier.story', 'dossier.sequence')->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')->orderBy('due_at')->orderBy('id')->limit(4)->get()];
    }
}
