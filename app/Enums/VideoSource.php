<?php

namespace App\Enums;

enum VideoSource: string
{
    case Youtube = 'youtube';
    case Vimeo = 'vimeo';
    case Url = 'url';
    case Upload = 'upload';

    public function label(): string
    {
        return match ($this) {
            self::Youtube => 'YouTube',
            self::Vimeo => 'Vimeo',
            self::Url => 'رابط مباشر',
            self::Upload => 'رفع ملف',
        };
    }

    /**
     * Pattern a pasted link must match for this source; null when any URL (or a file) is accepted.
     */
    public function urlPattern(): ?string
    {
        return match ($this) {
            self::Youtube => '#^https?://(www\.|m\.)?(youtube\.com/(watch\?v=|embed/|shorts/)|youtu\.be/)[\w-]{6,}#i',
            self::Vimeo => '#^https?://(www\.|player\.)?vimeo\.com/(video/)?\d+#i',
            default => null,
        };
    }

    /**
     * Turns a YouTube/Vimeo watch link into its embeddable player URL.
     */
    public function embedUrl(?string $url): ?string
    {
        if (! filled($url)) {
            return null;
        }

        return match ($this) {
            self::Youtube => preg_match('#(?:v=|youtu\.be/|embed/|shorts/)([\w-]{6,})#', $url, $m)
                ? 'https://www.youtube.com/embed/'.$m[1]
                : null,
            self::Vimeo => preg_match('#vimeo\.com/(?:video/)?(\d+)#', $url, $m)
                ? 'https://player.vimeo.com/video/'.$m[1]
                : null,
            default => null,
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case) => ['value' => $case->value, 'label' => $case->label()], self::cases());
    }
}
