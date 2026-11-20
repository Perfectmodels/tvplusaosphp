<?php

namespace App\Http\Requests;

use App\Enums\CiviliteEnum;
use App\Enums\FonctionJournalisteEnum;
use App\Enums\PoleRubriqueEnum;
use App\Enums\StatutJournalisteEnum;
use App\Models\Journaliste;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreJournalisteRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Aligné sur JournalistePolicy (rôles) — les permissions Spatie 'create journaliste'/'admin full' n'existent pas.
        return ($this->user()?->can('create', Journaliste::class) ?? false)
            && (! $this->filled('user_id') || $this->user()->can('create', User::class));
    }

    public function rules(): array
    {
        return [
            'matricule' => ['nullable', 'string', 'max:32', 'unique:journalistes,matricule'],
            'civilite' => ['nullable', 'string', 'max:8', new Enum(CiviliteEnum::class)],
            'nom' => ['required', 'string', 'max:100'],
            'prenom' => ['required', 'string', 'max:100'],
            'date_naissance' => ['nullable', 'date', 'before_or_equal:today'],
            'lieu_naissance' => ['nullable', 'string', 'max:120'],
            'fonction' => ['nullable', 'string', 'max:64', new Enum(FonctionJournalisteEnum::class)],
            'pole' => ['nullable', 'string', 'max:48', new Enum(PoleRubriqueEnum::class)],
            'statut' => ['nullable', 'string', 'max:32', new Enum(StatutJournalisteEnum::class)],
            'date_embauche' => ['nullable', 'date'],
            'specialites' => ['nullable', 'array'],
            'specialites.*' => ['string', 'max:120'],
            'email' => ['required', 'email', 'max:200', 'unique:journalistes,email', Rule::unique('users', 'email')->ignore($this->integer('user_id') ?: null)],
            'telephone' => ['nullable', 'string', 'max:30'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'notes_admin' => ['nullable', 'string'],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($q) => $q
                ->whereNull('journaliste_id')
                ->whereNotIn('id', Journaliste::query()->whereNotNull('user_id')->select('user_id')))],
            'photo' => ['nullable', 'url:http,https', 'max:500'],
            'photo_file' => ['nullable', 'image', 'dimensions:min_width=1,min_height=1', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'prohibited_if:remove_photo,1'],
            'remove_photo' => ['sometimes', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'is_presentateur' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $matricule = trim((string) $this->input('matricule'));

        if ($this->has('specialites_text') && is_string($this->input('specialites_text'))) {
            $this->merge(['specialites' => array_values(array_unique(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $this->input('specialites_text'))))))]);
        }

        $this->merge([
            'matricule' => $matricule !== '' ? $matricule : null,
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'is_active' => $this->boolean('is_active', true),
            'is_presentateur' => $this->boolean('is_presentateur', false),
        ]);
    }
}
