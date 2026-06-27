<?php

namespace App\Services;

use App\Events\ObimsNotificationCreated;
use App\Models\ObimsNotification;
use App\Models\User;

class NotificationService
{
    public function send(User $user, string $type, string $title, string $message, array $data = []): ObimsNotification
    {
        $notification = ObimsNotification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
        ]);

        ObimsNotificationCreated::dispatch($notification);

        return $notification;
    }
}
