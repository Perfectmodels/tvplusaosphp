<?php

namespace App\Http\Requests;

use App\Models\UserAccountRequest;
use Illuminate\Foundation\Http\FormRequest;

class RejectAccountRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var UserAccountRequest $accountRequest */
        $accountRequest = $this->route('user_account_request');

        return $this->user()?->can('reject', $accountRequest) ?? false;
    }

    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }
}
