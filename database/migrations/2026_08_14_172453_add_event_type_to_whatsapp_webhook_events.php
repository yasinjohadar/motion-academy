<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The event type was only available inside the `payload` JSON, which made the
     * webhook log impossible to filter meaningfully. Promoted to an indexed column
     * and backfilled from existing rows.
     */
    public function up(): void
    {
        Schema::table('whatsapp_webhook_events', function (Blueprint $table) {
            $table->string('event_type', 80)->nullable()->after('event_id')->index();
        });

        DB::table('whatsapp_webhook_events')
            ->select('id', 'payload')
            ->orderBy('id')
            ->chunk(200, function ($rows): void {
                foreach ($rows as $row) {
                    $payload = json_decode((string) $row->payload, true);
                    $type = is_array($payload) ? ($payload['event'] ?? null) : null;

                    if (is_string($type) && $type !== '') {
                        DB::table('whatsapp_webhook_events')
                            ->where('id', $row->id)
                            ->update(['event_type' => mb_substr($type, 0, 80)]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('whatsapp_webhook_events', function (Blueprint $table) {
            $table->dropIndex(['event_type']);
            $table->dropColumn('event_type');
        });
    }
};
