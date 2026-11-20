<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssistantMessageRequest;
use App\Services\Ai\OpenCodeAssistant;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class AssistantController extends Controller
{
    public function __invoke(AssistantMessageRequest $request, OpenCodeAssistant $assistant): JsonResponse
    {
        $data = $request->validated();

        try {
            return response()->json([
                'answer' => $assistant->answer($data['message'], $data['history'] ?? []),
            ])->header('Cache-Control', 'no-store, private');
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 503)
                ->header('Cache-Control', 'no-store, private');
        }
    }
}
