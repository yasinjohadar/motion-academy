<?php

namespace App\Services\Frontend;

/**
 * How the home hero slider behaves. Defaults match the original hard-coded
 * behaviour.
 *
 * @method array{autoplay: bool, autoplay_delay: int, speed: int, effect: string, loop: bool, pause_on_hover: bool, show_navigation: bool, show_pagination: bool, show_progress: bool, max_slides: int} all()
 */
class HeroSliderSettings extends GroupedSettings
{
    public const GROUP = 'hero';

    public const FIELDS = [
        'autoplay' => ['type' => 'boolean', 'default' => true],
        'autoplay_delay' => ['type' => 'integer', 'default' => 6000],
        'speed' => ['type' => 'integer', 'default' => 900],
        'effect' => ['type' => 'string', 'default' => 'fade'],
        'loop' => ['type' => 'boolean', 'default' => true],
        'pause_on_hover' => ['type' => 'boolean', 'default' => true],
        'show_navigation' => ['type' => 'boolean', 'default' => true],
        'show_pagination' => ['type' => 'boolean', 'default' => true],
        'show_progress' => ['type' => 'boolean', 'default' => true],
        'max_slides' => ['type' => 'integer', 'default' => 0],
    ];

    public const EFFECTS = ['fade', 'slide', 'creative'];
}
