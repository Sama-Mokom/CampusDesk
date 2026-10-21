<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('users.{id}', function (User $user, int|string $id): bool {
    return (int) $user->id === (int) $id;
}, ['guards' => ['sanctum']]);

Broadcast::channel('departments.{id}', function (User $user, int|string $id): bool {
    if ($user->role !== 'staff') {
        return false;
    }

    if ($user->staffProfile?->admin_level === 'super_admin') {
        return true;
    }

    return $user->staffProfile
        ?->departments()
        ->where('departments.id', (int) $id)
        ->exists() ?? false;
}, ['guards' => ['sanctum']]);
