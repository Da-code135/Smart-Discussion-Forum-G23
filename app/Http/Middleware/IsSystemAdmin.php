<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class IsSystemAdmin
{
    /**
     * Handle an incoming request.
     * Check if user is System Administrator (full system-wide access)
     */
    public function handle(Request $request, Closure $next)
    {
        if (! auth()->check()) {
            return redirect('/login')->with('error', 'Please login to continue');
        }

        if (! auth()->user()->isSystemAdmin()) {
            abort(403, 'Unauthorized. System Administrator access required.');
        }

        return $next($request);
    }
}
