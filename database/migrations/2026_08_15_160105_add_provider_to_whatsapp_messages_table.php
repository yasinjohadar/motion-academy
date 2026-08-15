<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records which provider carried each outbound message.
 *
 * Needed because Flaxxa reports delivery only when asked, so a job has to find
 * "unfinished Flaxxa messages" — a query with no indexable column before this.
 * Existing rows stay null, meaning unknown; nothing infers a provider retroactively.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->string('provider', 32)->nullable()->after('direction');

            // The polling job scans by both together.
            $table->index(['provider', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropIndex(['provider', 'status']);
            $table->dropColumn('provider');
        });
    }
};
