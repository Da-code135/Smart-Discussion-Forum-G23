<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailVerificationToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use App\Mail\VerifyEmailMailable;

class EmailVerificationController extends Controller
{
    /**
     * Verify email address.
     *
     * POST /api/v1/email/verify
     * Protected by auth:sanctum middleware
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verify(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
        ]);

        $verification = EmailVerificationToken::where('token', $validated['token'])
            ->where('email', $validated['email'])
            ->first();

        if (!$verification || !$verification->isValid()) {
            return response()->json([
                'message' => 'Invalid or expired verification token',
            ], 400);
        }

        $verification->user->update(['email_verified_at' => now()]);
        $verification->delete();

        return response()->json([
            'message' => 'Email verified successfully',
        ], 200);
    }

    /**
     * Resend verification email.
     *
     * POST /api/v1/email/resend
     * Protected by auth:sanctum middleware
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resend(Request $request)
    {
        $user = $request->user();

        if ($user->email_verified_at) {
            return response()->json([
                'message' => 'Email already verified',
            ], 400);
        }

        // Rate limit: 1 per minute
        $key = 'api-verify-email:' . $user->email;
        if (RateLimiter::tooManyAttempts($key, 1)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'message' => 'Please wait ' . $seconds . ' seconds before requesting another verification email',
            ], 429);
        }

        RateLimiter::hit($key, 60);

        // Generate and send token
        $token = Str::random(64);
        EmailVerificationToken::create([
            'user_id' => $user->id,
            'token' => $token,
            'email' => $user->email,
            'expires_at' => now()->addHours(24),
        ]);

        Mail::to($user->email)->queue(new VerifyEmailMailable($user, $token));

        return response()->json([
            'message' => 'Verification email sent',
        ], 200);
    }
}
