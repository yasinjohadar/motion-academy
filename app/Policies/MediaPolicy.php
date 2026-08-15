<?php

namespace App\Policies;

/**
 * Owned via `uploaded_by`: the creator may always edit and delete their own record;
 * acting on someone else's requires admin.media.edit-any / .delete-any.
 */
class MediaPolicy extends BasePolicy
{
    protected string $resource = 'media';

    protected ?string $ownerColumn = 'uploaded_by';
}
