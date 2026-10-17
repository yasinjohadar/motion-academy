<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Resources attached to a lesson: any number of named links and uploaded files.
 *
 * `course_lessons.attachment` stays, but only as the main file of a "file" lesson.
 * The optional single attachment other lessons used to carry moves here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_lesson_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_lesson_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10); // link | file
            $table->string('title', 150);
            $table->string('url', 1000)->nullable();
            $table->string('path', 500)->nullable();
            $table->string('original_name', 255)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->index(['course_lesson_id', 'order']);
        });

        $rows = DB::table('course_lessons')
            ->where('type', '!=', 'file')
            ->whereNotNull('attachment')
            ->where('attachment', '!=', '')
            ->get(['id', 'attachment']);

        foreach ($rows as $row) {
            $isLink = str_starts_with((string) $row->attachment, 'http://') || str_starts_with((string) $row->attachment, 'https://');

            DB::table('course_lesson_attachments')->insert([
                'course_lesson_id' => $row->id,
                'type' => $isLink ? 'link' : 'file',
                'title' => $isLink ? 'مرفق الدرس' : basename((string) $row->attachment),
                'url' => $isLink ? $row->attachment : null,
                'path' => $isLink ? null : $row->attachment,
                'original_name' => $isLink ? null : basename((string) $row->attachment),
                'order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('course_lessons')->where('id', $row->id)->update(['attachment' => null]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('course_lesson_attachments');
    }
};
