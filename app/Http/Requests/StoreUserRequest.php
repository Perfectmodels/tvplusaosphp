<?php

namespace App\Http\Requests;

use App\Http\Controllers\UsersController;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && $this->user()->can('create', User::class);
    }

    public function rules(): array
    {
        $assignable = array_keys(UsersController::rolesAssignableTo($this->user()));

        return [
            'mode' => ['required', 'string', Rule::in(['from_journaliste', 'manual'])],
            'journaliste_id' => ['required_if:mode,from_journaliste', 'nullable', 'integer',
                Rule::exists('journalistes', 'id')->where(fn ($q) => $q->whereNull('user_id')->where('is_active', true)
                    ->whereNotIn('id', User::query()->whereNotNull('journaliste_id')->select('journaliste_id')))],
            'role' => ['required', 'string', Rule::in($assignable)],
            'roles' => ['sometimes', 'array', 'max:17'],
            'roles.*' => ['required', 'string', 'distinct', Rule::in($assignable)],
            'additional_roles' => ['sometimes', 'array', 'max:16'],
            'additional_roles.*' => ['required', 'string', 'distinct', Rule::in($assignable)],
            // Mode manual fields
            'civilite' => ['nullable', 'string', Rule::in(['Monsieur', 'Madame', 'Mademoiselle'])],
            'prenom' => ['required_if:mode,manual', 'nullable', 'string', 'min:2', 'max:80'],
            'nom' => ['required_if:mode,manual', 'nullable', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'string', 'email:rfc', 'max:200', 'unique:users,email',
                Rule::when($this->input('mode') === 'from_journaliste', [Rule::unique('journalistes', 'email')->ignore($this->integer('journaliste_id') ?: null)])],
            'phone' => ['nullable', 'string', 'max:30'],
            'department' => ['nullable', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
            'temp_password' => ['nullable', 'string', 'min:10', 'max:128'],
        ];
    }
}
