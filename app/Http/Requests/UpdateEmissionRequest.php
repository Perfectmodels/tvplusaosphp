<?php

namespace App\Http\Requests;

use App\Models\Emission;

class UpdateEmissionRequest extends EmissionFormRequest
{
    public function authorize(): bool
    {
        // Aligné sur EmissionPolicy (rôles) — les permissions Spatie 'update emission'/'admin full' n'existent pas.
        return $this->user()?->can('update', $this->route('emission')) ?? false;
    }
}
