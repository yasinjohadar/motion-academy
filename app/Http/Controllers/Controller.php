<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // Provides $this->authorize(), used alongside the permission middleware:
    // the middleware guards the screen, the policy guards the individual record.
    use AuthorizesRequests;
}
