<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AccountSecurityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RotateStagingPersonaPassword extends Command
{
    protected $signature = 'staging:rotate-persona-password {user_id : ID of the retained staging persona}';

    protected $description = 'Securely rotate a retained staging persona password without echoing it';

    public function handle(AccountSecurityService $accountSecurity): int
    {
        if (! $this->laravel->environment('staging')) {
            $this->error('This command may only run when APP_ENV=staging.');

            return self::FAILURE;
        }

        $userId = filter_var($this->argument('user_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($userId === false) {
            $this->error('The user ID must be a positive integer.');

            return self::INVALID;
        }

        $user = User::find($userId);
        if (! $user) {
            $this->error('No account exists for that user ID.');

            return self::FAILURE;
        }
        if ($user->disabled_at !== null) {
            $this->error('The retained persona must be enabled before its password is rotated.');

            return self::FAILURE;
        }

        $password = $this->secret('New password (minimum 16 characters)');
        $confirmation = $this->secret('Confirm new password');

        if (! is_string($password) || mb_strlen($password) < 16) {
            $this->error('The password must contain at least 16 characters.');

            return self::FAILURE;
        }
        if (! hash_equals($password, (string) $confirmation)) {
            $this->error('The passwords do not match.');

            return self::FAILURE;
        }
        if (User::query()->get(['password'])->contains(fn (User $candidate) => Hash::check($password, $candidate->password))) {
            $this->error('Choose a password that is not already used by another account.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($accountSecurity, $password, $userId) {
            $user = User::whereKey($userId)->lockForUpdate()->firstOrFail();
            $user->forceFill(['password' => $password])->save();
            $accountSecurity->invalidateCredentials($user);
            $accountSecurity->audit(
                'user.password_rotated',
                $user,
                null,
                metadata: ['source' => 'staging.retained_persona'],
            );
        });

        $password = null;
        $confirmation = null;
        $this->info('Persona password rotated and existing credentials revoked.');

        return self::SUCCESS;
    }
}
