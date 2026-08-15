<?php

namespace App\Policies;

/**
 * No owner column — permission alone decides. Declared explicitly because a model
 * without a policy is denied by Gate::allows(), which would hide its buttons.
 */
class AiProviderPolicy extends BasePolicy
{
    protected string $resource = 'ai-providers';
}
