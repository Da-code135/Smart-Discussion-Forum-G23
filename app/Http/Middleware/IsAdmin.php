<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
   public function handle(Request $request, Closure $next)
    {
        // Check if user is authenticated and is any type of admin
        if (!auth()->check()) {
            return redirect('/login')->with('error', 'Please login to continue');
        }

        // Check if user is any type of admin (System Admin or Group Admin)
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized. Administrator access required.');
        }

        return $next($request);
    }
}
