<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_veille', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('type', 50)->default('rss');
            $table->string('url', 1000);
            $table->string('rss_feed', 1000)->nullable();
            $table->string('country', 5)->nullable();
            $table->string('language', 5)->default('fr');
            $table->string('category')->nullable();
            $table->integer('priority')->default(5);
            $table->string('scraper_class')->nullable();
            $table->timestamp('last_scraped_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('config')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_veille');
    }
};
