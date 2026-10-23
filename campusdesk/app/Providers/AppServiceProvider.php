<?php

namespace App\Providers;

use App\Contracts\PaymentGatewayInterface;
use App\Contracts\RequestEventTransport;
use App\Models\RequestStage;
use App\Models\User;
use App\Observers\RequestStageObserver;
use App\Services\BroadcastingRequestEventTransport;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            RequestEventTransport::class,
            BroadcastingRequestEventTransport::class,
        );

        $this->app->bind(
            PaymentGatewayInterface::class,
            function ($app) {
                return $app->make(PaymentGatewayManager::class)->driver();
            }
        );
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
    }
}
