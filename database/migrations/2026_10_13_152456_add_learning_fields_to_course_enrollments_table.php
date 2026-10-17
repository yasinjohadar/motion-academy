<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where the student is in the course, kept on the enrollment so the dashboard
     * lists every course with its progress without counting lessons per row.
     */
    public function up(): void
    {
        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->foreignId('last_lesson_id')->nullable()->after('decided_at')
                ->constrained('course_lessons')->nullOnDelete();
            $table->timestamp('last_accessed_at')->nullable()->after('last_lesson_id');
            $table->unsignedTinyInteger('progress_percent')->default(0)->after('last_accessed_at');
            $table->timestamp('completed_at')->nullable()->after('progress_percent');

            $table->index(['user_id', 'last_accessed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'last_accessed_at']);
            $table->dropConstrainedForeignId('last_lesson_id');
            $table->dropColumn(['last_accessed_at', 'progress_percent', 'completed_at']);
        });
    }
};
