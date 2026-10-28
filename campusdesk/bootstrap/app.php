<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\EnsureIsDeptAdmin;
use App\Http\Middleware\EnsureIsStaff;
use App\Http\Middleware\EnsureIsStudent;
use App\Http\Middleware\EnsureIsSuperAdmin;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

// use Illuminate\Session\Middleware\StartSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['prefix' => 'api', 'middleware' => ['api', 'auth:sanctum']],
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
        $exceptions->render(function (QueryException|\PDOException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'A database error occurred while processing your request. Please try again later.',
                ], 500);
            }
        });

        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                if (
                    $e instanceof ValidationException ||
                    $e instanceof AuthenticationException ||
                    $e instanceof AuthorizationException ||
                    $e instanceof HttpExceptionInterface
                ) {
                    return null;
                }

                return response()->json([
                    'message' => 'An unexpected server error occurred. Please try again later.',
                ], 500);
            }
        });
    })->create();
