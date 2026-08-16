<?php

namespace App\Modules\WhatsApp\Services\Meta;

/**
 * An approved Meta template plus the values that fill its body placeholders.
 *
 * Meta's component shape is verbose and easy to get subtly wrong, so building it lives
 * here rather than being hand-assembled at each call site.
 */
class MetaTemplate
{
    /**
     * @param  array<int, string>  $bodyParameters
     */
    public function __construct(
        public readonly string $name,
        public readonly string $language = 'ar',
        public readonly array $bodyParameters = [],
    ) {}

    /**
     * @param  array<int, string>  $parameters
     */
    public function withBodyParameters(array $parameters): self
    {
        return new self($this->name, $this->language, array_values($parameters));
    }

    /**
     * Meta's `components` array. Positional {{1}}, {{2}} … placeholders map to the
     * order of $bodyParameters.
     *
     * @return array<int, array<string, mixed>>
     */
    public function components(): array
    {
        if ($this->bodyParameters === []) {
            return [];
        }

        return [[
            'type' => 'body',
            'parameters' => array_map(
                fn (string $value): array => ['type' => 'text', 'text' => $value],
                $this->bodyParameters,
            ),
        ]];
    }
}
