<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ConducteurChanged implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public readonly int $conducteurId) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('conducteur.'.$this->conducteurId)];
    }

    public function broadcastAs(): string
    {
        return 'conducteur.changed';
    }

    public function broadcastWith(): array
    {
        return ['conducteur_id' => $this->conducteurId];
    }
}
