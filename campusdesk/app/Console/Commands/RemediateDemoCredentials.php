<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AccountSecurityService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class RemediateDemoCredentials extends Command
{
    protected $signature = 'staging:remediate-demo-credentials
                            {--dry-run : Report vulnerable account counts without changing data}
                            {--apply : Apply the remediation after interactive confirmation}';

    protected $description = 'Disable staging accounts that still use the known demonstration password';

    public function handle(AccountSecurityService $accountSecurity): int
    {
        if (! $this->laravel->environment('staging')) {
            $this->error('This command may only run when APP_ENV=staging.');

            return self::FAILURE;
        }

        if ($this->option('apply') && $this->option('dry-run')) {
            $this->error('Choose either --dry-run or --apply, not both.');

            return self::INVALID;
        }

        $affected = $this->vulnerableUsers();
        $this->renderSummary($affected);

        if ($affected->contains(fn (User $user) => $user->staffProfile?->admin_level === 'super_admin')) {
            $this->error('Remediation aborted: at least one affected account is a Super Admin.');

            return self::FAILURE;
        }

        if (! $this->option('apply')) {
            $this->info('Dry run only; no accounts were changed.');

            return self::SUCCESS;
        }

        if ($affected->isEmpty()) {
            $this->info('No vulnerable accounts require remediation.');

            return self::SUCCESS;
        }

        if (! $this->confirm('Disable and rotate credentials for all affected accounts?', false)) {
            $this->warn('Remediation cancelled; no accounts were changed.');

            return self::SUCCESS;
        }

        try {
            $changed = DB::transaction(function () use ($affected, $accountSecurity): int {
                $users = User::with('staffProfile')
                    ->whereKey($affected->pluck('id'))
                    ->lockForUpdate()
                    ->get()
                    ->filter(fn (User $user) => Hash::check('password', $user->password));

                if ($users->contains(fn (User $user) => $user->staffProfile?->admin_level === 'super_admin')) {
                    throw new RuntimeException('A Super Admin became eligible during remediation.');
                }

                foreach ($users as $user) {
                    $before = $accountSecurity->safeState($user);
                    $user->forceFill([
                        'password' => Str::password(64),
                        'disabled_at' => now(),
                    ])->save();
                    $accountSecurity->invalidateCredentials($user);
                    $accountSecurity->audit(
                        'demo_credentials.remediated',
                        $user,
                        null,
                        $before,
                        $accountSecurity->safeState($user),
                        ['source' => 'staging.remediate_demo_credentials'],
                    );
                }

                return $users->count();
            });
        } catch (RuntimeException $exception) {
            $this->error('Remediation aborted: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Remediation complete. Accounts changed: {$changed}.");

        return self::SUCCESS;
    }

    /** @return Collection<int, User> */
    private function vulnerableUsers(): Collection
    {
        return User::with('staffProfile')
            ->get()
            ->filter(fn (User $user) => Hash::check('password', $user->password))
            ->values();
    }

    /** @param Collection<int, User> $users */
    private function renderSummary(Collection $users): void
    {
        $counts = $users->countBy(fn (User $user) => match ($user->staffProfile?->admin_level) {
            'super_admin' => 'Super Admin',
            'dept_admin' => 'Department Administrator',
            default => $user->role === 'student' ? 'Student' : 'Staff',
        });

        $this->line('Affected account summary:');
        foreach (['Student', 'Staff', 'Department Administrator', 'Super Admin'] as $role) {
            $this->line("  {$role}: ".($counts[$role] ?? 0));
        }
        $this->line('  Total: '.$users->count());
    }
}
