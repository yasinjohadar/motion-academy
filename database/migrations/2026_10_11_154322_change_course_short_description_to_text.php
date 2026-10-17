<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The short description is now edited with the rich-text editor, and its HTML does
 * not fit a 500-character column. TEXT keeps it in the FULLTEXT index on MySQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->text('short_description')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('short_description', 500)->nullable()->change();
        });
    }
};
