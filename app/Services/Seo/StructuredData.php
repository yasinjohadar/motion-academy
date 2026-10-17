<?php

namespace App\Services\Seo;

use App\Models\BlogPost;
use App\Models\Course;
use Illuminate\Support\Str;

/**
 * schema.org nodes for the public pages, shaped for Google's rich results:
 * Course (course info), BlogPosting (article), BreadcrumbList and ItemList.
 *
 * Every node leaves out what it does not know instead of sending empty values,
 * which the Rich Results Test reports as errors.
 */
class StructuredData
{
    /**
     * @param  array<int, array{name: string, url: string}>  $trail
     * @return array<string, mixed>
     */
    public static function breadcrumbs(array $trail, string $pageUrl): array
    {
        return [
            '@type' => 'BreadcrumbList',
            '@id' => $pageUrl.'#breadcrumb',
            'itemListElement' => collect($trail)->values()->map(fn (array $crumb, int $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $crumb['name'],
                'item' => url($crumb['url']),
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function course(Course $course, SiteSeo $site): array
    {
        $url = route('courses.show', $course->slug);
        $workload = self::isoDuration($course->duration_seconds);
        $instructor = $course->instructor?->name;

        $instance = array_filter([
            '@type' => 'CourseInstance',
            'courseMode' => 'Online',
            'courseWorkload' => $workload ?? 'PT1H',
            'inLanguage' => $course->language ?: 'ar',
            'instructor' => $instructor ? ['@type' => 'Person', 'name' => $instructor] : null,
        ]);

        $offer = array_filter([
            '@type' => 'Offer',
            'category' => $course->is_free ? 'Free' : 'Paid',
            'price' => number_format($course->effectivePrice(), 2, '.', ''),
            'priceCurrency' => $course->currency ?: 'USD',
            'availability' => $course->isFull() ? 'https://schema.org/SoldOut' : 'https://schema.org/InStock',
            'url' => $url,
            'priceValidUntil' => $course->isOnSale() ? $course->sale_ends_at?->toDateString() : null,
        ], fn ($value) => $value !== null);

        return array_filter([
            '@type' => 'Course',
            '@id' => $url.'#course',
            'name' => $course->title,
            'description' => self::plain($course->meta_description ?: $course->short_description ?: $course->description, 500)
                ?: $course->title,
            'url' => $url,
            'image' => $site->absolute($course->og_image ?: $course->thumbnailUrl()),
            'inLanguage' => $course->language ?: 'ar',
            'provider' => ['@id' => route('home').'#organization'],
            'educationalLevel' => $course->level->label(),
            'about' => $course->category?->name,
            'teaches' => array_values(array_filter((array) $course->learning_outcomes)) ?: null,
            'coursePrerequisites' => array_values(array_filter((array) $course->requirements)) ?: null,
            'timeRequired' => $workload,
            'numberOfLessons' => $course->lessons_count ?: null,
            'isAccessibleForFree' => (bool) $course->is_free,
            'datePublished' => $course->published_at?->toIso8601String(),
            'dateModified' => $course->updated_at?->toIso8601String(),
            'hasCourseInstance' => [$instance],
            'offers' => [$offer],
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @return array<string, mixed>
     */
    public static function blogPosting(BlogPost $post, SiteSeo $site): array
    {
        $url = route('blog.show', $post->slug);
        $image = $site->absolute($post->schema_image ?: $post->og_image ?: $post->featuredImageUrl());
        $authorName = $post->schema_author_name ?: $post->author?->name;

        return array_filter([
            '@type' => in_array($post->schema_type, ['Article', 'BlogPosting', 'NewsArticle', 'TechArticle'], true)
                ? $post->schema_type
                : 'BlogPosting',
            '@id' => $url.'#article',
            'headline' => Str::limit($post->schema_headline ?: $post->title, 110, ''),
            'description' => self::plain($post->schema_description ?: $post->meta_description ?: $post->excerpt, 300),
            'image' => $image ? [$image] : null,
            'url' => $url,
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
            'inLanguage' => $post->language ?: 'ar',
            'datePublished' => ($post->schema_published_time ?: $post->published_at)?->toIso8601String(),
            'dateModified' => ($post->schema_modified_time ?: $post->updated_at)?->toIso8601String(),
            'author' => $authorName ? array_filter([
                '@type' => 'Person',
                'name' => $authorName,
                'url' => $post->schema_author_url,
            ]) : null,
            'publisher' => ['@id' => route('home').'#organization'],
            'articleSection' => $post->category?->name,
            'keywords' => $post->relationLoaded('tags') && $post->tags->isNotEmpty()
                ? $post->tags->pluck('name')->implode(', ')
                : ($post->meta_keywords ?: null),
            'wordCount' => filled($post->content) ? self::wordCount((string) $post->content) : null,
            'timeRequired' => $post->reading_time ? 'PT'.$post->reading_time.'M' : null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * A listing page: the items it links to, in order.
     *
     * @param  iterable<int, array{name: string, url: string}>  $items
     * @return array<string, mixed>
     */
    public static function itemList(iterable $items, string $pageUrl, int $offset = 0): array
    {
        return [
            '@type' => 'ItemList',
            '@id' => $pageUrl.'#list',
            'itemListElement' => collect($items)->values()->map(fn (array $item, int $i) => [
                '@type' => 'ListItem',
                'position' => $offset + $i + 1,
                'name' => $item['name'],
                'url' => $item['url'],
            ])->all(),
        ];
    }

    /**
     * 5400 seconds → "PT1H30M".
     */
    public static function isoDuration(?int $seconds): ?string
    {
        if (! $seconds || $seconds <= 0) {
            return null;
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return 'PT'.($hours ? $hours.'H' : '').($minutes || ! $hours ? max(1, $minutes).'M' : '');
    }

    public static function plain(?string $html, int $limit): ?string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        return $text === '' ? null : Str::limit($text, $limit, '…');
    }

    private static function wordCount(string $html): int
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags($html)));

        return $text === '' ? 0 : count(explode(' ', $text));
    }
}
