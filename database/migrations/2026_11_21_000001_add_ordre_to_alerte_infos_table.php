<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alerte_infos', function (Blueprint $table): void {
            $table->unsignedInteger('ordre')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('alerte_infos', function (Blueprint $table): void {
            $table->dropColumn('ordre');
        });
    }
};
