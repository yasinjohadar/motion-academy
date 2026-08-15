<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Records which provider delivered the webhook so the processing job can pick the
     * right parser. Existing rows predate Meta support and are all Evolution.
     */
    public function up(): void
    {
        Schema::table('whatsapp_webhook_events', function (Blueprint $table) {
            $table->string('provider', 20)->default('evolution')->after('event_id')->index();
        });

        DB::table('whatsapp_webhook_events')->update(['provider' => 'evolution']);
    }

    public function down(): void
    {
        Schema::table('whatsapp_webhook_events', function (Blueprint $table) {
            $table->dropIndex(['provider']);
            $table->dropColumn('provider');
        });
    }
};
