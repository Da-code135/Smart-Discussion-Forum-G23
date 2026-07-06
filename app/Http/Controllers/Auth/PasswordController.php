<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class PasswordController extends Controller
{
    /**
     * Show forgot password form (Task #51)
     *
     * GET /forgot-password
     * Route name: 'password.request'
     *
     * @return View
     */
    public function showForgotPassword()
    {
        return view('auth.forgot-password');
    }

    /**
     * Send password reset link to email (Task #53)
     *
     * POST /forgot-password
     * Route name: 'password.email'
     *
     * Uses Laravel's built-in password reset notification system
     *
     * @return RedirectResponse
     */
    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ], [
            'email.exists' => 'We could not find a user with this email address.',
        ]);

        // Send the reset link
        $status = Password::sendResetLink(
            $request->only('email')
        );

        // Return response based on status
        return $status === Password::RESET_LINK_SENT
            ? back()->with('status', trans($status))
            : back()->withErrors(['email' => trans($status)]);
    }

    /**
     * Show password reset form (Task #55)
     *
     * GET /reset-password/{token}
     * Route name: 'password.reset'
     *
     * @param  string  $token
     * @return View
     */
    public function showResetPassword(Request $request, $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    /**
     * Reset the user's password (Task #56)
     *
     * POST /reset-password
     * Route name: 'password.update'
     *
     * Validates token, email, and new password with strength requirements
     * Uses Laravel's Password::reset() helper
     * Logs in user after successful reset
     *
     * @return RedirectResponse
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email|exists:users,email',
            'password' => [
                'required',
                'confirmed',
                PasswordRule::min(8)
                    ->mixedCase()
                    ->numbers(),
            ],
        ], [
            'password.confirmed' => 'Passwords do not match.',
            'password' => 'Password must be at least 8 characters with uppercase, lowercase, and numbers.',
        ]);

        // Attempt to reset the password
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        // Return response based on status
        if ($status === Password::PASSWORD_RESET) {
            // Get the user and log them in
            $user = User::where('email', $request->email)->first();
            Auth::login($user);

            return redirect()->route('dashboard')
                ->with('success', 'Your password has been reset successfully!');
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => trans($status)]);
    }

    /**
     * Show change password form (Task #57)
     *
     * GET /change-password
     * Route name: 'password.change'
     * Protected by auth middleware
     *
     * @return View
     */
    public function showChangePassword()
    {
        return view('auth.change-password');
    }

    /**
     * Update user's password (Task #59)
     *
     * POST /change-password
     * Route name: 'password.change.update'
     * Protected by auth middleware
     *
     * Validates:
     * - Current password is correct
     * - New password meets strength requirements
     * - New password confirmation matches
     * - New password is different from current password
     *
     * @return RedirectResponse
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => [
                'required',
                function ($attribute, $value, $fail) {
                    if (! Hash::check($value, Auth::user()->password)) {
                        $fail('The current password is incorrect.');
                    }
                },
            ],
            'new_password' => [
                'required',
                'different:current_password',
                'confirmed',
                PasswordRule::min(8)
                    ->mixedCase()
                    ->numbers(),
            ],
            'new_password_confirmation' => 'required',
        ], [
            'new_password.different' => 'The new password must be different from your current password.',
            'new_password.confirmed' => 'The passwords do not match.',
            'new_password' => 'Password must be at least 8 characters with uppercase, lowercase, and numbers.',
        ]);

        // Update the password
        Auth::user()->update([
            'password' => Hash::make($request->new_password),
        ]);

        return back()
            ->with('success', 'Your password has been updated successfully!');
    }

    /**
     * Calculate password strength (Task #60)
     *
     * Static helper method for real-time password strength feedback in views
     *
     * Scoring:
     * +1 for 8+ chars, +1 for 12+ chars, +1 for 16+ chars
     * +1 for lowercase, +1 for uppercase, +1 for number, +1 for special char
     *
     * Results:
     * - weak: ≤2 points
     * - fair: ≤4 points
     * - good: ≤6 points
     * - strong: >6 points
     *
     * @return string One of: 'weak', 'fair', 'good', 'strong'
     */
    public static function getPasswordStrength(string $password): string
    {
        $score = 0;

        // Length scoring
        if (strlen($password) >= 8) {
            $score++;
        }
        if (strlen($password) >= 12) {
            $score++;
        }
        if (strlen($password) >= 16) {
            $score++;
        }

        // Character type scoring
        if (preg_match('/[a-z]/', $password)) {
            $score++; // Has lowercase
        }
        if (preg_match('/[A-Z]/', $password)) {
            $score++; // Has uppercase
        }
        if (preg_match('/[0-9]/', $password)) {
            $score++; // Has number
        }
        if (preg_match('/[!@#$%^&*()_+\-=\[\]{};:\'",.<>?\/\\|`~]/', $password)) {
            $score++; // Has special character
        }

        // Return strength level based on score
        if ($score <= 2) {
            return 'weak';
        } elseif ($score <= 4) {
            return 'fair';
        } elseif ($score <= 6) {
            return 'good';
        } else {
            return 'strong';
        }
    }
}
