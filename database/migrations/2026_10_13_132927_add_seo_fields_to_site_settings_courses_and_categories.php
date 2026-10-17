<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Site-wide search defaults (used wherever a page has nothing of its own),
     * search-console verification, and a per-course / per-category "show in
     * search results" switch like the one blog posts already have.
     */
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->string('seo_title', 120)->nullable()->after('footer_text');
            $table->string('seo_description', 320)->nullable()->after('seo_title');
            $table->string('seo_keywords', 500)->nullable()->after('seo_description');
            $table->string('seo_og_image', 1000)->nullable()->after('seo_keywords');
            $table->string('twitter_handle', 50)->nullable()->after('seo_og_image');
            $table->string('google_site_verification', 120)->nullable()->after('twitter_handle');
            $table->string('bing_site_verification', 120)->nullable()->after('google_site_verification');
            $table->boolean('allow_indexing')->default(true)->after('bing_site_verification');
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->boolean('is_indexable')->default(true)->after('og_image');
        });

        Schema::table('course_categories', function (Blueprint $table) {
            $table->boolean('is_indexable')->default(true)->after('og_image');
        });
    }

    public function down(): void
    {
        Schema::table('course_categories', function (Blueprint $table) {
            $table->dropColumn('is_indexable');
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('is_indexable');
        });

        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn([
                'seo_title', 'seo_description', 'seo_keywords', 'seo_og_image',
                'twitter_handle', 'google_site_verification', 'bing_site_verification', 'allow_indexing',
            ]);
        });
    }
};
