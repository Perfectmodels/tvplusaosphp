<?php

namespace App\Http\Requests;

use App\Http\Controllers\UsersController;
use App\Models\User;
use App\Models\UserAccountRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApproveAccountRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var UserAccountRequest $accountRequest */
        $accountRequest = $this->route('user_account_request');

        return $this->user()?->can('approve', $accountRequest) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->email)) {
            $this->merge(['email' => mb_strtolower(trim($this->email))]);
        }
    }

    public function rules(): array
    {
        $assignable = array_keys(UsersController::rolesAssignableTo($this->user()));
        /** @var UserAccountRequest $accountRequest */
        $accountRequest = $this->route('user_account_request');

        return [
            'prenom' => ['required', 'string', 'min:2', 'max:80'],
            'nom' => ['required', 'string', 'min:2', 'max:100'],
            'email' => [
                'required', 'string', 'email:rfc', 'max:200',
                Rule::unique('users', 'email'),
                Rule::unique('user_account_requests', 'email')->ignore($accountRequest?->id)->where(fn ($q) => $q->where('status', UserAccountRequest::STATUS_PENDING)),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'department' => ['nullable', 'string', 'max:120'],
            'fonction' => ['required', 'string', 'min:2', 'max:120'],
            'journaliste_id' => [
                'nullable', 'integer',
                Rule::exists('journalistes', 'id')->where(fn ($q) => $q->where('is_active', true)
                    ->where(function ($qq) use ($accountRequest) {
                        $qq->whereNull('user_id');
                        if ($accountRequest?->journaliste_id) {
                            $qq->orWhere('id', $accountRequest->journaliste_id);
                        }
                    })),
            ],
            'role' => ['required', 'string', Rule::in($assignable)],
            'roles' => ['sometimes', 'array', 'max:17'],
            'roles.*' => ['required', 'string', 'distinct', Rule::in($assignable)],
            'is_active' => ['nullable', 'boolean'],
            'temp_password' => ['nullable', 'string', 'min:10', 'max:128'],
        ];
    }
}
