<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intervenants', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->nullable();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 200)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('organization')->nullable();
            $table->string('role_type')->nullable();
            $table->string('specialty')->nullable();
            $table->text('bio_short')->nullable();
            $table->longText('bio_long')->nullable();
            $table->string('photo', 2048)->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->string('contract_type')->nullable();
            $table->decimal('fee_amount', 12, 2)->nullable();
            $table->json('availability')->nullable();
            $table->json('language')->nullable();
            $table->longText('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_scan_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intervenants');
    }
};
