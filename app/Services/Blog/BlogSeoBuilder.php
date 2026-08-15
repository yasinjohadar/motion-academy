<?php

namespace App\Services\Blog;

use App\Models\BlogCategory;
use Illuminate\Support\Str;

class BlogSeoBuilder
{
    /**
     * Expand a draft into SEO/OG/Twitter/Schema fields.
     *
     * @param  array{title?: string, content?: string, excerpt?: string|null}  $draft
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function expandDraft(array $draft, array $options = []): array
    {
        $title = trim((string) ($draft['title'] ?? ''));
        $content = (string) ($draft['content'] ?? '');
        $excerpt = isset($draft['excerpt']) ? trim((string) $draft['excerpt']) : '';
        if ($excerpt === '') {
            $excerpt = Str::limit(preg_replace('/\s+/', ' ', strip_tags($content)) ?: '', 160);
        }

        $slug = BlogCategory::slugify($title !== '' ? $title : 'post');
        $lang = $options['language'] ?? 'ar';
        $locale = $lang === 'en' ? 'en_US' : 'ar_SA';
        $plainExcerpt = strip_tags($excerpt);

        $result = [
            'title' => $title,
            'slug' => $slug,
            'excerpt' => $excerpt,
            'content' => $content,
            'reading_time' => max(1, (int) ceil(str_word_count(strip_tags($content)) / 200)),
            'canonical_url' => url('/blog/'.$slug),
            'language' => $lang,
        ];

        if ($options['generate_seo'] ?? true) {
            $result['meta_title'] = Str::limit($title, 60, '');
            $result['meta_description'] = Str::limit($plainExcerpt, 160, '');
            $result['meta_keywords'] = Str::limit($title, 100, '');
            $result['focus_keyword'] = Str::limit($title, 50, '');
            $result['focus_keyword_synonyms'] = '';
        }

        if ($options['generate_og'] ?? true) {
            $result['og_title'] = $title;
            $result['og_description'] = Str::limit($plainExcerpt, 200, '');
            $result['og_type'] = 'article';
            $result['og_locale'] = $locale;
        }

        if ($options['generate_twitter'] ?? true) {
            $result['twitter_card'] = 'summary_large_image';
            $result['twitter_title'] = Str::limit($title, 70, '');
            $result['twitter_description'] = Str::limit($plainExcerpt, 200, '');
        }

        if ($options['generate_schema'] ?? true) {
            $result['schema_type'] = 'BlogPosting';
            $result['schema_headline'] = $title;
            $result['schema_description'] = Str::limit($plainExcerpt, 300, '');
        }

        return $result;
    }
}
