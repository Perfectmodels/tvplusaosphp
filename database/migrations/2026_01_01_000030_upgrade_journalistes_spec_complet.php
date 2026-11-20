<?php

use App\Enums\CiviliteEnum;
use App\Enums\FonctionJournalisteEnum;
use App\Enums\PoleRubriqueEnum;
use App\Enums\StatutJournalisteEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Upgrade table `journalistes` pour correspondre 1:1 à la SPEC §2 et §7.2
 * - Colonnes legacy (first_name, last_name, phone, specialty, grade, biography) ne sont PAS renommées
 *   pour éviter les bugs SQLite renameColumn / hasColumn.
 * - Nouvelles colonnes FR: prenom, nom, telephone, specialites (JSON), notes_admin etc.
 */
return new class extends Migration
{
    public function up(): void
    {
        $cols = collect(Schema::getColumnListing('journalistes'));

        Schema::table('journalistes', function (Blueprint $table) use ($cols) {
            if (!$cols->contains('matricule')) {
                $table->string('matricule', 32)->nullable()->unique()->after('id');
            }
            if (!$cols->contains('civilite')) {
                $table->string('civilite', 8)->nullable()->default(CiviliteEnum::Monsieur->value)->after('matricule');
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
            if (!$cols->contains('fonction')) {
                $table->string('fonction', 64)->nullable()->default(FonctionJournalisteEnum::Journaliste->value)->after('lieu_naissance');
            }
            if (!$cols->contains('pole')) {
                $table->string('pole', 48)->nullable()->default(PoleRubriqueEnum::Actualite->value)->after('fonction');
            }
            if (!$cols->contains('statut')) {
                $table->string('statut', 32)->nullable()->default(StatutJournalisteEnum::Actif->value)->after('pole');
            }
            if (!$cols->contains('date_embauche')) {
                $table->date('date_embauche')->nullable()->after('statut');
            }
            if (!$cols->contains('specialites')) {
                $table->json('specialites')->nullable()->after('date_embauche');
            }
            if (!$cols->contains('telephone')) {
                $table->string('telephone', 30)->nullable()->after('specialites');
            }
            if (!$cols->contains('adresse')) {
                $table->string('adresse', 255)->nullable()->after('telephone');
            }
            if (!$cols->contains('notes_admin')) {
                $table->text('notes_admin')->nullable()->after('adresse');
            }
            if (!$cols->contains('is_presentateur')) {
                $table->boolean('is_presentateur')->default(false)->after('notes_admin');
            }
            if (!$cols->contains('date_promotion_presentateur')) {
                $table->dateTime('date_promotion_presentateur')->nullable()->after('is_presentateur');
            }
        });

        Schema::table('journalistes', function (Blueprint $table) {
            try { $table->index(['pole']); } catch (\Throwable) {}
            try { $table->index(['statut']); } catch (\Throwable) {}
            try { $table->index(['fonction']); } catch (\Throwable) {}
            try { $table->index(['is_presentateur']); } catch (\Throwable) {}
        });
    }

    public function down(): void
    {
        $cols = collect(Schema::getColumnListing('journalistes'));
        Schema::table('journalistes', function (Blueprint $table) use ($cols) {
            $drops = [
                'matricule','civilite','prenom','nom','date_naissance','lieu_naissance',
                'fonction','pole','statut','date_embauche','specialites','telephone',
                'adresse','notes_admin','is_presentateur','date_promotion_presentateur',
            ];
            foreach ($drops as $c) {
                if ($cols->contains($c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
