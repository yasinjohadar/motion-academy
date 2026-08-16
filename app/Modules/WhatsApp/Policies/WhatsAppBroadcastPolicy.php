<?php

namespace App\Modules\WhatsApp\Policies;

use App\Policies\BasePolicy;

/**
 * Owned via `created_by`: the creator may always edit and delete their own record;
 * acting on someone else's requires admin.whatsapp-campaigns.edit-any / .delete-any.
 */
class WhatsAppBroadcastPolicy extends BasePolicy
{
    protected string $resource = 'whatsapp-campaigns';

    protected ?string $ownerColumn = 'created_by';
}
