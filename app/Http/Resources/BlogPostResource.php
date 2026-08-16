<?php

namespace App\Http\Resources;

use App\Models\BlogPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A published post as the public API returns it.
 *
 * The shape comes from BlogPost::toPublicArray(), which the Inertia blog pages already
 * use. Re-listing the fields here would create a second definition that drifts from the
 * first the moment either changes — the resource's job is the HTTP envelope, not a
 * duplicate of the model's public representation.
 *
 * @mixin BlogPost
 */
class BlogPostResource extends JsonResource
{
    /**
     * Full article body is only sent when a single post is requested; a listing that
     * carried every body would be enormous for no reader's benefit.
     */
    public function __construct($resource, private bool $withContent = false)
    {
        parent::__construct($resource);
    }

    public static function withContent(BlogPost $post): self
    {
        return new self($post, withContent: true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->resource->toPublicArray($this->withContent);
    }
}
