<?php

namespace App\Services\Seo;

use Illuminate\Support\Str;

/**
 * What one public page says about itself to search engines and social previews.
 *
 * Controllers fill in only what they know; anything left empty falls back to the
 * site-wide defaults in SiteSeo when the head is rendered. Built fluently:
 *
 *     SeoPage::make('الدورات')->description('…')->canonical(route('courses'))
 */
class SeoPage
{
    public ?string $title = null;

    /** Use the title as-is, without " | site name". */
    public bool $exactTitle = false;

    public ?string $description = null;

    public ?string $keywords = null;

    public ?string $canonical = null;

    public ?string $image = null;

    public ?string $imageAlt = null;

    /** og:type — website, article, … */
    public string $type = 'website';

    public bool $index = true;

    public bool $follow = true;

    public ?string $prev = null;

    public ?string $next = null;

    public ?string $twitterCreator = null;

    /** Social-preview overrides; empty means "same as the page". */
    public ?string $ogTitle = null;

    public ?string $ogDescription = null;

    public ?string $twitterTitle = null;

    public ?string $twitterDescription = null;

    public ?string $twitterImage = null;

    /** @var array<string, string|array<int, string>> article:* properties */
    public array $article = [];

    /** @var array<int, array{name: string, url: string}> */
    public array $breadcrumbs = [];

    /** @var array<int, array<string, mixed>> extra JSON-LD nodes for this page */
    public array $schemas = [];

    public static function make(?string $title = null): self
    {
        $page = new self;
        $page->title = $title;

        return $page;
    }

    public function title(?string $title, bool $exact = false): self
    {
        if (filled($title)) {
            $this->title = $title;
            $this->exactTitle = $exact;
        }

        return $this;
    }

    /**
     * Plain text, trimmed to what a results page shows. Accepts HTML (rich-text
     * descriptions) and strips it.
     */
    public function description(?string $text): self
    {
        $plain = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        if ($plain !== '') {
            $this->description = Str::limit($plain, 160, '…');
        }

        return $this;
    }

    public function keywords(?string $keywords): self
    {
        if (filled($keywords)) {
            $this->keywords = $keywords;
        }

        return $this;
    }

    public function canonical(?string $url): self
    {
        if (filled($url)) {
            $this->canonical = $url;
        }

        return $this;
    }

    public function image(?string $url, ?string $alt = null): self
    {
        if (filled($url)) {
            $this->image = $url;
            $this->imageAlt = $alt;
        }

        return $this;
    }

    public function type(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Keep the page out of results; links on it are still followed unless told.
     */
    public function noindex(bool $follow = true): self
    {
        $this->index = false;
        $this->follow = $follow && $this->follow;

        return $this;
    }

    public function robots(bool $index, bool $follow = true): self
    {
        $this->index = $index;
        $this->follow = $follow;

        return $this;
    }

    public function pagination(?string $prev, ?string $next): self
    {
        $this->prev = $prev;
        $this->next = $next;

        return $this;
    }

    public function twitterCreator(?string $handle): self
    {
        if (filled($handle)) {
            $this->twitterCreator = '@'.ltrim((string) $handle, '@');
        }

        return $this;
    }

    public function openGraph(?string $title, ?string $description = null): self
    {
        $this->ogTitle = filled($title) ? $title : null;
        $this->ogDescription = filled($description) ? $description : null;

        return $this;
    }

    public function twitter(?string $title, ?string $description = null, ?string $image = null): self
    {
        $this->twitterTitle = filled($title) ? $title : null;
        $this->twitterDescription = filled($description) ? $description : null;
        $this->twitterImage = filled($image) ? $image : null;

        return $this;
    }

    /**
     * @param  string|array<int, string>|null  $value
     */
    public function article(string $property, string|array|null $value): self
    {
        if (filled($value)) {
            $this->article[$property] = $value;
        }

        return $this;
    }

    /**
     * The trail from the home page to this one; the home crumb is added for you.
     *
     * @param  array<string, string>  $trail  name => url
     */
    public function breadcrumbs(array $trail): self
    {
        $this->breadcrumbs = [];
        foreach ($trail as $name => $url) {
            $this->breadcrumbs[] = ['name' => (string) $name, 'url' => $url];
        }

        return $this;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function schema(array $node): self
    {
        $this->schemas[] = $node;

        return $this;
    }
}
