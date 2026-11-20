<?php

use App\Enums\CiviliteEnum;
use App\Enums\StatutDispoIntervenantEnum;
use App\Enums\TypeIntervenantEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Upgrade table `intervenants` 1:1 SPEC §3 + §7.2 (colonnes manquantes)
 * - Approche SAFE SQLite: ajouter colonnes FR sans renommer legacy
 *   (code, first_name, last_name, phone, organization, address, city, country).
 */
return new class extends Migration
{
    public function up(): void
    {
        $cols = collect(Schema::getColumnListing('intervenants'));

        Schema::table('intervenants', function (Blueprint $table) use ($cols) {
            // ==== IDENTIFIANTS STABLES SPEC §7.2 ====
            if (!$cols->contains('code_intervenant')) {
                $table->string('code_intervenant', 50)->nullable()->unique()->after('id');
            }
            if (!$cols->contains('uuid_intervenant')) {
                $table->uuid('uuid_intervenant')->nullable()->unique()->after('code_intervenant');
            }

            // ==== IDENTITE ====
            if (!$cols->contains('civilite')) {
                $table->string('civilite', 8)->nullable()->default(CiviliteEnum::Monsieur->value)->after('uuid_intervenant');
            }
            if (!$cols->contains('prenom')) {
                $table->string('prenom', 100)->nullable()->after('civilite');
            }
            if (!$cols->contains('nom')) {
                $table->string('nom', 100)->nullable()->after('prenom');
            }
            if (!$cols->contains('date_naissance')) {
                $table->date('date_naissance')->nullable()->after('nom');
            }
            if (!$cols->contains('lieu_naissance')) {
                $table->string('lieu_naissance', 120)->nullable()->after('date_naissance');
            }
            if (!$cols->contains('piece_identite')) {
                $table->string('piece_identite', 120)->nullable()->after('lieu_naissance');
            }

            // ==== PROFESSIONNEL ====
            if (!$cols->contains('organisme')) {
                $table->string('organisme', 200)->nullable()->after('piece_identite');
            }
            if (!$cols->contains('fonction')) {
                $table->string('fonction', 160)->nullable()->after('organisme');
            }
            if (!$cols->contains('poste')) {
                $table->string('poste', 160)->nullable()->after('fonction');
            }
            if (!$cols->contains('type')) {
                $table->string('type', 48)->nullable()->default(TypeIntervenantEnum::Invite->value)->after('poste');
            }
            if (!$cols->contains('domaines')) {
                $table->json('domaines')->nullable()->after('type');
            }
            if (!$cols->contains('langues')) {
                $table->json('langues')->nullable()->after('domaines');
            }
            if (!$cols->contains('ville_residence')) {
                $table->string('ville_residence', 120)->nullable()->after('langues');
            }
            if (!$cols->contains('pays_residence')) {
                $table->string('pays_residence', 80)->nullable()->after('ville_residence');
            }

            // ==== CONTACT ====
            if (!$cols->contains('telephone_1')) {
                $table->string('telephone_1', 30)->nullable()->after('pays_residence');
            }
            if (!$cols->contains('telephone_2')) {
                $table->string('telephone_2', 30)->nullable()->after('telephone_1');
            }
            if (!$cols->contains('adresse_physique')) {
                $table->string('adresse_physique', 255)->nullable()->after('telephone_2');
            }
            if (!$cols->contains('contact_urgence')) {
                $table->json('contact_urgence')->nullable()->after('adresse_physique');
            }

            // ==== INFOS TV+ (SPEC §3.4 INFOS TV+) ====
            if (!$cols->contains('presentation_courte')) {
                $table->text('presentation_courte')->nullable()->after('contact_urgence');
            }
            if (!$cols->contains('biographie_longue')) {
                $table->longText('biographie_longue')->nullable()->after('presentation_courte');
            }
            if (!$cols->contains('sujets_sensibles')) {
                $table->text('sujets_sensibles')->nullable()->after('biographie_longue');
            }
            if (!$cols->contains('taille_habillement')) {
                $table->string('taille_habillement', 40)->nullable()->after('sujets_sensibles');
            }
            if (!$cols->contains('regime_alimentaire')) {
                $table->string('regime_alimentaire', 80)->nullable()->after('taille_habillement');
            }
            if (!$cols->contains('observations_admin')) {
                $table->text('observations_admin')->nullable()->after('regime_alimentaire');
            }
            if (!$cols->contains('disponibilite')) {
                $table->string('disponibilite', 32)->nullable()->default(StatutDispoIntervenantEnum::Disponible->value)->after('observations_admin');
            }
        });

        Schema::table('intervenants', function (Blueprint $table) {
            try { $table->index(['type']); } catch (\Throwable) {}
            try { $table->index(['disponibilite']); } catch (\Throwable) {}
            try { $table->index(['email']); } catch (\Throwable) {}
        });
    }

    public function down(): void
    {
        $cols = collect(Schema::getColumnListing('intervenants'));
        Schema::table('intervenants', function (Blueprint $table) use ($cols) {
            $drops = [
                'code_intervenant','uuid_intervenant','civilite','prenom','nom','date_naissance',
                'lieu_naissance','piece_identite','organisme','fonction','poste','type','domaines','langues',
                'ville_residence','pays_residence','telephone_1','telephone_2','adresse_physique',
                'contact_urgence','presentation_courte','biographie_longue','sujets_sensibles',
                'taille_habillement','regime_alimentaire','observations_admin','disponibilite',
            ];
            foreach ($drops as $c) {
                if ($cols->contains($c)) {
                    try { $table->dropColumn($c); } catch (\Throwable) {}
                }
            }
        });
    }
};
