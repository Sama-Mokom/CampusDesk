<?php

namespace App\Providers;

use App\Models\RequestStage;
use App\Models\User;
use App\Observers\RequestStageObserver;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        Model::preventSilentlyDiscardingAttributes();

        Gate::define('is_student', fn (User $user) => $user->role === 'student');
        Gate::define('is_staff', fn (User $user) => $user->role === 'staff');
        Gate::define('is-dept-admin', fn (User $user) => $user->role === 'staff' && $user->staffProfile?->admin_level === 'dept_admin');
        Gate::define('is-super-admin', fn (User $user) => $user->role === 'staff' && $user->staffProfile?->admin_level === 'super_admin');

        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url')."/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });

        RequestStage::observe(RequestStageObserver::class);

        // RateLimiter::for('auth', function (Request $request) {
        // return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
        //  });
        // RateLimiter::for('api', function (Request $request) {
        // return $request->user()
        // ? Limit::perMinute(20)->by($request->user()->id)
        // : Limit::perMinute(10)->by($request->ip());
        // });
    }
}
