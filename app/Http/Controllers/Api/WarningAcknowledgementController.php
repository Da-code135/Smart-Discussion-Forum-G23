<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Utilities\WarningAcknowledgementUtility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API controller for warning acknowledgement.
 *
 * When a user's account status is 'warned', they must acknowledge the warning
 * before continuing. This endpoint is called after the desktop client detects
 * the `requires_warning_acknowledgement` flag from the login response.
 */
class WarningAcknowledgementController extends Controller
{
    public function __construct(
        protected WarningAcknowledgementUtility $utility
    ) {}

    /**
     * POST /api/v1/warnings/acknowledge
     *
     * Acknowledge the first unacknowledged warning for the authenticated user.
     */
    public function acknowledge(Request $request): JsonResponse
    {
        $user = $request->user();

        $warning = $this->utility->getUnacknowledgedWarning($user);

        if (! $warning) {
            return response()->json([
                'success' => false,
                'message' => 'No unacknowledged warnings found.',
            ], 404);
        }

        try {
            $acknowledged = $this->utility->acknowledge($warning, $user);

            if (! $acknowledged) {
                return response()->json([
                    'success' => false,
                    'message' => 'Warning is already acknowledged.',
                ], 409);
            }

            return response()->json([
                'success' => true,
                'message' => 'Warning acknowledged successfully.',
                'data' => [
                    'warning_id' => $warning->id,
                    'account_status' => $user->fresh()->account_status,
                ],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * GET /api/v1/warnings/unacknowledged
     *
     * Check if the authenticated user has any unacknowledged warnings.
     */
    public function unacknowledged(Request $request): JsonResponse
    {
        $user = $request->user();

        $warning = $this->utility->getUnacknowledgedWarning($user);

        if (! $warning) {
            return response()->json([
                'success' => true,
                'data' => [
                    'has_unacknowledged' => false,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'has_unacknowledged' => true,
                'warning' => [
                    'id' => $warning->id,
                    'reason' => $warning->reason,
                    'warning_number' => $warning->warning_number,
                    'issued_at' => $warning->created_at,
                    'response_deadline' => $warning->response_deadline,
                ],
            ],
        ]);
    }
}
