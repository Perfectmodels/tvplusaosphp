<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jeton public PERMANENT d'invitation, propre à chaque intervenant.
 *
 * Le QR code envoyé par WhatsApp encode `/i/{jeton}` : un lien stable qui ne
 * change pas d'une émission à l'autre. Le jeton est généré à la demande
 * (lazy) pour les fiches existantes et peut être régénéré pour invalider
 * un ancien QR.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intervenants', function (Blueprint $table) {
            $table->string('invitation_token', 64)
                ->nullable()
                ->unique()
                ->after('uuid_intervenant');
        });
    }

    public function down(): void
    {
        Schema::table('intervenants', function (Blueprint $table) {
            $table->dropUnique(['invitation_token']);
            $table->dropColumn('invitation_token');
        });
    }
};
