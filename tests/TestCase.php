<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * A control-panel account (a non-student role, so it gets past the /admin
     * guard) that holds none of the permissions a test is checking.
     */
    protected function panelUserWithoutPermissions(): User
    {
        return User::factory()->staff()->create();
    }
}
