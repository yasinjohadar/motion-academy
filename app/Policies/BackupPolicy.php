<?php

namespace App\Policies;

/**
 * Owned via `created_by`: the creator may always edit and delete their own record;
 * acting on someone else's requires admin.backups.edit-any / .delete-any.
 */
class BackupPolicy extends BasePolicy
{
    protected string $resource = 'backups';

    protected ?string $ownerColumn = 'created_by';
}
