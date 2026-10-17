<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Courses, their sections and lessons.
 *
 * Every index below backs a concrete query: the admin filters today, and the public
 * catalogue (published / category / instructor / featured / free / level) next.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_category_id')->constrained('course_categories')->restrictOnDelete();
            $table->foreignId('instructor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title');
            $table->string('slug')->unique();
            $table->string('subtitle')->nullable();
            $table->string('short_description', 500)->nullable();
            $table->longText('description')->nullable();

            $table->string('thumbnail', 500)->nullable();
            $table->string('intro_video_source', 16)->nullable();
            $table->string('intro_video', 500)->nullable();

            $table->string('level', 16)->default('all');
            $table->string('language', 8)->default('ar');
            $table->string('status', 16)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_featured')->default(false);

            $table->boolean('is_free')->default(false);
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->timestamp('sale_ends_at')->nullable();
            $table->char('currency', 3)->default('USD');

            $table->json('learning_outcomes')->nullable();
            $table->json('requirements')->nullable();
            $table->json('target_audience')->nullable();
            $table->boolean('has_certificate')->default(false);
            $table->unsignedInteger('max_students')->nullable();

            $table->unsignedInteger('lessons_count')->default(0);
            $table->unsignedInteger('duration_seconds')->default(0);

            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->text('meta_keywords')->nullable();
            $table->string('og_image', 500)->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['status', 'published_at']);
            $table->index(['course_category_id', 'status']);
            $table->index(['instructor_id', 'status']);
            $table->index(['is_featured', 'status']);
            $table->index(['is_free', 'status']);
            $table->index(['level', 'status']);
            $table->index('deleted_at');

            // SQLite (local dev) has no FULLTEXT; search falls back to LIKE there.
            if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb', 'pgsql'], true)) {
                $table->fullText(['title', 'short_description']);
            }
        });

        Schema::create('course_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->index(['course_id', 'order']);
        });

        Schema::create('course_lessons', function (Blueprint $table) {
            $table->id();
            // Denormalised from the section so course-wide counts never need a join.
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('course_section_id')->constrained('course_sections')->cascadeOnDelete();
            $table->string('title');
            $table->string('type', 16)->default('video');
            $table->string('video_source', 16)->nullable();
            $table->string('video', 500)->nullable();
            $table->longText('content')->nullable();
            $table->string('attachment', 500)->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->boolean('is_preview')->default(false);
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->index(['course_section_id', 'order']);
            $table->index(['course_id', 'is_published']);
            $table->index(['course_id', 'is_preview']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_lessons');
        Schema::dropIfExists('course_sections');
        Schema::dropIfExists('courses');
    }
};
