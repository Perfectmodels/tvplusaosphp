<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conducteurs', function (Blueprint $table) {
            try {
                $table->dropForeign(['emission_id']);
            } catch (\Throwable) {
                // SQLite : les FK ne sont pas toujours nommées/déclarables — on ignore.
            }
        });

        // Portabilité : ->change() (Laravel 11+) gère MySQL, SQLite et Postgres.
        // L'ancien `ALTER TABLE ... MODIFY COLUMN` était MySQL-only et cassait les tests (SQLite :memory:).
        Schema::table('conducteurs', function (Blueprint $table) {
            $table->foreignId('emission_id')->nullable()->change();
        });

        Schema::table('conducteurs', function (Blueprint $table) {
            $table->foreign('emission_id')
                ->references('id')
                ->on('emissions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('conducteurs', function (Blueprint $table) {
            try {
                $table->dropForeign(['emission_id']);
            } catch (\Throwable) {
            }
        });

        Schema::table('conducteurs', function (Blueprint $table) {
            $table->foreignId('emission_id')->nullable(false)->change();
        });

        Schema::table('conducteurs', function (Blueprint $table) {
            $table->foreign('emission_id')
                ->references('id')
                ->on('emissions')
                ->cascadeOnDelete();
        });
    }
};
