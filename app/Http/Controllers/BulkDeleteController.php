<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkDeleteRequest;
use App\Services\BulkDeletionService;
use Illuminate\Http\RedirectResponse;

class BulkDeleteController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(BulkDeleteRequest $request, string $resource, BulkDeletionService $deletion): RedirectResponse
    {
        $data = $request->validated();
        $count = $deletion->delete($resource, $data['ids'], isset($data['parent_id']) ? (int) $data['parent_id'] : null);
        $message = match ($resource) {
            'sources-veille' => $count.' source(s) désactivée(s). Les articles historiques sont conservés.',
            'sujets-veille' => $count.' sujet(s) archivé(s). Leur historique est conservé.',
            'utilisateurs' => $count.' compte(s) supprimé(s). Les fiches journalistes sont conservées.',
            'presences-conference' => $count.' présence(s) retirée(s) de cette conférence. Les fiches journalistes sont conservées.',
            default => $count.' élément(s) supprimé(s).',
        };

        return back()->with('status', $message);
    }
}
