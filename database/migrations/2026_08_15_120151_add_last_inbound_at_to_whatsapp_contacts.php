<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracks when the contact last messaged us, which is what opens Meta's 24-hour
     * customer service window.
     *
     * Deliberately separate from `last_seen_at`: that column is touched for other
     * reasons (presence, syncing) and using it would silently mis-report the window,
     * making the app send free-form text that Meta rejects.
     */
    public function up(): void
    {
        Schema::table('whatsapp_contacts', function (Blueprint $table) {
            $table->timestamp('last_inbound_at')->nullable()->after('last_seen_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_contacts', function (Blueprint $table) {
            $table->dropIndex(['last_inbound_at']);
            $table->dropColumn('last_inbound_at');
        });
    }
};
