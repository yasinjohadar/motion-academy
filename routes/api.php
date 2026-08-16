<?php

use App\Modules\WhatsApp\Controllers\Api\EvolutionWebhookController;
use App\Modules\WhatsApp\Controllers\Api\MetaWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/evolution/{instance?}', [EvolutionWebhookController::class, 'handle'])
    ->name('api.webhooks.evolution');

// Meta Cloud API webhook: GET performs Meta's subscription handshake, POST receives
// events. Both are signature/token protected inside the controller.
Route::get('/webhooks/meta', [MetaWebhookController::class, 'verify'])->name('api.webhooks.meta.verify');
Route::post('/webhooks/meta', [MetaWebhookController::class, 'handle'])->name('api.webhooks.meta');
