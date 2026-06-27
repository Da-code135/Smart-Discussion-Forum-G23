<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\Group;
use App\Models\BlacklistRecord;
use App\Models\Warning;
use App\Models\OnboardingAgreement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Events\Registered;

class AuthController extends Controller
{
    /**
     * API Registration endpoint for desktop client.
     *
     * POST /api/v1/register
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        // Validate input
        $validated = $request->validate([
            'full_name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        // Look up role and group by name (dynamic, not hardcoded)
        $role = Role::where('role_name', 'Student')->first();
        $group = Group::where('group_name', 'Default Group')->first();

        if (!$role || !$group) {
            return response()->json([
                'message' => 'Required role or group not found in database. Please contact administrator.',
            ], 500);
        }

        // Create user
        $user = User::create([
            'full_name' => $validated['full_name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role_id' => $role->id,
            'group_id' => $group->id,
        ]);

        // Send verification email
        event(new Registered($user));

        // Generate API token
        $token = $user->createToken('desktop-client')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful',
            'token' => $token,
            'user' => $this->formatUserResponse($user),
        ], 201);
    }

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
     * Delete user account.
     *
     * DELETE /api/v1/account
     * Protected by auth:sanctum middleware
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteAccount(Request $request)
    {
        $validated = $request->validate([
            'password' => 'required',
        ]);

        $user = $request->user();

        // Verify password
        if (!Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid password',
            ], 403);
        }

        // Cascade delete related records
        $user->warnings()->delete();
        $user->blacklistRecords()->delete();
        $user->emailVerificationTokens()->delete();
        $user->onboardingAgreements()->delete();

        // Revoke all tokens
        $user->tokens()->delete();

        // Delete user
        $user->delete();

        return response()->json([
            'message' => 'Account deleted successfully',
        ], 200);
    }

    /**
     * Refresh API token.
     *
     * POST /api/v1/token/refresh
     * Protected by auth:sanctum middleware
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function refreshToken(Request $request)
    {
        $user = $request->user();

        // Revoke current token
        $request->user()->currentAccessToken()->delete();

        // Issue new token
        $newToken = $user->createToken('desktop-client')->plainTextToken;

        return response()->json([
            'message' => 'Token refreshed successfully',
            'token' => $newToken,
        ], 200);
    }

    /**
     * List all active tokens.
     *
     * GET /api/v1/tokens
     * Protected by auth:sanctum middleware
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function listTokens(Request $request)
    {
        $tokens = $request->user()->tokens()
            ->orderBy('last_used_at', 'desc')
            ->get()
            ->map(function ($token) {
                return [
                    'id' => $token->id,
                    'name' => $token->name,
                    'created_at' => $token->created_at,
                    'last_used_at' => $token->last_used_at,
                    'expires_at' => $token->expires_at,
                ];
            });

        return response()->json([
            'tokens' => $tokens,
        ], 200);
    }

    /**
     * Revoke specific token.
     *
     * DELETE /api/v1/tokens/{tokenId}
     * Protected by auth:sanctum middleware
     *
     * @param Request $request
     * @param int $tokenId
     * @return \Illuminate\Http\JsonResponse
     */
    public function revokeToken(Request $request, $tokenId)
    {
        $token = $request->user()->tokens()->where('id', $tokenId)->first();

        if (!$token) {
            return response()->json([
                'message' => 'Token not found',
            ], 404);
        }

        $token->delete();

        return response()->json([
            'message' => 'Token revoked successfully',
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
