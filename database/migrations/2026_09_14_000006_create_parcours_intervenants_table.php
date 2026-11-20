<?php

use App\Enums\EtapeParcoursIntervenantEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table PARCOURS INTERVENANT (spécifique workflow 9 étapes linéaires)
 * Une ligne PAR intervenant PAR sujet (1:1 avec sequence_intervenants pivot)
 * Colonnes horodatages ①→⑨ permettent lecture timeline 0 ms de calcul.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parcours_intervenants', function (Blueprint $table) {
            $table->id();

            // Identité (redondance volontaire pour perf lectures timeline)
            $table->foreignId('sequence_intervenant_id')
                ->nullable()
                ->unique()
                ->constrained('sequence_intervenants')
                ->nullOnDelete();
            $table->foreignId('intervenant_id')
                ->constrained('intervenants')
                ->cascadeOnDelete()
                ->index('pi_intervenant_idx');
            $table->foreignId('sequence_id')
                ->constrained('sequences')
                ->cascadeOnDelete()
                ->index('pi_sequence_idx');
            $table->foreignId('conducteur_id')
                ->nullable()
                ->constrained('conducteurs')
                ->nullOnDelete();

            // 9 COLONNES HORODATAGES = 9 ÉTAPES
            // Chaque = l'heure à laquelle l'étape a été validée
            $table->timestamp('invitation_envoyee_at')->nullable()
                ->comment('① Chef édition crée le sujet + ajoute intervenant');
            $table->timestamp('confirme_at')->nullable()
                ->comment('② Intervenant confirme sa venue (mail/sms/manuel)');
            $table->timestamp('qr_envoye_at')->nullable()
                ->comment('③ QR code convocation envoyé (WhatsApp/Mail/SMS)');
            $table->timestamp('en_chemin_at')->nullable()
                ->comment('④ Intervenant part / en route (clic lien "je pars")');
            $table->timestamp('arrive_accueil_at')->nullable()
                ->comment('⑤ Scan QR poste Accueil → triggerScanWorkflow Accueil');
            $table->timestamp('en_maquillage_at')->nullable()
                ->comment('⑥ Scan QR poste Maquillage #1 début');
            $table->timestamp('pret_plateau_at')->nullable()
                ->comment('⑦ Scan QR poste Maquillage #2 fin → prêt');
            $table->timestamp('sur_plateau_at')->nullable()
                ->comment('⑧ Scan QR poste Plateau');
            $table->timestamp('diffuse_termine_at')->nullable()
                ->comment('⑨ Passage à l\'antenne effectif / séquence diffusée');

            // Meta workflow
            $table->string('confirme_medium', 20)->nullable()
                ->comment('mail | whatsapp | sms | telephone | manuel');
            $table->string('qr_medium', 20)->nullable()
                ->comment('whatsapp | mail | sms | manuel | impression');
            $table->integer('eta_arrive_minutes')->nullable()
                ->comment('Estimation temps trajet restant en chemin');
            $table->integer('retard_minutes')->nullable()
                ->default(0)
                ->comment('Retard accumulé vs heure_passage_prevue (peut être négatif = en avance)');
            $table->boolean('no_show')->default(false)
                ->comment('Absent non signalé');
            $table->boolean('est_actif')->default(true)
                ->comment('Si plusieurs trajets/jour, peut marquer inutilisé');
            $table->text('notes_logistique')->nullable();
            $table->json('metadata')->nullable()
                ->comment('infos diverses ex: code promo taxi, numéro train, repas...');

            // Audit last déclencheur
            $table->foreignId('last_trigger_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('last_trigger_medium', 20)->nullable()
                ->comment('whatsapp | mail | sms | scan_qr | bouton_web | api');

            $table->timestamps();

            $table->index(['conducteur_id','intervenant_id'], 'pi_conducteur_intervenant_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parcours_intervenants');
    }
};
