<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->disabled_at !== null) {
            return response()->json([
                'message' => 'Unauthenticated.',
                'code' => 'ACCOUNT_DISABLED',
            ], 401);
        }

        return $next($request);
    }
}
