<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password as PasswordRule;

class PasswordController extends Controller
{
    /**
     * Change user password.
     *
     * POST /api/v1/password/change
     * Protected by auth:sanctum middleware
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function change(Request $request)
    {
        // Validate input
        $request->validate([
            'current_password' => [
                'required',
                function ($attribute, $value, $fail) use ($request) {
                    if (!Hash::check($value, $request->user()->password)) {
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
                    ->numbers()
            ],
            'new_password_confirmation' => 'required',
        ], [
            'new_password.different' => 'The new password must be different from your current password.',
            'new_password.confirmed' => 'The passwords do not match.',
            'new_password' => 'Password must be at least 8 characters with uppercase, lowercase, and numbers.',
        ]);

        // Update password
        $request->user()->update([
            'password' => Hash::make($request->new_password),
        ]);

        return response()->json([
            'message' => 'Password updated successfully',
        ], 200);
    }
}
