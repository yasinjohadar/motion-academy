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
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('disk', 64)->default('media');
            $table->string('path');
            $table->string('original_path')->nullable();
            $table->string('filename');
            $table->string('mime', 127);
            $table->string('extension', 32);
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alt')->nullable();
            $table->string('title')->nullable();
            $table->string('caption')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['mime', 'created_at']);
            $table->index('filename');
        });

        Schema::create('media_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->string('name', 32);
            $table->string('path');
            $table->string('mime', 127);
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestamps();

            $table->unique(['media_id', 'name']);
        });

        if (Schema::hasTable('app_storage_configs') && Schema::hasTable('storage_disk_mappings')) {
            // StorageDiskMapping is audited; this migration predates the activity_log
            // table on a fresh install, so suppress logging for the seed row.
            activity()->withoutLogs(function (): void {
                $local = AppStorageConfig::query()->where('driver', 'local')->orderBy('id')->first();
                if ($local && ! StorageDiskMapping::query()->where('disk_name', 'media')->exists()) {
                    StorageDiskMapping::query()->create([
                        'disk_name' => 'media',
                        'label' => 'مكتبة الوسائط',
                        'primary_storage_id' => $local->id,
                        'fallback_storage_ids' => [],
                        'is_active' => true,
                    ]);
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('media_variants');
        Schema::dropIfExists('media');

        if (Schema::hasTable('storage_disk_mappings')) {
            StorageDiskMapping::query()->where('disk_name', 'media')->delete();
        }
    }
};
