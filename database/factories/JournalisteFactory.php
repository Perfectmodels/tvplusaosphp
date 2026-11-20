<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CiviliteEnum;
use App\Enums\FonctionJournalisteEnum;
use App\Enums\PoleRubriqueEnum;
use App\Enums\StatutJournalisteEnum;
use App\Models\Journaliste;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Journaliste>
 */
class JournalisteFactory extends Factory
{
    protected $model = Journaliste::class;

    /**
     * Données réalistes Gabon / Afrique Centrale
     */
    private const PRENOMS_H = ['Christian','Jean','Marc','Laurent','Pierre','Michel','Daniel','Guy','Eric','Patrice','Alain','Didier','Arnaud','Fabrice','Jacques'];
    private const PRENOMS_F = ['Chantal','Marie','Sylvie','Aurelie','Camille','Sandrine','Christelle','Nathalie','Sophie','Laurence','Vanessa','Audrey','Estelle','Celeste','Fatou'];
    private const NOMS = ['Ekogha','Mve','Nze','Ondo','Moussavou','Akoga','Nguema','Biyoghe','Nzamba','Okawe','Mba','Andome','Ntoutoume','Mboumba','Ela','Mintsa','Mbaiguinam'];
    private const VILLES_NAISSANCE_GABON = ['Libreville','Port-Gentil','Franceville','Oyem','Moanda','Lambaréné','Mouila','Tchibanga','Koulamoutou','Lastoursville'];

    /**
     * Spécialités tag par Pôle (pour realistic JSON specialites[])
     */
    private const SPECIALITES_PAR_POLE = [
        'politique' => ['Gouvernement','Sénat','Assemblée Nationale','Élections','Géopolitique','CEEAC','Partis politiques'],
        'economie'  => ['Budget','Finance','Banque Centrale','BEAC','Mines','Pétrole','Forêts','Investissements','UE','Coopération'],
        'justice'   => ['Code pénal','Cour constitutionnelle','Droit des affaires','CNDH','Procédures','Lutte contre la corruption'],
        'sante'     => ['Santé publique','Épidémiologie','OMS','Hôpitaux','Médicaments','Maternité'],
        'sport'     => ['Football','Basketball','Athlétisme','Jeux Africains','CAN','Fédéral FEGUIFOOT','Olympiades'],
        'culture'   => ['Cinéma','Musique','Traditions','Festival','Bwiti','Métier d\'art','Patrimoine UNESCO'],
        'tech'      => ['Télécoms','GABON TELECOM','Cyber-sécurité','5G','Data','IA','Inclusion numérique'],
        'international' => ['SADC','CEEAC','Union Africaine','ONU','Relations bilatérales','France Gabon','USA','Chine'],
        'societe'   => ['Education','Jeunesse','Femmes','Droits humains','ONG','Social'],
        'environnement' => ['Climat','Parcs nationaux','Loppé','Carbone bleu','Déforestation','COP'],
    ];

