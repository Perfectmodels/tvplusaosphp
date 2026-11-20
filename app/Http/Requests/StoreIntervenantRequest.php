<?php

namespace App\Http\Requests;

use App\Enums\CiviliteEnum;
use App\Enums\StatutDispoIntervenantEnum;
use App\Enums\TypeIntervenantEnum;
use App\Models\Intervenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreIntervenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Aligné sur IntervenantPolicy (rôles) — les permissions Spatie 'create intervenant'/'admin full' n'existent pas.
        return $this->user()?->can('create', Intervenant::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'civilite' => ['nullable', 'string', 'max:8', new Enum(CiviliteEnum::class)],
            'nom' => ['required', 'string', 'max:100'],
            'prenom' => ['required', 'string', 'max:100'],
            'date_naissance' => ['nullable', 'date', 'before_or_equal:today'],
            'lieu_naissance' => ['nullable', 'string', 'max:120'],
            'piece_identite' => ['nullable', 'string', 'max:120'],
            'fonction' => ['nullable', 'string', 'max:160'],
            'organisme' => ['nullable', 'string', 'max:200'],
            'poste' => ['nullable', 'string', 'max:160'],
            'type' => ['required', 'string', 'max:48', new Enum(TypeIntervenantEnum::class)],
            'domaines' => ['nullable', 'array'],
            'domaines.*' => ['string', 'max:200'],
            'langues' => ['nullable', 'array'],
            'langues.*' => ['string', 'max:10'],
            'ville_residence' => ['nullable', 'string', 'max:120'],
            'pays_residence' => ['nullable', 'string', 'max:80'],
            'telephone_1' => ['nullable', 'string', 'max:30'],
            'telephone_2' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'unique:intervenants,email'],
            'adresse_physique' => ['nullable', 'string', 'max:255'],
            'contact_urgence' => ['nullable', 'array'],
            'presentation_courte' => ['nullable', 'string', 'max:1000'],
            'biographie_longue' => ['nullable', 'string'],
            'sujets_sensibles' => ['nullable', 'string'],
            'taille_habillement' => ['nullable', 'string', 'max:40'],
            'regime_alimentaire' => ['nullable', 'string', 'max:80'],
            'observations_admin' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'disponibilite' => ['nullable', 'string', 'max:32', new Enum(StatutDispoIntervenantEnum::class)],
            'photo' => ['nullable', 'url:http,https', 'max:500'],
            'photo_file' => ['nullable', 'image', 'dimensions:min_width=1,min_height=1', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'prohibited_if:remove_photo,1'],
            'remove_photo' => ['sometimes', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $values = ['is_active' => $this->boolean('is_active', true)];
        if ($this->has('email') && is_string($this->input('email'))) {
            $values['email'] = mb_strtolower(trim($this->input('email')));
        }
        if ($this->boolean('_person_form')) {
            $values['domaines'] = $this->input('domaines', []);
            $values['langues'] = $this->input('langues', []);
        }
        $this->merge($values);
    }
}
