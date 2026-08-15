<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/**
 * Notifications broadcast on a private per-user channel named after the notifiable
 * class and id — `App.Models.User.{id}` — which is what Echo's `notification()`
 * listener subscribes to in resources/js/Components/Admin/NotificationBell.jsx.
 */
Broadcast::channel('App.Models.User.{id}', function (User $user, int $id): bool {
    return $user->id === $id;
});
