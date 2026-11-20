<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('password_changed_at')->nullable()->after('is_active');
            $table->foreignId('journaliste_id')->nullable()->constrained('journalistes')->nullOnDelete()->after('password_changed_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->after('journaliste_id');
            $table->index(['journaliste_id']);
            $table->index(['password_changed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['journaliste_id']);
            $table->dropForeign(['created_by']);
            $table->dropIndex(['users_journaliste_id_index']);
            $table->dropIndex(['users_password_changed_at_index']);
            $table->dropColumn(['password_changed_at', 'journaliste_id', 'created_by']);
        });
    }
};
