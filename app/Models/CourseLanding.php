<?php

namespace App\Models;

use App\Enums\VideoSource;
use App\Models\Concerns\AuditsChanges;
use App\Support\SocialPlatforms;
use Database\Factories\CourseLandingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A course's standalone advertising page (/l/{slug}). Shared details (title,
 * cover, price, stats, instructor) are always read from the course, so the two
 * pages never disagree; the fields here exist only on the landing page. Any
 * block left empty is simply not rendered.
 *
 * JSON casts are invisible to static analysis without these.
 *
 * @property array<int, array{title?: string|null, url: string}>|null $videos
 * @property array<int, string>|null $goals
 * @property array<int, array{title: string, text?: string|null}>|null $features
 * @property array<int, array{name: string, role?: string|null, text?: string|null, video_url?: string|null}>|null $testimonials
 * @property array<int, array{question: string, answer: string}>|null $faqs
 * @property array<int, array{platform: string, url: string}>|null $social_links
 */
class CourseLanding extends Model
{
    /** @use HasFactory<CourseLandingFactory> */
    use AuditsChanges, HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_published' => false,
        'show_price' => true,
        'allow_indexing' => false,
    ];

    protected $fillable = [
        'course_id',
        'is_published',
        'tagline',
        'description',
        'videos',
        'goals',
        'features',
        'testimonials',
        'faqs',
        'social_links',
        'show_price',
        'cta_label',
        'cta_url',
        'meta_title',
        'meta_description',
        'allow_indexing',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'videos' => 'array',
            'goals' => 'array',
            'features' => 'array',
            'testimonials' => 'array',
            'faqs' => 'array',
            'social_links' => 'array',
            'show_price' => 'boolean',
            'allow_indexing' => 'boolean',
        ];
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * The YouTube video id of a watch/short/embed link, or null.
     */
    public static function youtubeId(?string $url): ?string
    {
        $embed = VideoSource::Youtube->embedUrl($url);

        return $embed ? basename($embed) : null;
    }

    /**
     * Videos with a recognised YouTube id, ready to render.
     *
     * @return array<int, array{title: string|null, id: string}>
     */
    public function youtubeVideos(): array
    {
        $videos = [];

        foreach ($this->videos ?? [] as $video) {
            if ($id = self::youtubeId($video['url'] ?? null)) {
                $videos[] = ['title' => $video['title'] ?? null, 'id' => $id];
            }
        }

        return $videos;
    }

    /**
     * Testimonials with their YouTube id resolved (null for text-only ones).
     *
     * @return array<int, array{name: string, role: string|null, text: string|null, video_id: string|null}>
     */
    public function testimonialCards(): array
    {
        return array_map(fn (array $item) => [
            'name' => $item['name'],
            'role' => $item['role'] ?? null,
            'text' => $item['text'] ?? null,
            'video_id' => self::youtubeId($item['video_url'] ?? null),
        ], $this->testimonials ?? []);
    }

    /**
     * @return array<int, array{platform: string, label: string, icon: string, color: string, url: string}>
     */
    public function socialButtons(): array
    {
        return SocialPlatforms::buttons($this->social_links);
    }

    /**
     * The first WhatsApp link, which the page promotes to a large animated button.
     */
    public function whatsappUrl(): ?string
    {
        foreach ($this->socialButtons() as $button) {
            if ($button['platform'] === 'whatsapp') {
                return $button['url'];
            }
        }

        return null;
    }

    /**
     * schema.org FAQPage data, so search engines can show the questions.
     *
     * @return array<string, mixed>|null
     */
    public function faqSchema(): ?array
    {
        if (empty($this->faqs)) {
            return null;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn (array $faq) => [
                '@type' => 'Question',
                'name' => $faq['question'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['answer']],
            ], $this->faqs),
        ];
    }

    /**
     * Where the call-to-action goes: the custom URL, or the course's enrolment box.
     */
    public function ctaUrl(): string
    {
        return $this->cta_url ?: route('courses.show', $this->course->slug).'#enroll';
    }

    public function ctaLabel(): string
    {
        return $this->cta_label ?: 'سجّل في الدورة الآن';
    }

    public function hasDescription(): bool
    {
        return filled(trim(strip_tags((string) $this->description, '<img><iframe><video>')));
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminArray(): array
    {
        return [
            'is_published' => $this->is_published,
            'tagline' => $this->tagline,
            'description' => $this->description,
            'videos' => array_values($this->videos ?? []),
            'goals' => array_values($this->goals ?? []),
            'features' => array_values($this->features ?? []),
            'testimonials' => array_values($this->testimonials ?? []),
            'faqs' => array_values($this->faqs ?? []),
            'social_links' => array_values($this->social_links ?? []),
            'show_price' => $this->show_price,
            'cta_label' => $this->cta_label,
            'cta_url' => $this->cta_url,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'allow_indexing' => $this->allow_indexing,
        ];
    }
}
