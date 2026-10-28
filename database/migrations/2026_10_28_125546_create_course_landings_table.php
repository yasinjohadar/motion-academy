<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A course's standalone advertising page. Title, cover, price and stats are
     * read from the course itself; everything here is landing-only. The
     * repeatable blocks are JSON because the page always loads them together.
     */
    public function up(): void
    {
        Schema::create('course_landings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('is_published')->default(false);

            $table->string('tagline', 300)->nullable();
            $table->longText('description')->nullable();
            $table->json('videos')->nullable();
            $table->json('goals')->nullable();
            $table->json('features')->nullable();
            $table->json('testimonials')->nullable();
            $table->json('social_links')->nullable();

            $table->boolean('show_price')->default(true);
            $table->string('cta_label', 60)->nullable();
            $table->string('cta_url', 500)->nullable();

            $table->string('meta_title', 120)->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->boolean('allow_indexing')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_landings');
    }
};
