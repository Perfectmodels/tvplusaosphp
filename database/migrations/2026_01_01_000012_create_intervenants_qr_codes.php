<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intervenants_qr_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intervenant_id')->constrained('intervenants')->cascadeOnDelete();
            $table->text('token');
            $table->json('payload')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('qr_png_storage_path', 500)->nullable();
            $table->string('qr_svg_storage_path', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intervenants_qr_codes');
    }
};
