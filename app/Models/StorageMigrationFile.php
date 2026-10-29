<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One file to copy during a storage migration.
 */
class StorageMigrationFile extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'storage_migration_id',
        'zone',
        'path',
        'status',
        'error',
    ];

    /** @return BelongsTo<StorageMigration, $this> */
    public function migration(): BelongsTo
    {
        return $this->belongsTo(StorageMigration::class, 'storage_migration_id');
    }
}
