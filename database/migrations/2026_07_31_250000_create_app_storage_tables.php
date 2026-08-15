<?php

use App\Models\AppStorageConfig;
use App\Models\StorageDiskMapping;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_storage_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('driver', 50);
            $table->text('config');
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0);
            $table->string('cdn_url')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('driver');
            $table->index('is_active');
            $table->index('priority');
        });

        Schema::create('storage_disk_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('disk_name')->unique();
            $table->string('label');
            $table->foreignId('primary_storage_id')->constrained('app_storage_configs')->cascadeOnDelete();
            $table->json('fallback_storage_ids')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });

        /*
         * These seed rows go through the models on purpose — AppStorageConfig::config
         * is encrypted by a mutator, so a raw insert would have to duplicate the
         * encryption scheme here.
         *
         * Activity logging is suppressed because both models are audited, and this
         * migration runs before the activity_log table exists on a fresh install.
         */
        activity()->withoutLogs(function (): void {
            $local = AppStorageConfig::query()->create([
                'name' => 'Local Default',
                'driver' => 'local',
                'config' => ['path' => 'public'],
                'is_active' => true,
                'priority' => 0,
            ]);

            StorageDiskMapping::query()->create([
                'disk_name' => 'site',
                'label' => 'ملفات الموقع (شعار / أيقونة)',
                'primary_storage_id' => $local->id,
                'fallback_storage_ids' => [],
                'is_active' => true,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_disk_mappings');
        Schema::dropIfExists('app_storage_configs');
    }
};
