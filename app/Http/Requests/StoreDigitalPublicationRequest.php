<?php

namespace App\Http\Requests;

use App\Models\DigitalPublication;
use App\Models\WorkspaceDossier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDigitalPublicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', DigitalPublication::class);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:50000'],
            'platform' => ['required', Rule::in(array_keys(config('workspaces.digital_platforms')))],
            'dossier_id' => ['nullable', 'integer', 'exists:workspace_dossiers,id'],
            'emission_id' => ['nullable', 'integer', 'exists:emissions,id'],
        ];
    }

    public function sourceAttributes(): array
    {
        $id = $this->validated('dossier_id');
        $publication = $this->route('publication');
        $current = $publication?->sharedDossier();
        if ($current && $id && (int) $id !== $current->id) {
            abort(422, 'Le dossier source ne peut pas être remplacé.');
        }
        $dossier = $id ? WorkspaceDossier::findOrFail($id) : $current;
        if (! $dossier) {
            return [];
        }
        $this->user()->can('view', $dossier) || abort(403);
        abort_if($dossier->merged_into_id || ! $dossier->source_available, 422, 'Le sujet source n’est plus disponible.');

        return ['editorial_story_id' => $dossier->editorial_story_id, 'source_sequence_id' => $dossier->sequence_id];
    }
}
