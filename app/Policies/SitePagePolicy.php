<?php

namespace App\Policies;

/**
 * Owned via `created_by`: the creator may always edit and delete their own record;
 * acting on someone else's requires admin.site-pages.edit-any / .delete-any.
 */
class SitePagePolicy extends BasePolicy
{
    protected string $resource = 'site-pages';

    protected ?string $ownerColumn = 'created_by';
}
