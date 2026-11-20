<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\NiveauAlerteEnum;
use App\Models\AlerteInfo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class AlerteInfoController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasAnyRole(['admin', 'superadmin']), 403);

        $valid = $request->validate([
            'titre' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:1000'],
            'niveau' => ['required', 'string', Rule::enum(NiveauAlerteEnum::class)],
            'expire_at' => ['nullable', 'date', 'after:now'],
        ], [
            'titre.required' => 'Donnez un titre à l’alerte.',
            'message.required' => 'Saisissez le message de l’alerte.',
            'expire_at.after' => 'La date d’expiration doit être dans le futur.',
        ]);

        AlerteInfo::create([
            'titre' => $valid['titre'],
            'message' => $valid['message'],
            'niveau' => $valid['niveau'],
            'expire_at' => $valid['expire_at'] ?? null,
            'created_by' => $request->user()?->id,
        ]);

        return back()->with('success', 'Alerte diffusée sur toutes les pages.');
    }

    public function destroy(AlerteInfo $alerteInfo): RedirectResponse
    {
        abort_unless(auth()->user()?->hasAnyRole(['admin', 'superadmin']), 403);

        $alerteInfo->update(['actif' => false]);

        return back()->with('success', 'Alerte retirée.');
    }
}
