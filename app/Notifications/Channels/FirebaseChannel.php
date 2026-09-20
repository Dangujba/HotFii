<?php

namespace App\Notifications\Channels;

use App\Models\NotificationPreference;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\HotFiiAlert;
use App\Services\Notifications\FirebaseCloudMessaging;
use Illuminate\Notifications\Notification;

class FirebaseChannel
{
    public function __construct(private readonly FirebaseCloudMessaging $firebase) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof User
            || ! $notification instanceof HotFiiAlert
            || ! $this->firebase->configured()) {
            return;
        }

        $organization = $notification->organizationId
            ? Organization::where('uuid', $notification->organizationId)->first()
            : null;
        $preference = $organization
            ? NotificationPreference::whereBelongsTo($notifiable)
                ->whereBelongsTo($organization)
                ->first()
            : null;

        if ($preference && ! $preference->allows($notification->category)) {
            return;
        }

        $message = $notification->toFirebase($notifiable);
        $notifiable->mobileDevices()
            ->whereNotNull('push_token')
            ->get()
            ->each(function ($device) use ($message): void {
                if (! $this->firebase->send((string) $device->push_token, $message)) {
                    $device->forceFill(['push_token' => null, 'push_token_hash' => null])->save();
                }
            });
    }
}
