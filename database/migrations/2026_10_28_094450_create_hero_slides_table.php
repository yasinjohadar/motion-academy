<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hero_slides', function (Blueprint $table) {
            $table->id();
            $table->string('badge_text', 120)->nullable();
            $table->string('badge_icon', 80)->nullable();
            $table->string('title', 200);
            $table->string('title_highlight', 120)->nullable();
            $table->text('description')->nullable();

            $table->string('primary_button_label', 80)->nullable();
            $table->string('primary_button_url', 500)->nullable();
            $table->string('primary_button_icon', 80)->nullable();
            $table->string('secondary_button_label', 80)->nullable();
            $table->string('secondary_button_url', 500)->nullable();
            $table->string('secondary_button_icon', 80)->nullable();

            $table->string('bullet_label', 60)->nullable();
            $table->string('visual', 20)->default('none');
            $table->string('image', 1000)->nullable();
            $table->string('image_alt', 200)->nullable();
            $table->boolean('show_stats')->default(false);

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hero_slides');
    }
};
