<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Utilities\ReportUtility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API controller for content reporting.
 *
 * Allows desktop clients to report topics, posts, and replies.
 * Group isolation is enforced via the ReportUtility.
 */
class ReportController extends Controller
{
    public function __construct(
        protected ReportUtility $reportUtility
    ) {}

    /**
     * POST /api/v1/reports
     *
     * Create a new report for a topic, post, or reply.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
            'type' => 'required|in:topic,post,reply',
            'id' => 'required|integer',
        ]);

        try {
            $report = $this->reportUtility->createReport(
                $request->user(),
                $validated['type'],
                $validated['id'],
                $validated['reason']
            );

            return response()->json([
                'success' => true,
                'message' => 'Thank you for reporting this content. Our moderators will review it shortly.',
                'data' => [
                    'report_id' => $report->id,
                    'status' => $report->status,
                ],
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * GET /api/v1/me/reports
     *
     * List all reports submitted by the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 20), 100);

        $reports = $this->reportUtility->getUserReports($request->user(), $perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'reports' => $reports->items(),
            ],
            'pagination' => [
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
                'per_page' => $reports->perPage(),
                'total' => $reports->total(),
            ],
        ]);
    }
}
