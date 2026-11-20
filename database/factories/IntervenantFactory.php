<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CiviliteEnum;
use App\Enums\LangueEnum;
use App\Enums\StatutDispoIntervenantEnum;
use App\Enums\TypeIntervenantEnum;
use App\Models\Intervenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Intervenant>
 */
class IntervenantFactory extends Factory
{
    protected $model = Intervenant::class;

    private const PRENOMS_H = ['Alain','Brice','Cedric','Dany','Elie','Fernand','Gael','Herve','Igor','Jonas','Kevin','Loic','Marcel','Norbert','Paco','Rufin','Serge','Thierry','Urbain','Vianney'];
    private const PRENOMS_F = ['Aminata','Bertille','Carole','Daniele','Eulalie','Fanta','Grace','Honorine','Irène','Jeanne','Kenza','Lydie','Mireille','Nadine','Ornella','Patricia','Reine','Sylviane','Thérèse','Yolande'];
    private const NOMS = ['Nzeng','Bourobou','Mabicka','Ndong','Mouele','Makaya','Koukou','Madela','Moussirou','Nguema','Oyouna','Pemba','Retho','Simbho','Tsiba','Wora','Yembi','Bilou','Chirambo','Dikobo'];

    // Spécialisation par type d'intervenant (SPC SPEC §3.4 DOMAINES)
    private const DOMAINES_PAR_TYPE = [
        TypeIntervenantEnum::Politicien->value => ['Gouvernement','Sénat','Assemblée','PDG','RPD','Opposition','Politique nationale','CEEAC','Réformes institutionnelles','Décentralisation'],
        TypeIntervenantEnum::Expert->value     => ['Macro-économie','Droit public','Droit privé','Santé publique','Mines','Pétrole','Géopolitique','Cyber-sécurité','Climat','Éducation','Banking','AGOA','ZLECAf'],
        TypeIntervenantEnum::SocieteCivil->value => ['ONG','Droits de l\'Homme','Femmes','Jeunesse','Protection de l\'enfance','Environnement','Transparence','Lutte anti-corruption','Syndicats','Mouvements citoyens'],
        TypeIntervenantEnum::Artiste->value    => ['Musique','Cinéma gabonais','Bwiti','Culture traditionnelle','Festival','Art contemporain','Mode','Design','Chanson'],
        TypeIntervenantEnum::Sportif->value    => ['Football FEGUIFOOT','Championnat national','Olympiades','Jeux Africains','Basketball','Athlétisme','Golf','Formule 1'],
        TypeIntervenantEnum::Animateur->value  => ['Animation','Jeux','Humour','Téléréalité','Musique live','Talk-show'],
        TypeIntervenantEnum::Correspondant->value => ['Terrain Libreville','Paris','Bruxelles','Johannesburg','Lagos','Nairobi','Yaoundé','Kinshasa','Reportage','Duplex'],
        TypeIntervenantEnum::Invite->value     => ['Invité plateau','Témoignage','Interview spéciale','Portrait','Actualité'],
        TypeIntervenantEnum::Autre->value      => ['Invité surprise','Humoriste','Chef cuisinier','Influenceur','Youtubeur','Influence digitale'],
    ];

