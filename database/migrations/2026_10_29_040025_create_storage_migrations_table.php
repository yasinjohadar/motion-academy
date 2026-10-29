<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A copy of every uploaded file from one storage to another, after which the
     * site switches to the new one. One row per file keeps the job resumable.
     */
    public function up(): void
    {
        Schema::create('storage_migrations', function (Blueprint $table) {
            $table->id();
            // Null on either side means "this server".
            $table->foreignId('from_config_id')->nullable()->constrained('app_storage_configs')->nullOnDelete();
            $table->foreignId('to_config_id')->nullable()->constrained('app_storage_configs')->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('copied')->default(0);
            $table->unsignedInteger('skipped')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('storage_migration_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('storage_migration_id')->constrained()->cascadeOnDelete();
            $table->string('zone', 10);
            $table->string('path', 1024);
            $table->string('status', 10)->default('pending');
            $table->text('error')->nullable();

            $table->index(['storage_migration_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_migration_files');
        Schema::dropIfExists('storage_migrations');
    }
};
