<?php

namespace App\Services\Frontend;

/**
 * How the home page testimonials section is titled, which testimonials it
 * picks, how many, and how it lays them out.
 *
 * @method array{eyebrow: string, title: string, subtitle: string, limit: int, order: string, featured_only: bool, layout: string, columns: int, show_rating: bool, autoplay: bool} all()
 */
class TestimonialSettings extends GroupedSettings
{
    public const GROUP = 'testimonials';

    public const FIELDS = [
        'eyebrow' => ['type' => 'string', 'default' => 'قصص نجاح'],
        'title' => ['type' => 'string', 'default' => 'آراء المتعلمين'],
        'subtitle' => ['type' => 'string', 'default' => 'تجارب حقيقية من متعلمين غيّروا مساراتهم المهنية بفضل محتوى عملي ومحدّث'],
        'limit' => ['type' => 'integer', 'default' => 3],
        'order' => ['type' => 'string', 'default' => 'manual'],
        'featured_only' => ['type' => 'boolean', 'default' => false],
        'layout' => ['type' => 'string', 'default' => 'grid'],
        'columns' => ['type' => 'integer', 'default' => 3],
        'show_rating' => ['type' => 'boolean', 'default' => true],
        'autoplay' => ['type' => 'boolean', 'default' => true],
    ];

    /** @var array<string, string> */
    public const ORDERS = [
        'manual' => 'الترتيب اليدوي',
        'latest' => 'الأحدث أولاً',
        'rating' => 'الأعلى تقييماً',
        'random' => 'عشوائي في كل زيارة',
    ];

    /** @var array<string, string> */
    public const LAYOUTS = [
        'grid' => 'شبكة بطاقات',
        'slider' => 'سلايدر متحرك',
    ];

    public const COLUMNS = [2, 3, 4];
}
