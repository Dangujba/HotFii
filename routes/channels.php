<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('organizations.{organizationUuid}', function ($user, string $organizationUuid) {
    return $user->is_platform_admin
        || $user->organizations()->where('uuid', $organizationUuid)->exists();
});

Broadcast::channel('users.{userUuid}', fn ($user, string $userUuid) => $user->uuid === $userUuid);