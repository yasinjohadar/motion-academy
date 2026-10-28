<?php

namespace App\Services\Frontend;

/**
 * How the home page team section is titled, which members it picks, how many,
 * how it lays them out, and where its call to action points.
 *
 * @method array{eyebrow: string, title: string, subtitle: string, limit: int, order: string, layout: string, columns: int, show_rating: bool, show_social: bool, autoplay: bool, cta_label: string, cta_url: string} all()
 */
class TeamSettings extends GroupedSettings
{
    public const GROUP = 'team';

    public const FIELDS = [
        'eyebrow' => ['type' => 'string', 'default' => 'شركاء النجاح'],
        'title' => ['type' => 'string', 'default' => 'فريق الخبراء'],
        'subtitle' => ['type' => 'string', 'default' => 'مجموعة من أفضل المدربين في الوطن العربي، يجمعهم شغف نقل خبراتهم العملية.'],
        'limit' => ['type' => 'integer', 'default' => 0],
        'order' => ['type' => 'string', 'default' => 'manual'],
        'layout' => ['type' => 'string', 'default' => 'slider'],
        'columns' => ['type' => 'integer', 'default' => 4],
        'show_rating' => ['type' => 'boolean', 'default' => true],
        'show_social' => ['type' => 'boolean', 'default' => true],
        'autoplay' => ['type' => 'boolean', 'default' => true],
        'cta_label' => ['type' => 'string', 'default' => 'تعرف على كامل الفريق'],
        'cta_url' => ['type' => 'string', 'default' => '/who-we-are'],
    ];

    /** @var array<string, string> */
    public const ORDERS = [
        'manual' => 'الترتيب اليدوي',
        'rating' => 'الأعلى تقييماً',
        'random' => 'عشوائي في كل زيارة',
    ];

    /** @var array<string, string> */
    public const LAYOUTS = [
        'slider' => 'سلايدر متحرك',
        'grid' => 'شبكة بطاقات',
    ];

    public const COLUMNS = [2, 3, 4];
}
