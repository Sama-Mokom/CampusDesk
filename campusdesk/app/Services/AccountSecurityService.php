<?php

namespace App\Services;

use App\Models\AdministrativeAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountSecurityService
{
    public function invalidateCredentials(User $user, ?string $previousEmail = null): void
    {
        $user->tokens()->delete();
        DB::table('password_reset_tokens')
            ->whereIn('email', array_values(array_unique(array_filter([$previousEmail, $user->email]))))
            ->delete();
        $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();
    }

    public function audit(
        string $action,
        User $subject,
        ?User $actor,
        ?array $before = null,
        ?array $after = null,
        ?array $metadata = null,
    ): AdministrativeAction {
        return AdministrativeAction::create([
            'actor_id' => $actor?->id,
            'action' => $action,
            'subject_type' => User::class,
            'subject_id' => $subject->id,
            'before_values' => $before,
            'after_values' => $after,
            'metadata' => $metadata,
        ]);
    }

    public function safeState(User $user): array
    {
        return [
            'disabled_at' => $user->disabled_at?->toISOString(),
            'role' => $user->role,
        ];
    }
}
