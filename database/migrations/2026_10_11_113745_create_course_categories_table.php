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
        Schema::create('course_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('icon', 100)->nullable();
            $table->string('color', 32)->nullable();
            $table->string('image', 500)->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('course_categories')->nullOnDelete();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->text('meta_keywords')->nullable();
            $table->string('og_image', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('order')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['parent_id', 'is_active', 'order']);
        });

        if (Schema::hasTable('storage_disk_mappings') && Schema::hasTable('app_storage_configs')) {
            // StorageDiskMapping is audited; suppress logging for the seed row like the blog migration does.
            activity()->withoutLogs(function (): void {
                $local = AppStorageConfig::query()->where('driver', 'local')->orderBy('id')->first();
                if ($local && ! StorageDiskMapping::query()->where('disk_name', 'courses')->exists()) {
                    StorageDiskMapping::query()->create([
                        'disk_name' => 'courses',
                        'label' => 'صور الدورات',
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
        Schema::dropIfExists('course_categories');
    }
};
