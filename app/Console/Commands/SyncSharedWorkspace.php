<?php

namespace App\Console\Commands;

use App\Models\EditorialStory;
use App\Models\Sequence;
use App\Services\SharedWorkspaceService;
use Illuminate\Console\Command;

class SyncSharedWorkspace extends Command
{
    protected $signature = 'workspace:sync';

    protected $description = 'Relier les sujets existants à leur dossier métier partagé';

    public function handle(SharedWorkspaceService $workspace): int
    {
        EditorialStory::query()->orderBy('id')->chunkById(100, function ($stories) use ($workspace): void {
            foreach ($stories as $story) {
                $workspace->syncStory($story, null, false);
            }
        });
        Sequence::whereHas('conducteur', fn ($c) => $c->where('type', 'redaction'))->with('conducteur')->chunkById(100, function ($sequences) use ($workspace): void {
            foreach ($sequences as $sequence) {
                $workspace->syncSequence($sequence, null, false);
            }
        });
        $this->info('Les sujets enregistrés sont reliés à l’espace partagé.');

        return self::SUCCESS;
    }
}
