<?php

namespace App\Enums;

enum CourseLevel: string
{
    case Beginner = 'beginner';
    case Intermediate = 'intermediate';
    case Advanced = 'advanced';
    case All = 'all';

    public function label(): string
    {
        return match ($this) {
            self::Beginner => 'مبتدئ',
            self::Intermediate => 'متوسط',
            self::Advanced => 'متقدم',
            self::All => 'جميع المستويات',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Beginner => 'admin-badge-success',
            self::Intermediate => 'admin-badge-info',
            self::Advanced => 'admin-badge-danger',
            self::All => 'admin-badge-muted',
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
