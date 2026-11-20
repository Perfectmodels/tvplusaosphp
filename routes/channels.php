<?php

use App\Models\Conducteur;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('conducteur.{conducteurId}', function (User $user, int $conducteurId): bool {
    $conducteur = Conducteur::find($conducteurId);

    return $conducteur !== null && $user->can('view', $conducteur);
});
