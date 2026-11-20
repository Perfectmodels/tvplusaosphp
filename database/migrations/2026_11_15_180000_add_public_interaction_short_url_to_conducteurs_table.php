<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conducteurs', function (Blueprint $table): void {
            $table->string('public_interaction_short_url', 255)->nullable()->after('public_interaction_token');
        });
    }

    public function down(): void
    {
        Schema::table('conducteurs', function (Blueprint $table): void {
            $table->dropColumn('public_interaction_short_url');
        });
    }
};
