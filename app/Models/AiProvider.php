<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use App\Services\Ai\AiProviderConfigApplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiProvider extends Model
{
    use AuditsChanges, SoftDeletes;

    public const DRIVERS = [
        'openai' => 'OpenAI',
        'anthropic' => 'Anthropic',
        'gemini' => 'Google Gemini',
        'openrouter' => 'OpenRouter',
        'groq' => 'Groq',
        'mistral' => 'Mistral',
        'deepseek' => 'DeepSeek',
        'xai' => 'xAI',
        'ollama' => 'Ollama',
        'openai-compatible' => 'OpenAI Compatible',
    ];

    protected $fillable = [
        'name',
        'driver',
        'api_key',
        'base_url',
        'default_model',
        'options',
        'is_default',
        'is_active',
    ];

    protected $hidden = [
        'api_key',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'options' => 'array',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => AiProviderConfigApplier::forget());
        static::deleted(fn () => AiProviderConfigApplier::forget());
    }

    public function configKey(): string
    {
        return 'db-'.$this->id;
    }

    public function requiresBaseUrl(): bool
    {
        return in_array($this->driver, ['ollama', 'openai-compatible'], true);
    }

    public function requiresApiKey(): bool
    {
        return $this->driver !== 'ollama';
    }

    /**
     * @return array<string, mixed>
     */
    public function toAiProviderConfig(): array
    {
        $config = [
            'driver' => $this->driver,
            'key' => (string) ($this->api_key ?? ''),
        ];

        if (filled($this->base_url)) {
            $config['url'] = $this->base_url;
        }

        if (is_array($this->options) && $this->options !== []) {
            $config = array_merge($config, $this->options);
        }

        return $config;
    }

    public function setAsDefault(): void
    {
        static::query()
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->forceFill([
            'is_default' => true,
            'is_active' => true,
        ])->save();
    }

    public static function getDefault(): ?self
    {
        return static::query()
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'driver' => $this->driver,
            'driver_label' => self::DRIVERS[$this->driver] ?? $this->driver,
            'base_url' => $this->base_url,
            'default_model' => $this->default_model,
            'options' => $this->options,
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
            'has_api_key' => filled($this->api_key),
            'created_at' => optional($this->created_at)?->format('Y-m-d H:i'),
        ];
    }
}
