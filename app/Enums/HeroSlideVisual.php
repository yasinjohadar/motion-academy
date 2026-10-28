<?php

namespace App\Enums;

/**
 * What a hero slide shows beside its copy. The four built-in visuals are the
 * animated HTML/CSS scenes from the original design; `Image` shows an uploaded
 * or library image, and `None` centres the copy on its own.
 */
enum HeroSlideVisual: string
{
    case Editor = 'editor';
    case Curve = 'curve';
    case Courses = 'courses';
    case Preview = 'preview';
    case Image = 'image';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Editor => 'محرر الحركة (رسوم متحركة)',
            self::Curve => 'منحنى السرعة (رسوم متحركة)',
            self::Courses => 'بطاقات أحدث الدورات',
            self::Preview => 'هاتف معاينة الدروس',
            self::Image => 'صورة مخصصة',
            self::None => 'بدون عنصر مرئي',
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
