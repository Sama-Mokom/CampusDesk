<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\EnsureIsDeptAdmin;
use App\Http\Middleware\EnsureIsStaff;
use App\Http\Middleware\EnsureIsStudent;
use App\Http\Middleware\EnsureIsSuperAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;

// use Illuminate\Session\Middleware\StartSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(HandleCors::class);

        // $middleware->api(prepend: [
        //     // \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        // // $middleware->append(StartSession::class),
        // ]);

        $middleware->alias([
            'verified' => EnsureEmailIsVerified::class,
            'student' => EnsureIsStudent::class,
            'staff' => EnsureIsStaff::class,
            'dept_admin' => EnsureIsDeptAdmin::class,
            'super_admin' => EnsureIsSuperAdmin::class,
            'active_account' => EnsureAccountIsActive::class,
        ]);

        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
