<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Models\UserAccountRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccountRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => is_string($this->email) ? mb_strtolower(trim($this->email)) : $this->email,
            'charter_accepted' => $this->boolean('charter_accepted'),
        ]);
    }

    public function rules(): array
    {
        return [
            'civilite' => ['nullable', 'string', Rule::in(['Monsieur', 'Madame', 'Mademoiselle'])],
            'prenom' => ['required', 'string', 'min:2', 'max:80'],
            'nom' => ['required', 'string', 'min:2', 'max:100'],
            'email' => [
                'required', 'string', 'email:rfc', 'max:200',
                Rule::unique('users', 'email'),
                Rule::unique('user_account_requests', 'email')->where(fn ($q) => $q->where('status', UserAccountRequest::STATUS_PENDING)),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'department' => ['nullable', 'string', 'max:120'],
            'fonction' => ['required', 'string', 'min:2', 'max:120'],
            'journaliste_id' => [
                'nullable', 'integer',
                Rule::exists('journalistes', 'id')->where(fn ($q) => $q->where('is_active', true)->whereNull('user_id')),
            ],
            'matricule' => ['nullable', 'string', 'max:50'],
            'requested_access_type' => ['nullable', 'string', 'max:120'],
            'motivation' => ['nullable', 'string', 'max:2000'],
            'charter_accepted' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Cette adresse e-mail est déjà utilisée ou fait l’objet d’une demande en attente.',
            'charter_accepted.accepted' => 'Vous devez accepter la charte d’utilisation pour envoyer la demande.',
        ];
    }
}
