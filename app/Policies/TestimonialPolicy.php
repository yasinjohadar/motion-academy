<?php

namespace App\Policies;

/**
 * Testimonials are shared site content with no owner: the plain
 * admin.testimonials.* permissions decide everything.
 */
class TestimonialPolicy extends BasePolicy
{
    protected string $resource = 'testimonials';
}
