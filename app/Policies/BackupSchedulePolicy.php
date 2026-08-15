<?php

namespace App\Policies;

/**
 * Owned via `created_by`: the creator may always edit and delete their own record;
 * acting on someone else's requires admin.backup-schedules.edit-any / .delete-any.
 */
class BackupSchedulePolicy extends BasePolicy
{
    protected string $resource = 'backup-schedules';

    protected ?string $ownerColumn = 'created_by';
}
