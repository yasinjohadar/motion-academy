<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');

            // Indexed columns written by App\Notifications\Channels\AppDatabaseChannel
            // so the notification centre can filter without JSON queries.
            $table->string('category', 40)->nullable()->index();
            $table->string('level', 20)->nullable()->index();
            $table->string('action_url')->nullable();

            $table->json('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // Serves the unread badge count and the notification centre listing.
            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category', 40);
            $table->string('channel', 20);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'category', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notifications');
    }
};