    // Organismes par type
    private const ORGANISMES_PAR_TYPE = [
        TypeIntervenantEnum::Politicien->value => ['Assemblée nationale','Sénat du Gabon','Ministère de l\'Intérieur','Ministère des Finances','Ministère des Mines','Ministère de la Santé','Palais du bord de mer','Opposition Démocrate','ANR','Conseil constitutionnel'],
        TypeIntervenantEnum::Expert->value     => ['Université Omar Bongo','École Nationale d\'Administration','IMF','Banque Mondiale','BDEAC','CAISTAB','IRD Gabon','Société Civile Internationale','Sogea Satom','GSEZ','BeTech','Agence Nationale du Numérique'],
        TypeIntervenantEnum::SocieteCivil->value => ['Fondation Sylvia Bongo Ondimba','Jeunesse Gabonaise','WILDAF Gabon','SOS Villages d\'Enfants','Gabon Environnement','BirdLife Gabon','ACAT Gabon','Syndicat des journalistes','Ligue des droits'],
        TypeIntervenantEnum::Artiste->value    => ['Institut National des Arts','FEMUA','Festival International du Film de Libreville','Studio Moanda','Na Na Disco','Radio Soleil','Atypik Studio','Maison de la Culture'],
        TypeIntervenantEnum::Sportif->value    => ['Fédération Gabonaise de Football','Fédération de Basketball','CAN 2027','Comité National Olympique','CAF','Mouloudia FC','Cercle Mbéri Sportif','US Bitam'],
        TypeIntervenantEnum::Animateur->value  => ['RTG1','Radio Nostalgie','Radio GABON 1','Trace TV','TV+ Production','Mboa TV'],
        TypeIntervenantEnum::Correspondant->value => ['Correspondance Libreville','Correspondance Paris','RTBF','AFP Gabon','RFI Libreville','BBC Afrique','France 24'],
        TypeIntervenantEnum::Invite->value     => ['Citoyen','Porte parole','Témoin','Association','Comité','Coordination'],
        TypeIntervenantEnum::Autre->value      => ['Indépendant','Freelance','Influenceur Gabon','YouTube Gabon','Tiktok Gabon'],
    ];

    private const VILLES_GABON = ['Libreville','Port-Gentil','Franceville','Oyem','Moanda','Lambaréné','Mouila','Tchibanga','Koulamoutou','Lastoursville','Makokou','Booué','Ndjolé'];
    private const PAYS = ['Gabon','Cameroun','Congo','RD Congo','Côte d\'Ivoire','France','Belgique','Afrique du Sud','Nigeria','Kenya','Togo','Bénin'];

