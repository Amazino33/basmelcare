<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        if (!auth()->check() || empty(array_intersect(auth()->user()->role ?? [], $roles))) {
            abort(403, 'You do not have permission to access this page.');
        }

        return $next($request);
    }
}
