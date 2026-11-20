<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\EmissionChatMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Diffusion temps réel d'un message du chat d'émission.
 *
 * Canal privé `conducteur.{id}` : la fermeture de session est déjà gérée par
 * routes/channels.php (seuls les utilisateurs qui peuvent voir le conducteur
 * s'y abonnent). L'interface écoute `.chat.message` ; le polling côté client
 * reste la source de vérité tant que le broadcasting n'est pas activé.
 */
final class EmissionChatMessageSent implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public readonly EmissionChatMessage $message) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('conducteur.'.$this->message->conducteur_id)];
    }

    public function broadcastAs(): string
    {
        return 'chat.message';
    }

    public function broadcastWith(): array
    {
        $user = $this->message->user;

        return [
            'id' => (int) $this->message->id,
            'user_id' => $this->message->user_id !== null ? (int) $this->message->user_id : null,
            'name' => $user?->name ?? 'Membre',
            'message' => (string) $this->message->message,
            'created_at' => $this->message->created_at?->toIso8601String(),
        ];
    }
}
