<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SourceVeille;
use App\Services\VeilleAutomationService;
use Illuminate\Http\JsonResponse;

class VeilleAutomationController extends Controller
{
    public function __invoke(VeilleAutomationService $automation): JsonResponse
    {
        $this->authorize('viewAny', SourceVeille::class);
        $snapshot = $automation->snapshot();

        return response()->json([
            ...$snapshot,
            'panel_html' => view('_partials.veille-automation', ['automation' => $snapshot])->render(),
        ])->header('Cache-Control', 'no-store');
    }
}
