<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Utilities\ProfileUpdateUtility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function __construct(
        protected ProfileUpdateUtility $profileUtility
    ) {}

    /**
     * Update user profile.
     *
     * POST /api/v1/profile
     * Protected by auth:sanctum middleware
     *
     * @return JsonResponse
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

        $result = $this->profileUtility->updateProfile(
            $user,
            $validated['full_name'],
            $validated['email']
        );

        return response()->json([
            'message' => 'Profile updated successfully',
            'email_verification_required' => $result['email_changed'],
            'user' => [
                'id' => $result['user']->id,
                'full_name' => $result['user']->full_name,
                'email' => $result['user']->email,
                'email_verified_at' => $result['user']->email_verified_at,
            ],
        ], 200);
    }

    /**
     * Upload profile picture.
     *
     * POST /api/v1/profile/picture
     * Protected by auth:sanctum middleware
     */
    public function uploadPicture(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'profile_picture' => 'required|image|mimes:jpeg,png|max:2048',
        ]);

        $user = $request->user();

        $path = $this->profileUtility->uploadProfilePicture(
            $user,
            $request->file('profile_picture')
        );

        return response()->json([
            'success' => true,
            'message' => 'Profile picture updated successfully.',
            'data' => [
                'profile_picture' => $path,
                'profile_picture_url' => asset('storage/'.$path),
            ],
        ]);
    }
}
