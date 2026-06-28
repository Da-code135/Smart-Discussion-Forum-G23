<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class IsGroupAdmin
{
    /**
     * Handle an incoming request.
     * Check if user is Group Administrator (can manage assigned groups)
     */
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check()) {
            return redirect('/login')->with('error', 'Please login to continue');
        }

        $user = auth()->user();

        // Must be at least a Group Admin (System Admins also pass this check)
        if (!$user->isAdmin()) {
            abort(403, 'Unauthorized. Group Administrator access required.');
        }

        return $next($request);
    }
}
