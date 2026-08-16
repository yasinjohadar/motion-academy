<?php

namespace App\Http\Resources;

use App\Models\BlogTag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BlogTag
 */
class BlogTagResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->resource->toPublicArray();
    }
}
