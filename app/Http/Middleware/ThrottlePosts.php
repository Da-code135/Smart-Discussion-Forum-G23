<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ThrottlePosts
{
    /**
     * The rate limiter instance.
     */
    protected RateLimiter $limiter;

    /**
     * Allowed actions and their limits.
     *
     * 'reply' — max 5 POST replies per 60-second window.
     * 'topic' — max 3 new topics per 60-second window.
     */
    protected const LIMITS = [
        'reply' => ['max_attempts' => 5, 'decay_seconds' => 60],
        'topic' => ['max_attempts' => 3, 'decay_seconds' => 60],
    ];

    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    /**
     * Handle an incoming request.
     *
     * @param  string  $action  'reply' or 'topic'
     */
    public function handle(Request $request, Closure $next, string $action = 'reply'): Response
    {
        // Every route this middleware is applied to requires authentication,
        // but guard against the off-chance it's reached without a logged-in user.
        if (! auth()->check()) {
            return response()->json([
                'message' => 'You are posting too fast. Please wait.',
            ], 429);
        }

        $user = auth()->user();

        // --- BYPASS: Lecturers and Administrators are never throttled ---
        // Lecturers (teachers) + System/Group Administrators bypass the limit.
        if ($user->isAdmin() || ($user->role && $user->role->role_name === 'Lecturer')) {
            return $next($request);
        }

        // --- Apply the rate limit ---
        $limits = self::LIMITS[$action] ?? self::LIMITS['reply'];

        // Build a per-user, per-action cache key so replying and topic-creation
        // limits are tracked independently (e.g. 5 replies AND 3 topics per minute).
        $key = 'throttle.posts.'.$action.'.'.$user->id;

        if ($this->limiter->tooManyAttempts($key, $limits['max_attempts'])) {
            $retryAfter = $this->limiter->availableIn($key);

            return response()->json([
                'message' => 'You are posting too fast. Please wait.',
                'retry_after' => $retryAfter,
            ], 429);
        }

        // Record this attempt with the configured decay window.
        $this->limiter->hit($key, $limits['decay_seconds']);

        return $next($request);
    }
}
