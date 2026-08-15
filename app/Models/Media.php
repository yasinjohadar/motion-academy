<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Media extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'media';

    protected $fillable = [
        'uuid',
        'disk',
        'path',
        'original_path',
        'filename',
        'mime',
        'extension',
        'size',
        'width',
        'height',
        'alt',
        'title',
        'caption',
        'description',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Media $media): void {
            if (blank($media->uuid)) {
                $media->uuid = (string) Str::uuid();
            }
        });
    }

    public function variants(): HasMany
    {
        return $this->hasMany(MediaVariant::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function variant(string $name): ?MediaVariant
    {
        return $this->variants->firstWhere('name', $name);
    }

    public function isSvg(): bool
    {
        return $this->mime === 'image/svg+xml' || $this->extension === 'svg';
    }

    public function isGif(): bool
    {
        return $this->mime === 'image/gif' || $this->extension === 'gif';
    }
}
