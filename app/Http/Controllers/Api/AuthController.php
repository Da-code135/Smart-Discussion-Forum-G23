<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\BlacklistRecord;
use App\Models\Warning;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * API Login endpoint for desktop client.
     *
     * POST /api/v1/login
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        // Rate limiting check
        $key = 'api-login-attempts:' . $request->input('email') . '|' . $request->ip();
        $maxAttempts = 5;
        $lockoutSeconds = 30;

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'message' => 'Too many login attempts. Try again in ' . $seconds . ' seconds.',
            ], 429);
        }

        // Validate input
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:8',
        ]);

        // Look up user
        $user = User::where('email', $request->input('email'))->first();

        // Check credentials
        if (!$user || !Hash::check($request->input('password'), $user->password)) {
            RateLimiter::hit($key, $lockoutSeconds);

            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }

        // Blacklist gate
        if ($user->account_status === 'blacklisted') {
            $blacklistRecord = BlacklistRecord::where('user_id', $user->id)
                ->whereNull('lifted_at')
                ->first();

            if ($blacklistRecord) {
                RateLimiter::hit($key, $lockoutSeconds);

                return response()->json([
                    'message' => 'Your account is blacklisted until ' . $blacklistRecord->expires_at->format('M d, Y') . '.',
                ], 403);
            }
        }

        // Warned gate - check for unacknowledged warnings
        if ($user->account_status === 'warned') {
            $unacknowledgedWarning = Warning::where('user_id', $user->id)
                ->where('is_acknowledged', false)
                ->first();

            if ($unacknowledgedWarning) {
                return response()->json([
                    'message' => 'Your account is warned. Please acknowledge the warning before continuing.',
                    'requires_warning_acknowledgement' => true,
                    'user' => $this->formatUserResponse($user),
                ], 403);
            }
        }

        // Clear rate limiter on successful login
        RateLimiter::clear($key);

        // Update last active
        $user->update(['last_active_at' => now()]);

        // Generate API token
        $token = $user->createToken('desktop-client')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => $this->formatUserResponse($user),
        ], 200);
    }

    /**
     * API Logout endpoint.
     *
     * POST /api/v1/logout
     * Protected by auth:sanctum middleware
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ], 200);
    }

    /**
     * Format user data for API response.
     *
     * @param User $user
     * @return array
     */
    private function formatUserResponse(User $user): array
    {
        return [
            'id' => $user->id,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'account_status' => $user->account_status,
            'role' => $user->role ? $user->role->role_name : null,
            'group' => $user->group ? $user->group->group_name : null,
            'email_verified_at' => $user->email_verified_at,
            'last_active_at' => $user->last_active_at,
        ];
    }
}
