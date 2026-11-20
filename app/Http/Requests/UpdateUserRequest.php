<?php

namespace App\Http\Requests;

use App\Http\Controllers\UsersController;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && $this->user()->can('update', $this->route('user'));
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $canAssign = $this->user()?->can('assignRoles', $this->route('user')) ?? false;
            $roles = $this->input('roles');
            $role = $this->input('role');

            if ($canAssign && is_array($roles) && (! is_string($role) || ! in_array($role, $roles, true))) {
                $validator->errors()->add('roles', 'Le rôle principal doit aussi être coché dans les fonctions attribuées.');
            }
        }];
    }

    public function rules(): array
    {
        $userId = (int) $this->route('user')->id;
        $canAssign = $this->user()?->can('assignRoles', $this->route('user')) ?? false;
        $assignable = array_keys(UsersController::rolesAssignableTo($this->user()));

        $roleRules = $canAssign
            ? ['required', 'string', Rule::in($assignable)]
            : ['prohibited'];
        $rolesRules = $canAssign
            ? ['sometimes', 'array', 'min:1', 'max:17']
            : ['prohibited'];

        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => $roleRules,
            'roles' => $rolesRules,
            'roles.*' => ['required', 'string', 'distinct', Rule::in($assignable)],
            'department' => ['nullable', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
