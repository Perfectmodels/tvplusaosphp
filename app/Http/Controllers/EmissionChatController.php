<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\EmissionChatMessageSent;
use App\Models\Conducteur;
use App\Models\EmissionChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Discussion interne d'un conducteur d'émission (entre les rôles qui y ont accès).
 *
 * - GET  /conducteurs/emission/{conducteur}/chat        → messages (50 derniers, ou incrémental via ?after=ID)
 * - POST /conducteurs/emission/{conducteur}/chat        → envoyer un message (30 req/min)
 *
 * Accès : toute personne autorisée à voir le conducteur (ConducteurPolicy::view)
 * peut lire et écrire. Le canal privé broadcast est le même (`conducteur.{id}`),
 * déjà fermé par routes/channels.php.
 */
final class EmissionChatController extends Controller
{
    private const LIMIT_INITIAL = 50;

    private const LIMIT_INCREMENTAL = 100;

    public function index(Request $request, Conducteur $conducteur): JsonResponse
    {
        Gate::authorize('view', $conducteur);

        if ((string) $conducteur->type !== 'emission') {
            abort(404);
        }

        $after = max(0, (int) $request->query('after', 0));

        $query = $conducteur->emissionChatMessages()->with(['user.roles:id,name']);

        $messages = $after > 0
            ? $query->where('id', '>', $after)->orderBy('id')->limit(self::LIMIT_INCREMENTAL)->get()
            : $query->orderByDesc('id')->limit(self::LIMIT_INITIAL)->get()->reverse()->values();

        return response()->json([
            'messages' => $messages->map(fn (EmissionChatMessage $m): array => $this->formatMessage($m))->values()->all(),
        ]);
    }

    public function store(Request $request, Conducteur $conducteur): JsonResponse
    {
        Gate::authorize('view', $conducteur);

        if ((string) $conducteur->type !== 'emission') {
            abort(404);
        }

        $data = $request->validate([
            'message' => ['required', 'string', 'min:1', 'max:2000'],
        ], [
            'message.required' => 'Le message ne peut pas être vide.',
            'message.max' => 'Le message ne peut pas dépasser 2 000 caractères.',
        ]);

        $message = EmissionChatMessage::create([
            'conducteur_id' => (int) $conducteur->id,
            'user_id' => (int) $request->user()->id,
            'message' => trim((string) $data['message']),
        ]);

        $message->load(['user.roles:id,name']);

        // Temps réel si le broadcasting est activé (no-op sinon, cf. BROADCAST_CONNECTION).
        broadcast(new EmissionChatMessageSent($message))->toOthers();

        $payload = $this->formatMessage($message);
        $payload['mine'] = true;

        return response()->json(['message' => $payload]);
    }

    /**
     * Sérialisation partagée entre la lecture et l'écriture.
     *
     * @return array<string, mixed>
     */
    private function formatMessage(EmissionChatMessage $message): array
    {
        $user = $message->user;
        $roleName = $user?->roles->first()?->name;

        return [
            'id' => (int) $message->id,
            'user_id' => $message->user_id !== null ? (int) $message->user_id : null,
            'name' => $user?->name ?? 'Membre',
            'role' => $this->roleLabel($roleName, $user?->department),
            'message' => (string) $message->message,
            'time' => $message->created_at?->format('H\hi'),
            'day' => $message->created_at?->format('d/m/Y'),
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }

    private function roleLabel(?string $roleName, ?string $department): string
    {
        if ($roleName !== null && $roleName !== '') {
            $labels = config('workspaces.roles');

            return is_array($labels) && isset($labels[$roleName])
                ? (string) $labels[$roleName]
                : ucfirst(str_replace('_', ' ', $roleName));
        }

        return $department ? (string) $department : 'Équipe';
    }
}
