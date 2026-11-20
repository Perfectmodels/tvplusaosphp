<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Messages de discussion interne d'un conducteur d'émission.
 *
 * Un fil par conducteur (canal privé broadcast `conducteur.{id}`) : les rôles
 * qui ont accès au conducteur (Vue Émission, Vue Rédaction, Vue Antenne) y
 * échangent des messages court pendant la préparation / l'antenne.
 *
 * Les échanges sont persistés (pas un chat éphémère) et restent consultables
 * après l'édition, comme le reste du conducteur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emission_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conducteur_id')->constrained('conducteurs')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('message');
            $table->timestamps();

            // Lecture incrémentale par fil : WHERE conducteur_id = ? AND id > ?
            $table->index(['conducteur_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emission_chat_messages');
    }
};
