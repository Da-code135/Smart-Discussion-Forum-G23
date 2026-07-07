<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RecommendationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API controller for personalized topic recommendations.
 *
 * Delegates to the existing RecommendationService so the logic is identical
 * to the web recommendations page.
 */
class RecommendationController extends Controller
{
    public function __construct(
        protected RecommendationService $recommendationService
    ) {}

    /**
     * GET /api/v1/recommendations
     *
     * Returns personalized topic recommendations for the authenticated user.
     *
     * @param  int  $limit  Number of recommendations (default 10, max 50)
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $limit = min((int) $request->input('limit', 10), 50);

        $recommendations = $this->recommendationService
            ->generateRecommendations($user, $limit);

        return response()->json([
            'success' => true,
            'data' => [
                'recommendations' => $recommendations->map(function ($topic) {
                    return [
                        'id' => $topic->id,
                        'title' => $topic->title,
                        'description' => $topic->description,
                        'creator' => $topic->creator ? [
                            'id' => $topic->creator->id,
                            'full_name' => $topic->creator->full_name,
                        ] : null,
                        'category' => $topic->category ? [
                            'id' => $topic->category->id,
                            'name' => $topic->category->category_name,
                        ] : null,
                        'reply_count' => $topic->posts_count,
                        'post_type' => $topic->post_type,
                        'is_answered' => $topic->is_answered,
                        'created_at' => $topic->created_at,
                    ];
                }),
            ],
        ]);
    }
}
