<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE conducteurs SET type='presentateur' WHERE type='presentation'");
    }

    public function down(): void
    {
        DB::statement("UPDATE conducteurs SET type='presentation' WHERE type='presentateur'");
    }
};
