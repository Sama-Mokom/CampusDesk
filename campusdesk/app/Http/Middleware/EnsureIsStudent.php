<?php

namespace App\Http\Middleware;

use Closure;
// use Illuminate\Auth\Access\Facades\Gate; older convention which is why we dont use anymore, still works though
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIsStudent
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Gate::allows('is_student')){
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        return $next($request);
    }
}
