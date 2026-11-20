<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkDeleteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'parent_id' => [Rule::requiredIf(in_array($this->route('resource'), ['sujets-redaction', 'sequences-emission', 'remixes-veille', 'presences-conference'], true)), 'nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Sélectionnez au moins un élément.',
            'ids.max' => 'Sélectionnez au maximum 100 éléments par opération.',
            'ids.*.distinct' => 'Un élément ne peut être sélectionné qu’une seule fois.',
            'parent_id.required' => 'Le contexte de la liste est manquant. Rechargez la page.',
        ];
    }
}
