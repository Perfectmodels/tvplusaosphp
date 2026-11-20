<?php

namespace App\Http\Requests;

use App\Enums\RolePresentateurEmissionEnum;
use App\Enums\StatutEmissionEnum;
use App\Enums\TypeEmissionEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

abstract class EmissionFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:255'],
            'sous_titre' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'string', new Enum(TypeEmissionEnum::class)],
            'categorie' => ['nullable', 'string', 'max:50'],
            'cible_audience' => ['nullable', 'string', 'max:50'],
            'langues' => ['nullable', 'array'],
            'langues.*' => ['string', 'max:40', 'distinct'],
            'heure_debut' => ['required', 'date_format:H:i'],
            'heure_fin' => ['required', 'date_format:H:i', 'different:heure_debut'],
            'duree_minutes' => ['required', 'integer', 'min:1', 'max:480'],
            'fuseau_horaire' => ['nullable', 'timezone'],
            'jours_diffusion' => ['nullable', 'array'],
            'jours_diffusion.*' => ['string', 'distinct', Rule::in(['lun', 'mar', 'mer', 'jeu', 'ven', 'sam', 'dim'])],
            'date_lancement' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date', Rule::when($this->filled('date_lancement'), 'after_or_equal:date_lancement')],
            'format_image' => ['nullable', 'string', 'max:20'],
            'qualite' => ['nullable', 'string', 'max:20'],
            'couleur_habillage' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}([0-9a-fA-F]{2})?$/'],
            'police_titres' => ['nullable', 'string', 'max:60'],
            'statut' => ['nullable', 'string', new Enum(StatutEmissionEnum::class)],
            'chef_edition_id' => ['nullable', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
            'is_brouillon' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'presentateurs' => ['nullable', 'array', 'max:20'],
            'presentateurs.*.journaliste_id' => ['required', 'integer', 'exists:journalistes,id', 'distinct'],
            'presentateurs.*.role' => ['nullable', 'string', new Enum(RolePresentateurEmissionEnum::class)],
            'presentateurs.*.statut_affectation' => ['nullable', Rule::in(['Confirme', 'En_attente', 'Refuse'])],
            'presentateurs.*.ordre' => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $emission = $this->route('emission');
        $values = [
            'is_brouillon' => $this->boolean('is_brouillon', $emission?->is_brouillon ?? true),
            'is_active' => $this->boolean('is_active', $emission?->is_active ?? true),
        ];
        $statut = is_string($this->input('statut')) ? StatutEmissionEnum::tryFrom($this->input('statut')) : null;
        if ($statut !== null) {
            $values['is_brouillon'] = $statut === StatutEmissionEnum::Brouillon;
        }
        $debut = $this->input('heure_debut');
        $fin = $this->input('heure_fin');
        $validTime = static fn ($value): bool => is_string($value) && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value) === 1;
        if ($validTime($debut) && $validTime($fin)) {
            $minutes = static fn (string $value): int => (int) substr($value, 0, 2) * 60 + (int) substr($value, 3, 2);
            $values['duree_minutes'] = ($minutes($fin) - $minutes($debut) + 1440) % 1440;
        }
        if ($this->boolean('_emission_form')) {
            foreach (['langues', 'jours_diffusion', 'presentateurs'] as $field) {
                $values[$field] = $this->input($field, []);
            }
        }
        $this->merge($values);
    }

    public function messages(): array
    {
        return [
            'heure_fin.different' => 'L’heure de fin doit être différente de l’heure de début.',
            'duree_minutes.min' => 'La durée doit être comprise entre 1 et 480 minutes. Vérifiez les horaires.',
            'duree_minutes.max' => 'La durée doit être comprise entre 1 et 480 minutes. Vérifiez les horaires.',
            'jours_diffusion.*.in' => 'Choisissez les jours de diffusion dans la liste proposée.',
            'fuseau_horaire.timezone' => 'Choisissez un fuseau horaire valide.',
            'couleur_habillage.regex' => 'Choisissez une couleur valide dans la palette.',
            'presentateurs.*.journaliste_id.distinct' => 'Un présentateur ne peut être ajouté qu’une seule fois.',
        ];
    }
}
