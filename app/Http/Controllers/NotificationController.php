<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Conducteur;
use App\Models\EmissionInteractionMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class NotificationController extends Controller
{
    /**
     * État global des notifications affichées sur toutes les sections :
     * - le conducteur actuellement à l'antenne et sa séquence en cours ;
     * - les nouveaux messages du public (initialisation + incrémental).
     */
    public function etat(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null || ! Gate::forUser($user)->allows('viewAny', Conducteur::class)) {
            return response()->json([
                'live' => null,
                'public' => ['last_id' => 0, 'messages' => []],
            ]);
        }

        $live = Conducteur::query()
            ->where('type', 'emission')
            ->whereDate('date_edition', today())
            ->whereNull('antenne_ended_at')
            ->where(function ($query): void {
                $query->whereNotNull('antenne_started_at')
                    ->orWhere('antenne_offset_sec', '>', 0);
            })
            ->whereNotNull('active_sequence_id')
            ->with(['emission', 'sequenceActive'])
            ->orderByDesc('antenne_started_at')
            ->first();

        $sequence = $live?->sequenceActive;
        $livePayload = null;

        if ($live !== null && $sequence !== null) {
            $livePayload = [
                'id' => (int) $live->id,
                'emission' => (string) ($live->emission?->nom ?? 'Émission'),
                'url' => route('conducteurs.emission.show', $live),
                'running' => $live->antenne_started_at !== null && $live->antenne_ended_at === null,
                'sequence' => [
                    'id' => (int) $sequence->id,
                    'position' => $sequence->position,
                    'title' => (string) $sequence->title,
                    'duree' => (string) $sequence->duree_hm,
                ],
            ];
        }

        $apres = $request->query('after');
        $dernierId = (int) EmissionInteractionMessage::query()->max('id');
        $messages = [];

        if ($apres !== null && $apres !== '') {
            $messages = EmissionInteractionMessage::query()
                ->with(['conducteur.emission'])
                ->where('id', '>', (int) $apres)
                ->orderBy('id')
                ->limit(20)
                ->get()
                ->map(static function (EmissionInteractionMessage $message): array {
                    $conducteur = $message->conducteur;

                    return [
                        'id' => (int) $message->id,
                        'name' => $message->nom,
                        'message' => $message->message,
                        'created_at_label' => $message->created_at?->format('d/m H:i'),
                        'emission' => $conducteur?->emission?->nom,
                        'url' => $conducteur !== null
                            ? route('conducteurs.emission.show', $conducteur).'#tv-qr-public-card'
                            : null,
                    ];
                })
                ->values()
                ->all();
        }

        return response()->json([
            'live' => $livePayload,
            'public' => [
                'last_id' => $dernierId,
                'messages' => $messages,
            ],
        ]);
    }
}
