<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fenêtre de confirmation d'une invitation (7 jours à compter de l'envoi
 * WhatsApp). Un renvoi relance la fenêtre ; le QR, lui, reste permanent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parcours_intervenants', function (Blueprint $table) {
            $table->timestamp('confirmation_expires_at')
                ->nullable()
                ->after('qr_medium');
        });
    }

    public function down(): void
    {
        Schema::table('parcours_intervenants', function (Blueprint $table) {
            $table->dropColumn('confirmation_expires_at');
        });
    }
};
