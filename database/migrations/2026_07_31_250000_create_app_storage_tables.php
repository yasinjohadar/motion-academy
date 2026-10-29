<?php

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
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_disk_mappings');
        Schema::dropIfExists('app_storage_configs');
    }
};
