<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->index();
            $table->text('value')->nullable();
            $table->enum('type', ['string', 'integer', 'boolean', 'json'])->default('string');
            $table->string('group')->index();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['key', 'group']);
        });

        Schema::create('evolution_instances', function (Blueprint $table) {
            $table->id();
            $table->string('instance_name')->unique();
            $table->string('label')->nullable();
            $table->uuid('evolution_uuid')->nullable();
            $table->string('connection_status')->default('close');
            $table->string('owner_jid')->nullable();
            $table->string('profile_name')->nullable();
            $table->string('phone_number')->nullable();
            $table->text('profile_pic_url')->nullable();
            $table->longText('qr_code')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_manual')->default(false);
            $table->boolean('rotation_enabled')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->json('metadata')->nullable();
            $table->string('evolution_base_url')->nullable();
            $table->text('evolution_api_key')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamps();

            $table->index('connection_status');
            $table->index('is_default');
        });

        Schema::create('whatsapp_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('wa_id')->unique();
            $table->string('name')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->enum('direction', ['inbound', 'outbound']);
            $table->foreignId('contact_id')->constrained('whatsapp_contacts')->cascadeOnDelete();
            $table->string('meta_message_id')->nullable();
            $table->string('type')->default('text');
            $table->text('body')->nullable();
            $table->enum('status', ['queued', 'sent', 'delivered', 'read', 'failed'])->default('queued');
            $table->json('payload')->nullable();
            $table->json('error')->nullable();
            $table->timestamps();

            $table->index(['contact_id', 'created_at']);
            $table->index('meta_message_id');
            $table->index(['status', 'created_at']);
        });

        Schema::create('whatsapp_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id')->unique();
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index('processed_at');
        });

        Schema::create('whatsapp_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->text('message_template');
            $table->enum('send_type', ['text', 'template'])->default('text');
            $table->string('campaign_type', 50)->default('standard');
            $table->json('meta')->nullable();
            $table->integer('total_recipients')->default(0);
            $table->integer('sent_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->index('status');
        });

        Schema::create('whatsapp_broadcast_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('broadcast_id')->constrained('whatsapp_broadcasts')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('phone', 32);
            $table->string('status', 20)->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['broadcast_id', 'phone']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_broadcast_recipients');
        Schema::dropIfExists('whatsapp_broadcasts');
        Schema::dropIfExists('whatsapp_webhook_events');
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_contacts');
        Schema::dropIfExists('evolution_instances');
        Schema::dropIfExists('system_settings');
    }
};
