<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OnboardingAgreement;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    /**
     * Record the user's onboarding agreement.
     *
     * POST /api/v1/onboarding/agree
     */
    public function agree(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'agreed' => 'required|boolean',
            'agreement_version' => 'required|string|max:20',
        ]);

        OnboardingAgreement::create([
            'user_id' => $user->id,
            'agreed' => $validated['agreed'],
            'agreement_version' => $validated['agreement_version'],
            'ip_address' => $request->ip(),
            'agreed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Onboarding agreement recorded successfully.',
        ], 201);
    }

    /**
     * Check if the user has agreed to onboarding.
     *
     * GET /api/v1/onboarding/status
     */
    public function status(Request $request)
    {
        $user = $request->user();

        $agreement = OnboardingAgreement::where('user_id', $user->id)
            ->latest('agreed_at')
            ->first();

        return response()->json([
            'data' => [
                'has_agreed' => $agreement?->agreed ?? false,
                'agreement_version' => $agreement?->agreement_version ?? null,
                'agreed_at' => $agreement?->agreed_at ?? null,
            ],
        ], 200);
    }
}
