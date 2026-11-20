<?php

use App\Enums\StatutConducteurEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conducteurs', function (Blueprint $table) {
            if (! Schema::hasColumn('conducteurs', 'parent_id')) {
                $table->foreignId('parent_id')->nullable()->after('id')->constrained('conducteurs')->nullOnDelete();
            }
            if (! Schema::hasColumn('conducteurs', 'date_edition')) {
                $table->date('date_edition')->nullable()->after('emission_id')->index();
            }
            if (! Schema::hasColumn('conducteurs', 'owner_id')) {
                $table->foreignId('owner_id')->nullable()->after('version')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('conducteurs', 'presenter_on_air_id')) {
                $table->foreignId('presenter_on_air_id')->nullable()->after('owner_id')->constrained('journalistes')->nullOnDelete();
            }
            if (! Schema::hasColumn('conducteurs', 'target_duration_sec')) {
                $table->integer('target_duration_sec')->default(3600)->after('total_duration');
            }
            if (! Schema::hasColumn('conducteurs', 'active_version')) {
                $table->boolean('active_version')->default(true)->after('target_duration_sec');
            }
            if (! Schema::hasColumn('conducteurs', 'date_locked_at')) {
                $table->timestamp('date_locked_at')->nullable()->after('published_at');
            }
            $table->string('type', 50)->default('redaction')->change();
            $table->string('status', 50)->default(StatutConducteurEnum::Brouillon->value)->change();
        });
    }

    public function down(): void
    {
        Schema::table('conducteurs', function (Blueprint $table) {
            $columns = [
                'parent_id', 'date_edition', 'owner_id', 'presenter_on_air_id',
                'target_duration_sec', 'active_version', 'date_locked_at',
            ];
            foreach ($columns as $c) {
                if (Schema::hasColumn('conducteurs', $c)) {
                    if (in_array($c, ['parent_id', 'owner_id', 'presenter_on_air_id'])) {
                        try {
                            $table->dropForeign(["{$c}_foreign"]);
                        } catch (\Throwable) {
                        }
                    }
                    $table->dropColumn($c);
                }
            }
        });
    }
};
