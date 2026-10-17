<?php

namespace App\Enums;

enum CourseStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'مسودة',
            self::Published => 'منشورة',
            self::Archived => 'مؤرشفة',
        };
    }

    /**
     * Matches the `admin-badge-*` classes used across the admin tables.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft => 'admin-badge-warning',
            self::Published => 'admin-badge-success',
            self::Archived => 'admin-badge-muted',
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
