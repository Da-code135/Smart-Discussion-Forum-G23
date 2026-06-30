<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailVerificationToken;
use App\Mail\VerifyEmailMailable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /**
     * Update user profile.
     *
     * POST /api/v1/profile
     * Protected by auth:sanctum middleware
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request)
    {
        $user = $request->user();

        // Validate input
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
        ]);

        // Check if email changed
        $emailChanged = $validated['email'] !== $user->email;

        if ($emailChanged) {
            // Generate new verification token
            $token = Str::random(64);
            EmailVerificationToken::create([
                'user_id' => $user->id,
                'token' => $token,
                'email' => $validated['email'],
                'expires_at' => now()->addHours(24),
            ]);

            // Send verification email
            Mail::queue(new VerifyEmailMailable($user, $token));
        }

        // Update user
        $user->update([
            'full_name' => $validated['full_name'],
            'email' => $validated['email'],
            'email_verified_at' => $emailChanged ? null : $user->email_verified_at,
        ]);

        // Reload user with fresh data
        $user->refresh();

        return response()->json([
            'message' => 'Profile updated successfully',
            'email_verification_required' => $emailChanged,
            'user' => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
            ],
        ], 200);
    }
}
