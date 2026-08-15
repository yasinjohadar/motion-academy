<?php

namespace App\Services\Blog;

use App\Ai\Agents\BlogDraftAgent;
use App\Models\AiProvider;
use App\Models\BlogCategory;
use App\Services\Ai\AiProviderConfigApplier;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;
use Throwable;

class BlogAiGenerator
{
    public function __construct(private readonly BlogSeoBuilder $seoBuilder) {}

    /**
     * @param  array{
     *     content_length?: string,
     *     tone?: string,
     *     language?: string,
     *     category_id?: int|null,
     *     generate_seo?: bool,
     *     generate_og?: bool,
     *     generate_twitter?: bool,
     *     generate_schema?: bool
     * }  $options
     * @return array<string, mixed>
     */
    public function generate(string $topic, array $options = []): array
    {
        AiProviderConfigApplier::refresh();

        $default = AiProvider::getDefault();
        if (! $default) {
            throw new RuntimeException('لا يوجد مزود ذكاء اصطناعي افتراضي. أضفه من الإعدادات واختبر الاتصال أولاً.');
        }

        $category = null;
        if (! empty($options['category_id'])) {
            $category = BlogCategory::query()->find($options['category_id']);
        }

        $prompt = $this->buildPrompt($topic, $options, $category);
        $providerName = $default->configKey();
        $model = filled($default->default_model) ? $default->default_model : null;

        try {
            /** @var StructuredAgentResponse $response */
            $response = (new BlogDraftAgent)->prompt(
                $prompt,
                provider: $providerName,
                model: $model,
                timeout: 180,
            );

            $draft = $response->toArray();

            if (blank($draft['title'] ?? null) || blank($draft['content'] ?? null)) {
                throw new RuntimeException('المزود لم يُرجع مسودة صالحة (عنوان ومحتوى).');
            }

            return $this->seoBuilder->expandDraft($draft, [
                ...$options,
                'language' => $options['language'] ?? 'ar',
            ]);
        } catch (Throwable $e) {
            throw new RuntimeException('فشل توليد التدوينة: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function buildPrompt(string $topic, array $options, ?BlogCategory $category): string
    {
        $topic = Str::limit(trim($topic), 4000);

        $lengthMap = [
            'short' => 'roughly 500–800 words',
            'medium' => 'roughly 1000–1500 words',
            'long' => 'roughly 2000–3000 words',
        ];
        $toneMap = [
            'professional' => 'professional and clear',
            'friendly' => 'friendly and approachable',
            'technical' => 'technical and detailed',
            'casual' => 'casual and conversational',
            'formal' => 'formal and polished',
        ];

        $contentLength = $options['content_length'] ?? 'medium';
        $tone = $options['tone'] ?? 'professional';
        $language = $options['language'] ?? 'ar';

        $lengthText = $lengthMap[$contentLength] ?? $lengthMap['medium'];
        $toneText = $toneMap[$tone] ?? $toneMap['professional'];
        $langLine = $language === 'en'
            ? 'Write the title, excerpt, and full article body in English.'
            : 'Write the title, excerpt, and full article body in Arabic (Modern Standard Arabic).';
        $categoryLine = $category
            ? "The post fits the blog category: {$category->name}.\n"
            : '';

        return <<<PROMPT
Write a complete blog post draft as structured fields (title, content HTML, excerpt).

Topic: {$topic}

{$categoryLine}{$langLine}
Tone: {$toneText}.
Target length: {$lengthText}.

Content requirements:
- Use semantic HTML in "content": h2, h3, p, ul, ol, strong, em where appropriate.
- Include an engaging introduction, clear sections with subheadings, and a short conclusion.
- "excerpt" should be a concise plain-text summary appropriate to the language.

PROMPT;
    }
}
