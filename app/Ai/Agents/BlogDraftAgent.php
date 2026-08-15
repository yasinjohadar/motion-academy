<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[MaxTokens(8192)]
#[Temperature(0.7)]
class BlogDraftAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return 'You draft blog posts. Output must match the JSON schema. Use semantic HTML for content (h2, h3, p, ul, ol, strong, em). Do not wrap the JSON in markdown fences.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->required(),
            'content' => $schema->string()->required(),
            'excerpt' => $schema->string()->nullable(),
        ];
    }
}
