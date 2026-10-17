<?php

namespace App\Services\Seo;

use App\Models\SiteSetting;
use App\Models\SocialLink;
use Illuminate\Support\Str;
use Throwable;

/**
 * Turns a SeoPage into the tags of a public page's <head>.
 *
 * Owns the site-wide part: defaults from the admin "SEO" settings, the
 * Organization and WebSite structured data every page carries, search-console
 * verification, and the global "allow indexing" switch (off on a staging copy).
 */
class SiteSeo
{
    public const LOCALE = 'ar_AR';

    private ?SiteSetting $settings = null;

    private bool $loaded = false;

    public function settings(): ?SiteSetting
    {
        if (! $this->loaded) {
            $this->loaded = true;
            try {
                $this->settings = SiteSetting::current();
            } catch (Throwable) {
                $this->settings = null;
            }
        }

        return $this->settings;
    }

    public function siteName(): string
    {
        return (string) ($this->settings()?->site_name ?: config('app.name'));
    }

    public function allowsIndexing(): bool
    {
        return (bool) ($this->settings()?->allow_indexing ?? true);
    }

    /**
     * Search engines and social networks need absolute URLs.
     */
    public function absolute(?string $url): ?string
    {
        if (! filled($url)) {
            return null;
        }

        return Str::startsWith($url, ['http://', 'https://']) ? $url : url($url);
    }

    public function logoUrl(): string
    {
        return (string) $this->absolute($this->settings()?->logoUrl() ?: asset('frontend/assets/images/logo.png'));
    }

    public function defaultImage(): string
    {
        return (string) $this->absolute($this->settings()?->seo_og_image ?: $this->logoUrl());
    }

    /**
     * @return array{
     *     title: string,
     *     meta: array<int, array{attr: string, key: string, content: string}>,
     *     links: array<int, array{rel: string, href: string}>,
     *     jsonLd: array<string, mixed>
     * }
     */
    public function render(?SeoPage $page): array
    {
        $page ??= SeoPage::make();
        $settings = $this->settings();
        $siteName = $this->siteName();

        $title = match (true) {
            blank($page->title) => $settings?->seo_title ?: trim($siteName.' — '.($settings?->tagline ?? ''), ' —'),
            $page->exactTitle => $page->title,
            default => $page->title.' | '.$siteName,
        };

        $description = $page->description ?: $settings?->seo_description ?: $settings?->tagline;
        $keywords = $page->keywords ?: $settings?->seo_keywords;
        $canonical = (string) $this->absolute($page->canonical ?: url()->current());
        $image = $this->absolute($page->image) ?? $this->defaultImage();
        $index = $page->index && $this->allowsIndexing();

        $robots = $index
            ? ($page->follow ? 'index, follow' : 'index, nofollow').', max-image-preview:large, max-snippet:-1, max-video-preview:-1'
            : ($page->follow ? 'noindex, follow' : 'noindex, nofollow');

        $meta = [];
        $add = function (string $attr, string $key, ?string $content) use (&$meta): void {
            if (filled($content)) {
                $meta[] = ['attr' => $attr, 'key' => $key, 'content' => (string) $content];
            }
        };

        $add('name', 'description', $description);
        $add('name', 'keywords', $keywords);
        $add('name', 'robots', $robots);
        $add('name', 'googlebot', $robots);
        $add('name', 'google-site-verification', $settings?->google_site_verification);
        $add('name', 'msvalidate.01', $settings?->bing_site_verification);

        $add('property', 'og:site_name', $siteName);
        $add('property', 'og:locale', self::LOCALE);
        $add('property', 'og:type', $page->type);
        $add('property', 'og:title', $page->ogTitle ?: ($page->title ?: $title));
        $add('property', 'og:description', $page->ogDescription ?: $description);
        $add('property', 'og:url', $canonical);
        $add('property', 'og:image', $image);
        $add('property', 'og:image:alt', $page->imageAlt ?: ($page->title ?: $siteName));

        foreach ($page->article as $property => $value) {
            foreach ((array) $value as $item) {
                $add('property', 'article:'.$property, $item);
            }
        }

        $twitterSite = filled($settings?->twitter_handle) ? '@'.ltrim((string) $settings->twitter_handle, '@') : null;
        $add('name', 'twitter:card', 'summary_large_image');
        $add('name', 'twitter:site', $twitterSite);
        $add('name', 'twitter:creator', $page->twitterCreator ?: $twitterSite);
        $add('name', 'twitter:title', $page->twitterTitle ?: $page->ogTitle ?: ($page->title ?: $title));
        $add('name', 'twitter:description', $page->twitterDescription ?: $page->ogDescription ?: $description);
        $add('name', 'twitter:image', $this->absolute($page->twitterImage) ?? $image);
        $add('name', 'twitter:image:alt', $page->imageAlt ?: ($page->title ?: $siteName));

        $links = [['rel' => 'canonical', 'href' => $canonical]];
        if ($page->prev) {
            $links[] = ['rel' => 'prev', 'href' => (string) $this->absolute($page->prev)];
        }
        if ($page->next) {
            $links[] = ['rel' => 'next', 'href' => (string) $this->absolute($page->next)];
        }

        return [
            'title' => $title,
            'meta' => $meta,
            'links' => $links,
            'jsonLd' => $this->graph($page, $canonical),
        ];
    }

    /**
     * One JSON-LD document per page: the site's Organization and WebSite, the
     * breadcrumb trail, and whatever the page itself describes.
     *
     * @return array<string, mixed>
     */
    public function graph(SeoPage $page, string $canonical): array
    {
        $graph = [$this->organization(), $this->website()];

        if ($page->breadcrumbs !== []) {
            $graph[] = StructuredData::breadcrumbs([
                ['name' => 'الرئيسية', 'url' => route('home')],
                ...$page->breadcrumbs,
            ], $canonical);
        }

        foreach ($page->schemas as $node) {
            $graph[] = $node;
        }

        return ['@context' => 'https://schema.org', '@graph' => $graph];
    }

    /**
     * @return array<string, mixed>
     */
    public function organization(): array
    {
        $settings = $this->settings();

        $sameAs = [];
        try {
            $sameAs = SocialLink::query()->where('is_active', true)->orderBy('sort_order')->pluck('url')->filter()->values()->all();
        } catch (Throwable) {
            // No social links table yet (fresh install): the node is still valid.
        }

        $contact = array_filter([
            '@type' => 'ContactPoint',
            'contactType' => 'customer support',
            'email' => $settings?->email,
            'telephone' => $settings?->phone,
            'availableLanguage' => ['Arabic'],
        ]);

        return array_filter([
            '@type' => 'EducationalOrganization',
            '@id' => route('home').'#organization',
            'name' => $this->siteName(),
            'alternateName' => $settings?->site_name_en,
            'url' => route('home'),
            'logo' => ['@type' => 'ImageObject', 'url' => $this->logoUrl()],
            'description' => $settings?->seo_description ?: $settings?->tagline,
            'sameAs' => $sameAs ?: null,
            'contactPoint' => isset($contact['email']) || isset($contact['telephone']) ? $contact : null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @return array<string, mixed>
     */
    public function website(): array
    {
        return [
            '@type' => 'WebSite',
            '@id' => route('home').'#website',
            'url' => route('home'),
            'name' => $this->siteName(),
            'inLanguage' => 'ar',
            'publisher' => ['@id' => route('home').'#organization'],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => route('courses').'?search={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }
}
