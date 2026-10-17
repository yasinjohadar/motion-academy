<?php

namespace App\Enums;

enum LessonType: string
{
    case Video = 'video';
    case Text = 'text';
    case File = 'file';

    public function label(): string
    {
        return match ($this) {
            self::Video => 'فيديو',
            self::Text => 'مقال نصي',
            self::File => 'ملف للتحميل',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Video => 'ri-play-circle-line',
            self::Text => 'ri-file-text-line',
            self::File => 'ri-attachment-2',
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
     * @return array<int, array{value: string, label: string, icon: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => ['value' => $case->value, 'label' => $case->label(), 'icon' => $case->icon()],
            self::cases(),
        );
    }
}
