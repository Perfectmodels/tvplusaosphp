<?php

use App\Enums\NiveauAlerteEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerte_infos', function (Blueprint $table): void {
            $table->id();
            $table->string('titre', 150);
            $table->text('message');
            $table->string('niveau', 20)->default(NiveauAlerteEnum::Info->value);
            $table->boolean('actif')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expire_at')->nullable();
            $table->timestamps();

            $table->index(['actif', 'expire_at'], 'alerte_infos_actif_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerte_infos');
    }
};
