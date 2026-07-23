<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Get authenticated user data.
     *
     * GET /api/v1/me
     * Protected by auth:sanctum middleware
     *
     * @return JsonResponse
     */
    public function me(Request $request)
    {
        // Eager load role and group relationships
        $user = $request->user()->load(['role', 'group']);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'account_status' => $user->account_status,
                'role' => $user->role ? $user->role->role_name : null,
                'group' => $user->group ? $user->group->group_name : null,
                'email_verified_at' => $user->email_verified_at,
                'last_active_at' => $user->last_active_at,
                'profile_picture' => $user->profile_picture,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ],
        ], 200);
    }
}
