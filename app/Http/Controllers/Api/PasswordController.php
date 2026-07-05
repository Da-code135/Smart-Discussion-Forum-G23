<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetOtpMailable;
use App\Models\ApiPasswordResetOtp;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password as PasswordRule;

class PasswordController extends Controller
{
    /**
     * ============================================================
     * POST /api/v1/password/forgot
     * ============================================================
     * Send a 6-digit OTP to the user's email address.
     *
     * The desktop client calls this endpoint, shows an OTP input field,
     * and the user types the code they received — they never leave the app
     * to click a link in a browser (the old token-link approach is kept
     * only for the web interface at /forgot-password).
     *
     * Rate limits
     *   - 3 OTP requests per 15 minutes per email address.
     *     Prevents inbox flooding / OTP bombing.
     *
     * Security properties
     *   - Any previous unused OTP for this email is deleted before the
     *     new one is created, so only the most-recently issued code is valid.
     *   - The OTP is hashed (bcrypt) before being stored — the plaintext
     *     code is only ever held in memory and sent once in the email.
     *   - OTP expires in 10 minutes.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function forgot(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $email = $validated['email'];

        // Rate-limit OTP requests: 3 per 15 minutes per email address
        $requestKey = 'api-otp-request:' . $email;

        if (RateLimiter::tooManyAttempts($requestKey, 3)) {
            $seconds = RateLimiter::availableIn($requestKey);

            return response()->json([
                'message' => "Too many reset requests. Try again in {$seconds} seconds.",
            ], 429);
        }

        RateLimiter::hit($requestKey, 900); // 15 minutes

        // Delete all previous unused OTPs for this email — only one can be active
        ApiPasswordResetOtp::where('email', $email)
            ->whereNull('used_at')
            ->delete();

        // Generate a 6-digit OTP padded to always be 6 digits (e.g. 004821)
        $plainOtp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store hashed — never persist the plaintext code
        ApiPasswordResetOtp::create([
            'email'      => $email,
            'otp'        => Hash::make($plainOtp),
            'expires_at' => now()->addMinutes(10),
        ]);

        // Queue the email — $plainOtp is passed once, hashed version is in DB
        $user = User::where('email', $email)->first();
        Mail::queue(new PasswordResetOtpMailable($user, $plainOtp));

        return response()->json([
            'message' => 'A 6-digit reset code has been sent to your email. It expires in 10 minutes.',
        ], 200);
    }

    /**
     * ============================================================
     * POST /api/v1/password/reset
     * ============================================================
     * Validate the OTP and update the password.
     *
     * Request body
     *   email                  string  required
     *   otp                    string  required, exactly 6 characters
     *   password               string  required, min 8, mixed case, numbers
     *   password_confirmation  string  required
     *
     * On success
     *   - OTP is marked as used (prevents replay within the same window)
     *   - Password is updated
     *   - All existing Sanctum tokens are revoked — the user must log in
     *     again with the new password, which is the expected behavior after
     *     a password reset on any secure platform
     *   - PasswordReset event is fired (consistent with the web flow)
     *
     * Rate limits
     *   - 5 attempts per 10 minutes per email address.
     *     Prevents brute-forcing the 6-digit space (1 000 000 possibilities
     *     reduced to at most 5 guesses per window).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function reset(Request $request)
    {
        $validated = $request->validate([
            'email'                 => 'required|email|exists:users,email',
            'otp'                   => 'required|string|size:6',
            'password'              => [
                'required',
                'confirmed',
                PasswordRule::min(8)->mixedCase()->numbers(),
            ],
        ]);

        $email = $validated['email'];

        // Rate-limit reset attempts to prevent brute-forcing the OTP
        $guessKey = 'api-otp-guess:' . $email;

        if (RateLimiter::tooManyAttempts($guessKey, 5)) {
            $seconds = RateLimiter::availableIn($guessKey);

            return response()->json([
                'message' => "Too many attempts. Try again in {$seconds} seconds.",
            ], 429);
        }

        // Find the most-recent unused OTP record for this email
        $record = ApiPasswordResetOtp::where('email', $email)
            ->whereNull('used_at')
            ->latest()
            ->first();

        // Wrong OTP or no OTP was ever requested — count as a failed attempt
        if (!$record || !Hash::check($validated['otp'], $record->otp)) {
            RateLimiter::hit($guessKey, 600); // 10 minutes

            return response()->json([
                'message' => 'Invalid reset code. Please check the code and try again.',
            ], 400);
        }

        // Correct code — now check it has not expired
        if (!$record->isValid()) {
            return response()->json([
                'message' => 'This reset code has expired. Please request a new one.',
            ], 400);
        }

        // Mark as used BEFORE updating the password to prevent a race condition
        // where two simultaneous requests both read the record as unused
        $record->markAsUsed();

        // Update the password — the hashed cast on User handles hashing automatically
        $user = User::where('email', $email)->first();
        $user->forceFill(['password' => Hash::make($validated['password'])])->save();
        event(new PasswordReset($user));

        // Revoke all existing API tokens — user must log in fresh with new password
        $user->tokens()->delete();

        // Clear the guess rate limiter on success
        RateLimiter::clear($guessKey);

        return response()->json([
            'message' => 'Password reset successfully. Please log in with your new password.',
        ], 200);
    }

    /**
     * ============================================================
     * POST /api/v1/password/change
     * ============================================================
     * Change password for an already-authenticated user.
     * Protected by auth:sanctum middleware.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function change(Request $request)
    {
        $validated = $request->validate([
            'current_password' => 'required',
            'new_password'     => [
                'required',
                'different:current_password',
                'confirmed',
                PasswordRule::min(8)->mixedCase()->numbers(),
            ],
        ]);

        $user = $request->user();

        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect.',
            ], 403);
        }

        $user->update(['password' => Hash::make($validated['new_password'])]);

        return response()->json([
            'message' => 'Password changed successfully.',
        ], 200);
    }
}
