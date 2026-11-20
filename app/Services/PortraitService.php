<?php

namespace App\Services;

use App\Models\Intervenant;
use App\Models\Journaliste;
use Illuminate\Http\Request;

class PortraitService
{
    public function saveFromRequest(Request $request, Journaliste|Intervenant $personne, ?string $ancienneUrl = null): void
    {
        if ($request->boolean('remove_photo')) {
            $personne->clearMediaCollection('portrait');
            $personne->update(['photo' => null]);

            return;
        }
        if ($request->hasFile('photo_file')) {
            $personne->addMediaFromRequest('photo_file')->toMediaCollection('portrait', 'public');
            $personne->update(['photo' => null]);

            return;
        }
        if ($request->has('photo') && $personne->photo !== $ancienneUrl) {
            $personne->clearMediaCollection('portrait');
        }
    }
}
