<?php

namespace App\Policies;

/**
 * Slides are shared site content with no owner: the plain admin.hero-slides.*
 * permissions decide everything.
 */
class HeroSlidePolicy extends BasePolicy
{
    protected string $resource = 'hero-slides';
}
