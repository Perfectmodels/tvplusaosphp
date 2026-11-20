<?php

namespace App\Services;

use App\Models\Journaliste;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class JournalisteAccountService
{
    /** @return array{user: User, password: ?string} */
    public function creerOuLier(Journaliste $journaliste, User $actor, ?int $userId = null): array
    {
        return DB::transaction(function () use ($journaliste, $actor, $userId): array {
            $journaliste = Journaliste::query()->lockForUpdate()->findOrFail($journaliste->id);
            if ($journaliste->user_id !== null || User::where('journaliste_id', $journaliste->id)->exists()) {
                throw ValidationException::withMessages(['user_id' => 'Ce journaliste possède déjà un compte utilisateur.']);
            }

            $password = null;
            if ($userId !== null) {
                Gate::forUser($actor)->authorize('create', User::class);
                $user = User::query()->lockForUpdate()->findOrFail($userId);
                if ($user->journaliste_id !== null || $user->journalistes()->exists()) {
                    throw ValidationException::withMessages(['user_id' => 'Ce compte est déjà lié à un journaliste.']);
                }
                if (Str::lower($user->email) !== Str::lower((string) $journaliste->email)) {
                    throw ValidationException::withMessages(['email' => 'Utilisez l’adresse e-mail du compte sélectionné.']);
                }
            } else {
                $password = Str::password(16, true, true, false, false);
                Role::findOrCreate('journaliste', 'web');
                $user = User::create([
                    'name' => trim($journaliste->prenom.' '.$journaliste->nom),
                    'email' => $journaliste->email,
                    'phone' => $journaliste->telephone,
                    'password' => $password,
                    'role' => 'journaliste',
                    'is_active' => $journaliste->is_active,
                    'email_verified_at' => now(),
                    'password_changed_at' => null,
                    'created_by' => $actor->id,
                ]);
                $user->syncRoles(['journaliste']);
            }

            $user->update(['journaliste_id' => $journaliste->id]);
            $journaliste->update(['user_id' => $user->id]);

            return ['user' => $user, 'password' => $password];
        }, 3);
    }
}
