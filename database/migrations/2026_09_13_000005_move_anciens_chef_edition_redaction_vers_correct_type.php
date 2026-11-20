<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE conducteurs SET type='chef_edition' WHERE id IN (1, 4)");
    }

    public function down(): void
    {
        DB::statement("UPDATE conducteurs SET type='redaction' WHERE id IN (1, 4)");
    }
};
