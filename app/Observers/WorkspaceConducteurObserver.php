<?php

namespace App\Observers;

use App\Models\Conducteur;
use App\Services\SharedWorkspaceService;

class WorkspaceConducteurObserver
{
    public function __construct(private SharedWorkspaceService $workspace) {}

    public function saved(Conducteur $conducteur): void
    {
        if ($conducteur->type !== 'redaction' || ! $conducteur->wasChanged('owner_id')) {
            return;
        }
        $conducteur->sequences()->whereNull('editorial_story_id')->chunkById(100, function ($sequences): void {
            foreach ($sequences as $sequence) {
                $dossier = $this->workspace->syncSequence($sequence, auth()->user(), false);
                if ($dossier) {
                    $this->workspace->activity($dossier, auth()->user(), 'team_updated', 'Le responsable du conducteur a été mis à jour.');
                }
            }
        });
    }
}
