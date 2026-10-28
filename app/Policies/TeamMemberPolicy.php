<?php

namespace App\Policies;

/**
 * Team members are shared site content with no owner: the plain
 * admin.team-members.* permissions decide everything.
 */
class TeamMemberPolicy extends BasePolicy
{
    protected string $resource = 'team-members';
}
