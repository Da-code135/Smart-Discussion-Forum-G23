<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Auth\Events\PasswordReset;

class PasswordController extends Controller
{
    /**
     * Send password reset link.
     *
     * POST /api/v1/password/forgot
     * Public endpoint
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function forgot(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => 'Password reset link sent to your email',
            ], 200);
        }

        return response()->json([
            'message' => 'Unable to send reset link',
        ], 400);
    }

    /**
     * Reset password.
     *
     * POST /api/v1/password/reset
     * Public endpoint
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function reset(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'email' => 'required|email|exists:users,email',
            'password' => [
                'required',
                'confirmed',
                PasswordRule::min(8)->mixedCase()->numbers()
            ],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Password reset successfully',
            ], 200);
        }

        return response()->json([
            'message' => 'Unable to reset password',
        ], 400);
    }

    /**
     * Change password.
     *
     * POST /api/v1/password/change
     * Protected by auth:sanctum middleware
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function change(Request $request)
    {
        $validated = $request->validate([
            'current_password' => 'required',
            'new_password' => [
                'required',
                'different:current_password',
                'confirmed',
                PasswordRule::min(8)->mixedCase()->numbers()
            ],
        ]);

        $user = $request->user();

        // Verify current password
        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect',
            ], 403);
        }

        // Update password
        $user->update(['password' => Hash::make($validated['new_password'])]);

        return response()->json([
            'message' => 'Password changed successfully',
        ], 200);
    }
}
