<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AssistantMessageRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:2000'],
            'history' => ['sometimes', 'array', 'max:6'],
            'history.*' => ['required', 'array:role,content'],
            'history.*.role' => ['required', 'string', 'in:user,assistant'],
            'history.*.content' => ['required', 'string', 'max:8000'],
        ];
    }

    public function messages(): array
    {
        return [
            'message.required' => 'Écrivez une question pour démarrer la discussion.',
            'message.max' => 'Votre question doit contenir au maximum 2 000 caractères.',
            'history.max' => 'La conversation transmise est trop longue. Démarrez une nouvelle discussion.',
            'history.*.role.in' => 'Le rôle de ce message est invalide.',
        ];
    }
}
