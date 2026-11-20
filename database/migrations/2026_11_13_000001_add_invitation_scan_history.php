<?php

use App\Enums\EtapeParcoursIntervenantEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parcours_intervenants', function (Blueprint $table) {
            $table->string('qr_token', 64)->nullable()->unique();
            $table->timestamp('en_attente_at')->nullable()->after('arrive_accueil_at');
        });

        Schema::table('intervenants_scan_logs', function (Blueprint $table) {
            $table->foreignId('parcours_intervenant_id')
                ->nullable()
                ->constrained('parcours_intervenants')
                ->nullOnDelete();
        });

        Schema::create('parcours_intervenant_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parcours_intervenant_id')
                ->constrained('parcours_intervenants')
                ->cascadeOnDelete();
            $table->string('etape', 32);
            $table->timestamp('recorded_at');
            $table->foreignId('triggered_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('source', 32)->default('bouton_web');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['parcours_intervenant_id', 'etape'], 'pie_parcours_etape_unique');
            $table->index(['parcours_intervenant_id', 'recorded_at'], 'pie_parcours_recorded_idx');
        });

        DB::table('parcours_intervenants')
            ->whereNull('qr_token')
            ->orderBy('id')
            ->chunkById(100, static function ($parcours) {
                foreach ($parcours as $item) {
                    do {
                        $token = Str::random(48);
                    } while (DB::table('parcours_intervenants')->where('qr_token', $token)->exists());

                    DB::table('parcours_intervenants')
                        ->where('id', $item->id)
                        ->update(['qr_token' => $token]);
                }
            });

        $colonnesEtapes = [
            EtapeParcoursIntervenantEnum::Invitation->value => 'invitation_envoyee_at',
            EtapeParcoursIntervenantEnum::PresenceConfirmee->value => 'confirme_at',
            EtapeParcoursIntervenantEnum::QrEnvoye->value => 'qr_envoye_at',
            EtapeParcoursIntervenantEnum::EnChemin->value => 'en_chemin_at',
            EtapeParcoursIntervenantEnum::ArriveSurSite->value => 'arrive_accueil_at',
            EtapeParcoursIntervenantEnum::EnAttente->value => 'en_attente_at',
            EtapeParcoursIntervenantEnum::EnPreparation->value => 'en_maquillage_at',
            EtapeParcoursIntervenantEnum::PretPlateau->value => 'pret_plateau_at',
            EtapeParcoursIntervenantEnum::EnPlateau->value => 'sur_plateau_at',
            EtapeParcoursIntervenantEnum::PassageTermine->value => 'diffuse_termine_at',
        ];

        DB::table('parcours_intervenants')
            ->orderBy('id')
            ->chunkById(100, static function ($parcours) use ($colonnesEtapes) {
                foreach ($parcours as $item) {
                    foreach ($colonnesEtapes as $etape => $colonne) {
                        if ($item->{$colonne} === null) {
                            continue;
                        }

                        DB::table('parcours_intervenant_events')->insert([
                            'parcours_intervenant_id' => $item->id,
                            'etape' => $etape,
                            'recorded_at' => $item->{$colonne},
                            'triggered_by_user_id' => null,
                            'source' => 'legacy',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('parcours_intervenant_events');

        Schema::table('intervenants_scan_logs', function (Blueprint $table) {
            $table->dropForeign(['parcours_intervenant_id']);
            $table->dropColumn('parcours_intervenant_id');
        });

        Schema::table('parcours_intervenants', function (Blueprint $table) {
            $table->dropUnique(['qr_token']);
            $table->dropColumn(['qr_token', 'en_attente_at']);
        });
    }
};
