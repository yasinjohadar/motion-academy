<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupLog extends Model
{
    protected $fillable = [
        'backup_id',
        'level',
        'message',
    ];

    public function backup(): BelongsTo
    {
        return $this->belongsTo(Backup::class);
    }
}
