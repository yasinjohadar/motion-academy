<?php

namespace App\Policies;

/**
 * Owned via `created_by`: the creator may always edit and delete their own record;
 * acting on someone else's requires admin.app-storage.edit-any / .delete-any.
 */
class AppStorageConfigPolicy extends BasePolicy
{
    protected string $resource = 'app-storage';

    protected ?string $ownerColumn = 'created_by';
}
