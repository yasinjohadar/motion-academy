<?php

namespace App\Services\Storage;

use App\Services\Frontend\GroupedSettings;

/**
 * Where every uploaded file lives: 0 means this server, otherwise the id of an
 * AppStorageConfig (a cloud account). Changed only by a completed file
 * migration, so the site never points at a storage that lacks its files.
 *
 * @method array{active_config_id: int} all()
 */
class StorageSettings extends GroupedSettings
{
    public const GROUP = 'storage';

    public const FIELDS = [
        'active_config_id' => ['type' => 'integer', 'default' => 0],
    ];

    public function activeConfigId(): ?int
    {
        $id = (int) $this->all()['active_config_id'];

        return $id > 0 ? $id : null;
    }

    public function setActiveConfigId(?int $id): void
    {
        $this->save(['active_config_id' => $id ?? 0]);
    }
}
