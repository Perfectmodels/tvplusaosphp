<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VoteSondageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'candidat_id' => ['required', 'integer', 'exists:sondage_candidats,id'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'candidat_id.required' => 'Sélectionnez un débatteur.',
            'candidat_id.exists' => 'Ce candidat n’est pas disponible.',
        ];
    }
}
