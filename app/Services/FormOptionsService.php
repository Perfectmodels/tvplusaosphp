<?php

namespace App\Services;

use App\Enums\FonctionJournalisteEnum;
use App\Enums\PoleRubriqueEnum;
use App\Models\Intervenant;
use App\Models\Journaliste;
use Illuminate\Support\Collection;

class FormOptionsService
{
    public function intervenants(): array
    {
        $profils = Intervenant::query()->latest('updated_at')->limit(300)
            ->get(['fonction', 'organisme', 'poste', 'pays_residence', 'ville_residence', 'domaines']);

        return [
            'fonction' => $this->valeurs($profils->pluck('fonction')->merge(array_map(fn ($fonction) => $fonction->label(), FonctionJournalisteEnum::cases()))),
            'organisme' => $this->valeurs($profils->pluck('organisme')),
            'poste' => $this->valeurs($profils->pluck('poste')),
            'pays_residence' => $this->valeurs($profils->pluck('pays_residence')->merge(array_values($this->pays()))),
            'ville_residence' => $this->valeurs($profils->pluck('ville_residence')),
            'domaines' => $this->valeurs($profils->pluck('domaines')->flatten()->merge(array_values(PoleRubriqueEnum::selectOptions()))),
        ];
    }

    public function specialites(): array
    {
        return $this->valeurs(Journaliste::query()->latest('updated_at')->limit(300)->pluck('specialites')
            ->flatten()->merge(array_values(PoleRubriqueEnum::selectOptions())));
    }

    public function pays(): array
    {
        $pays = ['GA' => 'Gabon'];
        if (class_exists(\ResourceBundle::class)) {
            $regions = \ResourceBundle::create('fr', 'ICUDATA-region')?->get('Countries');
            foreach ($regions ?? [] as $code => $nom) {
                if (preg_match('/^[A-Z]{2}$/', $code) && ! in_array($code, ['EU', 'EZ', 'QO', 'UN', 'XA', 'XB', 'ZZ'], true)) {
                    $pays[$code] = (string) $nom;
                }
            }
        }
        asort($pays, SORT_NATURAL | SORT_FLAG_CASE);

        return $pays;
    }

    private function valeurs(Collection $valeurs): array
    {
        return $valeurs->filter(fn ($valeur) => is_string($valeur) && trim($valeur) !== '')
            ->map(fn (string $valeur) => trim($valeur))
            ->unique(fn (string $valeur) => mb_strtolower($valeur))
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
    }
}
