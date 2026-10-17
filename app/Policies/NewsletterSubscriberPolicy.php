<?php

namespace App\Policies;

/**
 * Subscribers have no owner: the permission alone decides.
 */
class NewsletterSubscriberPolicy extends BasePolicy
{
    protected string $resource = 'newsletter-subscribers';
}
