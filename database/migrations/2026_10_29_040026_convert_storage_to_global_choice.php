<?php

use App\Models\AppStorageConfig;
use App\Models\SystemSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * From per-disk storage mappings to one global choice (this server or one
     * cloud account).
     *
     *  1. Keep whatever cloud account the site already used, so nothing breaks.
     *  2. Move local backup archives out of storage/app/public — that folder is
     *     served at /storage, which made every local backup downloadable.
     *  3. "Local" is no longer an account: drop those rows and the mappings.
     */
    public function up(): void
    {
        $this->keepCloudAccountInUse();

        // Tests migrate a throwaway database; they must not move real files.
        if (! app()->runningUnitTests()) {
            $this->moveLocalBackupsOutOfPublic();
            File::deleteDirectory(storage_path('app/public/connection-tests'));
        }

        Schema::table('backup_schedules', function (Blueprint $table) {
            $table->dropForeign(['storage_config_id']);
        });
        Schema::table('backup_schedules', function (Blueprint $table) {
            // Null now means "this server (private folder)".
            $table->foreignId('storage_config_id')->nullable()->change();
            $table->foreign('storage_config_id')->references('id')->on('app_storage_configs')->nullOnDelete();
        });

        $localIds = DB::table('app_storage_configs')->where('driver', 'local')->pluck('id');
        if ($localIds->isNotEmpty()) {
            DB::table('backups')->whereIn('storage_config_id', $localIds)->update(['storage_config_id' => null, 'storage_driver' => 'local']);
            DB::table('backup_schedules')->whereIn('storage_config_id', $localIds)->update(['storage_config_id' => null]);
            DB::table('app_storage_configs')->whereIn('id', $localIds)->delete();
        }

        Schema::dropIfExists('storage_disk_mappings');
    }

    /**
     * Not reversible: the mappings it replaces were only ever pointers, and the
     * backups it moved must not go back under the public folder.
     */
    public function down(): void {}

    private function keepCloudAccountInUse(): void
    {
        if (! Schema::hasTable('storage_disk_mappings')) {
            return;
        }

        $cloudId = DB::table('storage_disk_mappings as m')
            ->join('app_storage_configs as c', 'c.id', '=', 'm.primary_storage_id')
            ->where('m.is_active', true)
            ->where('c.driver', '!=', 'local')
            ->groupBy('c.id')
            ->orderByRaw('count(*) desc')
            ->value('c.id');

        if (! $cloudId) {
            return;
        }

        activity()->withoutLogs(function () use ($cloudId): void {
            $config = AppStorageConfig::query()->findOrFail($cloudId);
            // Its files sit at the bucket root, from before public/ and private/ prefixes.
            $config->update(['config' => [...$config->getDecryptedConfig(), 'legacy_root' => true]]);
        });

        SystemSetting::set('active_config_id', $cloudId, 'integer', 'storage');

        Log::warning('Storage: kept the cloud account already in use as the global storage', ['id' => $cloudId]);
    }

    private function moveLocalBackupsOutOfPublic(): void
    {
        $from = storage_path('app/public/backups');

        if (is_dir($from)) {
            $to = storage_path('app/private/backups');
            File::ensureDirectoryExists($to);
            File::copyDirectory($from, $to);
            File::deleteDirectory($from);
        }

        // Where `storage:link` could not make a link (some Windows setups),
        // public/storage is a plain copy whose backups folder is public too.
        // Done after the move: through a junction this path is the folder just
        // emptied above, so nothing real can be lost.
        $publicCopy = public_path('storage/backups');
        if (is_dir($publicCopy)) {
            File::deleteDirectory($publicCopy);
        }
    }
};
