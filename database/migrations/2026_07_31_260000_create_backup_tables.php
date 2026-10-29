<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_storage_configs', function (Blueprint $table) {
            $table->unsignedInteger('max_backups')->nullable()->after('cdn_url');
        });

        Schema::create('backup_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('backup_type', 30)->default('database');
            $table->foreignId('storage_config_id')->constrained('app_storage_configs')->cascadeOnDelete();
            $table->string('frequency', 20)->default('daily');
            $table->string('time', 5)->default('02:00');
            $table->unsignedTinyInteger('day_of_week')->nullable();
            $table->unsignedTinyInteger('day_of_month')->nullable();
            $table->unsignedInteger('retention_days')->default(30);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'next_run_at']);
        });

        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 20)->default('manual');
            $table->string('backup_type', 30)->default('database');
            $table->string('storage_driver', 50);
            $table->foreignId('storage_config_id')->nullable()->constrained('app_storage_configs')->nullOnDelete();
            $table->string('storage_path')->nullable();
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('compression_type', 20)->default('zip');
            $table->string('status', 20)->default('pending');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('stage')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('duration')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedInteger('retention_days')->default(30);
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('schedule_id')->nullable()->constrained('backup_schedules')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('backup_type');
            $table->index('expires_at');
        });

        Schema::create('backup_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('backup_id')->constrained('backups')->cascadeOnDelete();
            $table->string('level', 20)->default('info');
            $table->text('message');
            $table->timestamps();

            $table->index(['backup_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_logs');
        Schema::dropIfExists('backups');
        Schema::dropIfExists('backup_schedules');

        Schema::table('app_storage_configs', function (Blueprint $table) {
            $table->dropColumn('max_backups');
        });
    }
};
