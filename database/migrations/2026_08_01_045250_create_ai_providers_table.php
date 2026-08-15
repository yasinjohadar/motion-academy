<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('driver', 64);
            $table->text('api_key')->nullable();
            $table->string('base_url')->nullable();
            $table->string('default_model')->nullable();
            $table->json('options')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_default', 'is_active']);
            $table->index('driver');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_providers');
    }
};
