<?php

namespace App\Http\Controllers;

use App\Models\ParticipationActivity;
use App\Models\User;
use App\Services\ParticipationService;
use Illuminate\Support\Facades\Auth;

class ParticipationController extends Controller
{
    /**
     * Participation marks overview for lecturers and administrators.
     *
     * Lecturers see Student-role users in their accessible groups
     * (own group + lecturer_group_access). System Admins see all
     * students; Group Admins see students in their accessible groups.
     */
    public function students(ParticipationService $participation)
    {
        $user = Auth::user();

        if (! $user->isLecturer() && ! $user->isAdmin()) {
            abort(403, 'Only lecturers and administrators can view participation marks.');
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

        return view('participation.students', [
            'students' => $students,
            'summaries' => $summaries,
            'weights' => config('participation.weights'),
            'activityTypes' => ParticipationActivity::TYPES,
        ]);
    }
}