    public function definition(): array
    {
        $sexe = fake()->randomElement(['H','F']);
        $type = TypeIntervenantEnum::cases()[fake()->numberBetween(0, count(TypeIntervenantEnum::cases()) - 1)];
        $civilite = match (true) {
            $type === TypeIntervenantEnum::Politicien && fake()->boolean(25) => CiviliteEnum::Maitre,
            $type === TypeIntervenantEnum::Expert     && fake()->boolean(40) => CiviliteEnum::Docteur,
            $type === TypeIntervenantEnum::Expert     && fake()->boolean(15) => CiviliteEnum::Professeur,
            $sexe === 'H' => CiviliteEnum::Monsieur,
            fake()->boolean(15) => CiviliteEnum::Mademoiselle,
            default => CiviliteEnum::Madame,
        };
        $prenom = fake()->randomElement($sexe === 'H' ? self::PRENOMS_H : self::PRENOMS_F);
        $nom    = fake()->randomElement(self::NOMS);

        // Villes/Pays
        $pays = fake()->randomElement(array_fill(0, 50, 'Gabon') + self::PAYS);
        $ville = $pays === 'Gabon'
            ? fake()->randomElement(self::VILLES_GABON)
            : match ($pays) {
                'Cameroun' => fake()->randomElement(['Douala','Yaoundé','Garoua']),
                'Congo'    => fake()->randomElement(['Brazzaville','Pointe-Noire']),
                'RD Congo' => fake()->randomElement(['Kinshasa','Lubumbashi','Goma']),
                'Côte d\'Ivoire' => fake()->randomElement(['Abidjan','Yamoussoukro','Bouaké']),
                'France'   => fake()->randomElement(['Paris','Lyon','Marseille','Bordeaux']),
                default    => fake()->randomElement(['Bruxelles','Lagos','Nairobi','Johannesburg','Lomé','Cotonou']),
            };

        // Domaines JSON 1-4 éléments
        $pool = self::DOMAINES_PAR_TYPE[$type->value] ?? ['Actualité'];
        $domaines = [];
        $nbDomaines = fake()->numberBetween(1, 4);
        for ($i = 0; $i < $nbDomaines; $i++) {
            $p = fake()->randomElement($pool);
            if (!in_array($p, $domaines, true)) {
                $domaines[] = $p;
            }
        }

        // Langues 1-4 (Français quasi-systematique Gabon/francophonie)
        $langues = [LangueEnum::Francais->value];
        $otherLangues = [LangueEnum::Anglais, LangueEnum::Lingala, LangueEnum::Swahili, LangueEnum::Wolof, LangueEnum::Espagnol, LangueEnum::Portugais, LangueEnum::Arabe, LangueEnum::Pidgin, LangueEnum::Chinois];
        shuffle($otherLangues);
        $addLangues = fake()->numberBetween(0, 2);
        for ($i = 0; $i < $addLangues; $i++) {
            $v = $otherLangues[$i]->value;
            if (!in_array($v, $langues, true)) {
                $langues[] = $v;
            }
        }

        $organisme = fake()->randomElement(self::ORGANISMES_PAR_TYPE[$type->value] ?? ['—']);
        $posteFonction = match ($type) {
            TypeIntervenantEnum::Politicien => fake()->randomElement(['Député','Sénateur','Ministre','Conseiller','Président Commission','Porte parole']),
            TypeIntervenantEnum::Expert     => fake()->randomElement(['Professeur associé','Chercheur','Consultant','Directeur','Expert international','Avocat','Notaire','Huissier','Conseiller']),
            TypeIntervenantEnum::SocieteCivil => fake()->randomElement(['Président','Délégué général','Coordonnateur','Porte parole','Chargé de programmes']),
            TypeIntervenantEnum::Artiste    => fake()->randomElement(['Artiste','Chanteur','Réalisateur','Scénariste','Peintre','Designer','Mannequin']),
            TypeIntervenantEnum::Sportif    => fake()->randomElement(['Joueur','Entraîneur','Président club','Arbitre','Commentateur sportif']),
            TypeIntervenantEnum::Animateur  => fake()->randomElement(['Animateur radio','Animateur TV','Comédien','Chroniqueur','DJ','Speaker']),
            TypeIntervenantEnum::Correspondant => fake()->randomElement(['Correspondant','Envoyé spécial','Reporter','Journaliste terrain']),
            TypeIntervenantEnum::Invite     => fake()->randomElement(['Porte parole','Invité','Témoin','Citoyen lambda']),
            default => fake()->randomElement(['Collaborateur','Indépendant','Influenceur']),
        };

        $dispo = fake()->randomElement([
            StatutDispoIntervenantEnum::Disponible,
            StatutDispoIntervenantEnum::Disponible,
            StatutDispoIntervenantEnum::Disponible,
            StatutDispoIntervenantEnum::Occupe,
            StatutDispoIntervenantEnum::Occupe,
            fake()->boolean(3) ? StatutDispoIntervenantEnum::Interdit : StatutDispoIntervenantEnum::Disponible,
        ]);

        $nomEmail = mb_strtolower(trim((string)preg_replace('/[^a-zA-Z0-9]+/', '.', "{$prenom}.{$nom}"), '.'));
        $domainMail = match (true) {
            str_contains($organisme, 'Université') || str_contains($organisme, 'ENA') => 'ugabon.ga',
            str_contains($organisme, 'Fédération') => 'fego.ga',
            str_contains($organisme, 'ONG') || str_contains($organisme, 'Fondation') || str_contains($organisme, 'Ligue') => 'asso-gabon.org',
            str_contains($organisme, 'Ministère') || str_contains($organisme, 'Palais') || str_contains($organisme, 'Conseil') => 'gouvernement.gouv.ga',
            default => fake()->randomElement(['gmail.com','yahoo.fr','consulting.ga','pro-mail.tvplus.ga']),
        };
        $telMobile = match ($pays) {
            'Gabon'        => '+241 ' . fake()->numerify('07 ## ## ## ##'),
            'Cameroun'     => '+237 ' . fake()->numerify('6## ## ## ##'),
            'Congo'        => '+242 ' . fake()->numerify('06 ## ## ## ##'),
            'RD Congo'     => '+243 ' . fake()->numerify('89 ## ### ###'),
            'Côte d\'Ivoire' => '+225 ' . fake()->numerify('07 ## ## ## ##'),
            'France'       => '+33 ' . fake()->numerify('6 ## ## ## ##'),
            default        => '+221 ' . fake()->numerify('77 ## ## ## ##'),
        };
        $telephone2 = fake()->boolean(30)
            ? match ($pays) {
                'Gabon' => '+241 ' . fake()->numerify('01 ## ## ## ##'),
                default => null,
              }
            : null;

        return [
            'civilite'     => $civilite,
            'nom'          => $nom,
            'prenom'       => $prenom,
            'first_name'   => $prenom,
            'last_name'    => $nom,
            'date_naissance' => fake()->boolean(60) ? fake()->dateTimeBetween('-70 years','-22 years')->format('Y-m-d') : null,
            'lieu_naissance' => fake()->boolean(40) ? $ville : null,
            'piece_identite' => fake()->boolean(20) ? ('CNI N° ' . fake()->bothify('????-#####-##')) : null,
            'fonction'     => $posteFonction,
            'organisme'    => $organisme,
            'organization' => $organisme,
            'poste'        => fake()->boolean(50) ? fake()->jobTitle() : null,
            'type'         => $type,
            'domaines'     => $domaines,
            'langues'      => $langues,
            'ville_residence' => $ville,
            'city'         => $ville,
            'pays_residence'  => $pays,
            'country'      => $pays,
            'telephone_1'  => $telMobile,
            'phone'        => $telMobile,
            'telephone_2'  => $telephone2,
            'email'        => "{$nomEmail}@{$domainMail}",
            'adresse_physique' => fake()->boolean(40) ? ("{$ville}, " . fake()->streetAddress()) : null,
            'address'      => fake()->boolean(40) ? ("{$ville}, " . fake()->streetAddress()) : null,
            'contact_urgence' => fake()->boolean(30) ? [
                'nom'  => fake()->name(),
                'lien' => fake()->randomElement(['Époux(se)','Enfant','Frère','Soeur','Collègue','Assistant','Parent']),
                'tel'  => $telMobile,
            ] : null,
            'presentation_courte' => fake()->boolean(80)
                ? ucfirst(strtolower($posteFonction)) . ' ' . strtolower((string)$organisme) . ', ' . fake()->paragraph(1)
                : null,
            'biographie_longue'  => fake()->boolean(60)
                ? fake()->paragraphs(fake()->numberBetween(2, 4), true)
                : null,
            'sujets_sensibles'   => fake()->boolean(15)
                ? fake()->sentence(8)
                : null,
            'taille_habillement' => match (true) {
                $type === TypeIntervenantEnum::Politicien || $type === TypeIntervenantEnum::Expert => fake()->randomElement(['H-S','H-M','H-L','H-XL','H-XXL','F-S','F-M','F-L','F-38','F-40']),
                default => null,
            },
            'regime_alimentaire' => fake()->boolean(12)
                ? fake()->randomElement(['Végétarien','Halal','Sans gluten','Sans lactose','Cacher','Standard'])
                : null,
            'observations_admin' => fake()->boolean(30)
                ? fake()->sentence(10)
                : null,
            'notes'          => fake()->boolean(50) ? fake()->paragraph(1) : null,
            'disponibilite'  => $dispo,
            'is_active'      => $dispo === StatutDispoIntervenantEnum::Interdit ? false : true,
        ];
    }

    // ÉTATS COURTS pratiques
    public function politicien(): static { return $this->state(fn () => ['type' => TypeIntervenantEnum::Politicien]); }
    public function expert():     static { return $this->state(fn () => ['type' => TypeIntervenantEnum::Expert]); }
    public function societeCivil():static{ return $this->state(fn () => ['type' => TypeIntervenantEnum::SocieteCivil]); }
    public function artiste():    static { return $this->state(fn () => ['type' => TypeIntervenantEnum::Artiste]); }
    public function sportif():    static { return $this->state(fn () => ['type' => TypeIntervenantEnum::Sportif]); }
    public function invite():     static { return $this->state(fn () => ['type' => TypeIntervenantEnum::Invite]); }
    public function disponible(): static { return $this->state(fn () => ['disponibilite' => StatutDispoIntervenantEnum::Disponible]); }
    public function blacklist():  static { return $this->state(fn () => ['disponibilite' => StatutDispoIntervenantEnum::Interdit,'is_active' => false]); }
}
