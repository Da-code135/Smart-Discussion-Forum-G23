<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ParticipationActivity;
use App\Models\User;
use App\Services\ParticipationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParticipationController extends Controller
{
    /**
     * GET /api/v1/participation/students
     *
     * Participation marks overview for lecturers and administrators.
     * Mirrors the web ParticipationController@students: lecturers see
     * Student-role users in their accessible groups, System Admins see
     * all students, Group Admins see students in their accessible groups.
     */
    public function students(Request $request, ParticipationService $participation): JsonResponse
    {
        $user = $request->user();

        if (! $user->isLecturer() && ! $user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only lecturers and administrators can view participation marks.',
            ], 403);
        }

        $query = User::query()
            ->whereHas('role', fn ($q) => $q->where('role_name', 'Student'))
            ->with('group:id,group_name')
            ->orderBy('full_name');

        // Group isolation: System Admins see all students
        if (! $user->isSystemAdmin()) {
            $query->whereIn('group_id', $user->accessibleGroupIds());
        }

        $students = $query->get();

        $summaries = $participation->summaryForUsers($students->pluck('id')->all());

        $data = $students->map(function (User $student) use ($summaries) {
            $summary = $summaries[$student->id] ?? [
                'total' => 0.0,
                'counts' => array_fill_keys(ParticipationActivity::TYPES, 0),
            ];

            return [
                'id' => $student->id,
                'full_name' => $student->full_name,
                'email' => $student->email,
                'group_name' => $student->group?->group_name,
                'counts' => $summary['counts'],
                'total' => $summary['total'],
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'weights' => config('participation.weights'),
        ]);
    }
}
