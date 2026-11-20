<?php

namespace App\Http\Requests;

use App\Models\Emission;

class StoreEmissionRequest extends EmissionFormRequest
{
    public function authorize(): bool
    {
        // Aligné sur EmissionPolicy (rôles) — les permissions Spatie 'create emission'/'admin full' n'existent pas.
        return $this->user()?->can('create', Emission::class) ?? false;
    }
}
