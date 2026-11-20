<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Emission;
use App\Models\Sondage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreSondageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $emission = $this->route('emission');

        return $emission instanceof Emission
            && Gate::allows('create', [Sondage::class, $emission]);
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'min:5', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'question.required' => 'Écrivez la question du sondage.',
            'question.min' => 'La question doit contenir au moins 5 caractères.',
            'question.max' => 'La question ne peut pas dépasser 255 caractères.',
        ];
    }
}
