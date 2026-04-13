<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\User;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('is-student', fn(User $user) => $user->role === 'student');
        Gate::define('is-staff', fn(User $user) => $user->role === 'staff');
        Gate::define('is-dept-admin', fn(User $user) => 
        $user->role === 'staff' && $user->staffProfile?->admin_level === 'dept_admin');
        Gate::define('is-super-admin', fn(User $user) => 
        $user->role === 'staff' && $user->staffProfile?->admin_level === 'super_admin');

        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url')."/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });
    }
}
