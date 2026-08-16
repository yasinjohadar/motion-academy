<?php

namespace App\Modules\WhatsApp;

use App\Modules\WhatsApp\Events\WhatsAppMessageReceived;
use App\Modules\WhatsApp\Listeners\NotifyOnWhatsAppMessageReceived;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Everything the WhatsApp module needs in order to boot.
 *
 * A module that lives outside app/Listeners loses Laravel's listener auto-discovery,
 * which scans that one directory. Moving the module silently unhooked
 * WhatsAppMessageReceived: inbound messages still arrived and were still stored, but
 * nobody was notified about them, and no test noticed. Registering explicitly here is
 * the price of the module being self-contained — and it is the honest trade, because
 * this file is now the single place that says what the module wires up.
 *
 * Removing the module means deleting app/Modules/WhatsApp and this provider's line in
 * bootstrap/providers.php.
 */
class WhatsAppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(WhatsAppMessageReceived::class, NotifyOnWhatsAppMessageReceived::class);
    }
}
