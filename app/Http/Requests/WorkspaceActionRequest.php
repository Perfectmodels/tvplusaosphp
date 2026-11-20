<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WorkspaceActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ability = match ($this->route()->getName()) {
            'workspace.comments.store' => 'comment', 'workspace.tasks.store' => 'createTask',
            'workspace.tasks.update' => 'changeTask', 'workspace.files.store' => 'upload',
            default => 'manageMembers',
        };

        return $this->user()->can($ability, $this->route('dossier'));
    }

    public function rules(): array
    {
        return match ($this->route()->getName()) {
            'workspace.comments.store' => ['body' => ['required', 'string', 'max:10000']],
            'workspace.tasks.store' => ['title' => ['required', 'string', 'max:255'], 'instructions' => ['nullable', 'string', 'max:10000'], 'type' => ['required', Rule::in(array_keys(config('workspaces.task_types')))], 'assigned_to' => ['required', 'integer', 'exists:users,id'], 'due_at' => ['nullable', 'date_format:Y-m-d\TH:i']],
            'workspace.tasks.update' => ['status' => ['required', Rule::in(array_keys(config('workspaces.task_statuses')))], 'blocked_reason' => ['required_if:status,blocked', 'nullable', 'string', 'max:5000'], 'assigned_to' => ['sometimes', 'required', 'integer', 'exists:users,id']],
            'workspace.files.store' => ['file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,ppt,pptx,txt,mp3,mp4,mov', 'extensions:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,ppt,pptx,txt,mp3,mp4,mov']],
            'workspace.members.store' => ['user_id' => ['required', 'integer', 'exists:users,id']],
            default => [],
        };
    }
}
