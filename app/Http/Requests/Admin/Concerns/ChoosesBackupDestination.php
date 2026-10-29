<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Models\AppStorageConfig;
use Closure;

/**
 * Backup destination field: "local" (this server's private folder) or the id of
 * an active cloud storage account.
 */
trait ChoosesBackupDestination
{
    public const LOCAL_DESTINATION = 'local';

    /**
     * @return array<int, mixed>
     */
    protected function backupDestinationRules(): array
    {
        return [
            'required',
            function (string $attribute, mixed $value, Closure $fail): void {
                if ($value === self::LOCAL_DESTINATION) {
                    return;
                }

                $exists = is_numeric($value) && AppStorageConfig::query()
                    ->whereKey((int) $value)
                    ->where('is_active', true)
                    ->exists();

                if (! $exists) {
                    $fail('وجهة التخزين غير صالحة.');
                }
            },
        ];
    }

    /**
     * The chosen account id, or null for this server.
     */
    public function storageConfigId(): ?int
    {
        $value = $this->validated('storage_config_id');

        return $value === self::LOCAL_DESTINATION ? null : (int) $value;
    }
}