    public function definition(): array
    {
        $sexe = fake()->randomElement(['H','F']);
        $prenom = fake()->randomElement(
            $sexe === 'H' ? self::PRENOMS_H : self::PRENOMS_F
        );
        $nom = fake()->randomElement(self::NOMS);
        $pole = PoleRubriqueEnum::cases()[fake()->numberBetween(0, count(PoleRubriqueEnum::cases()) - 1)];
        $fonction = fake()->randomElement([
            FonctionJournalisteEnum::Journaliste,
            FonctionJournalisteEnum::Journaliste,
            FonctionJournalisteEnum::JRI,
            FonctionJournalisteEnum::Redacteur,
            FonctionJournalisteEnum::ChefRubrique,
            FonctionJournalisteEnum::Pigiste,
            FonctionJournalisteEnum::Stagiaire,
        ]);
        $statut = fake()->randomElement([
            StatutJournalisteEnum::Actif,
            StatutJournalisteEnum::Actif,
            StatutJournalisteEnum::Actif,
            StatutJournalisteEnum::Mission,
            StatutJournalisteEnum::Conge,
            StatutJournalisteEnum::Inactif,
        ]);

        $specialitesPool = self::SPECIALITES_PAR_POLE[$pole->value] ?? ['Actualités générales'];
        $nbSpec = fake()->numberBetween(1, 3);
        $specialites = [];
        for ($i = 0; $i < $nbSpec; $i++) {
            $p = fake()->randomElement($specialitesPool);
            if (!in_array($p, $specialites, true)) {
                $specialites[] = $p;
            }
        }

        // Matricule auto format: J-AAAA-NNN (gestion unique dans afterCreating si collision)
        $annee = (string)fake()->numberBetween(2018, 2026);
        $matricule = sprintf('J-%s-%04d', $annee, fake()->unique()->numberBetween(1, 3000));

        return [
            'civilite'       => $sexe === 'H'
                ? (fake()->boolean(10) ? CiviliteEnum::Monsieur : fake()->randomElement([CiviliteEnum::Docteur, CiviliteEnum::Professeur, CiviliteEnum::Monsieur]))
                : (fake()->boolean(15) ? CiviliteEnum::Mademoiselle : CiviliteEnum::Madame),
            'matricule'      => $matricule,
            'nom'            => $nom,
            'prenom'         => $prenom,
            'first_name'     => $prenom,
            'last_name'      => $nom,
            'date_naissance' => fake()->dateTimeBetween('-55 years', '-22 years')->format('Y-m-d'),
            'lieu_naissance' => fake()->randomElement(self::VILLES_NAISSANCE_GABON),
            'fonction'       => $fonction,
            'pole'           => $pole,
            'statut'         => $statut,
            'date_embauche'  => $statut === StatutJournalisteEnum::Inactif || $fonction === FonctionJournalisteEnum::Stagiaire || $fonction === FonctionJournalisteEnum::Pigiste
                ? null
                : fake()->dateTimeBetween('-15 years', '-1 months')->format('Y-m-d'),
            'specialites'    => $specialites,
            'email'          => sprintf(
                '%s.%s.%s@%s',
                mb_strtolower($prenom),
                mb_strtolower($nom),
                mb_strtolower(fake()->unique()->bothify('?##')),
                fake()->randomElement(['tvplusafrique.ga','tvplusos.tv','redaction-tvplus.ga','media.tvplus.gabon','tvplus-presse.ga','tvplus-news.gabon','redaction-plus.tv'])
            ),
            'telephone'      => '+241 ' . fake()->numerify('07 ## ## ## ##'),
            'phone'          => '+241 ' . fake()->numerify('07 ## ## ## ##'),
            'adresse'        => fake()->boolean()
                ? fake()->randomElement(self::VILLES_NAISSANCE_GABON) . ', ' . fake()->streetAddress()
                : null,
            'notes_admin'    => fake()->boolean(30)
                ? fake()->sentence(10)
                : null,
            'is_active'      => in_array($statut, [StatutJournalisteEnum::Inactif, StatutJournalisteEnum::Demissionne, StatutJournalisteEnum::Suspendu], true) ? false : true,
            'is_presentateur' => false,
        ];
    }

    /**
     * État "Journaliste promu Présentateur TV+AOS"
     */
    public function presentateur(): static
    {
        return $this->state(function (array $_attrs): array {
            return [
                'is_presentateur'              => true,
                'date_promotion_presentateur'  => fake()->dateTimeBetween('-5 years', '-1 month'),
                'fonction'                     => fake()->randomElement([FonctionJournalisteEnum::ChefRubrique, FonctionJournalisteEnum::Journaliste, FonctionJournalisteEnum::Redacteur]),
            ];
        });
    }

    /**
     * État Chef de rubrique
     */
    public function chefRubrique(): static
    {
        return $this->state(fn () => [
            'fonction' => FonctionJournalisteEnum::ChefRubrique,
        ]);
    }
}
