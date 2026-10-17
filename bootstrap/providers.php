<?php

use App\Modules\WhatsApp\WhatsAppServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FrontendServiceProvider;
use App\Providers\PermissionServiceProvider;

return [
    AppServiceProvider::class,
    PermissionServiceProvider::class,
    FrontendServiceProvider::class,

    // Optional modules. Removing one is deleting its folder and its line here.
    WhatsAppServiceProvider::class,
];
