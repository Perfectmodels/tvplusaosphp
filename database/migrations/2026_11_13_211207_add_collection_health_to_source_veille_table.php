<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('source_veille', function (Blueprint $table) {
            $table->timestamp('last_succeeded_at')->nullable();
            $table->timestamp('collection_requested_at')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->unsignedInteger('last_items_count')->default(0);
            $table->unsignedInteger('last_new_items_count')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('source_veille', function (Blueprint $table) {
            $table->dropColumn(['last_succeeded_at', 'collection_requested_at', 'last_error', 'consecutive_failures', 'last_items_count', 'last_new_items_count']);
        });
    }
};
