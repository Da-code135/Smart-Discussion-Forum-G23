<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Quiz extends Model
{
    use HasFactory;

    protected $primaryKey = 'quiz_id';

    protected $table = 'quizzes';

    protected $fillable = [
        'lecturer_id',
        'group_id',
        'title',
        'description',
        'target_category',
        'scheduled_date',
        'start_time',
        'duration_minutes',
        'is_active',
        'published_at',
    ];

    protected $casts = [//converts values into specific PHP data types
        'scheduled_date' => 'date',
        'start_time' => 'datetime:H:i',
        'is_active' => 'boolean',
        'published_at' => 'datetime',
    ];

    /**
     * The lecturer who created this quiz.
     */
    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lecturer_id');
    }

    /**
     * The group this quiz belongs to (multi-tenant isolation).
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * Scope: only quizzes for a specific group.
     */
    public function scopeForGroup($query, int $groupId)
    {
        return $query->where('group_id', $groupId);
    }

    /**
     * All questions in this quiz.
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'quiz_id', 'quiz_id');
    }

    /**
     * Student attempts for this quiz.
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(StudentAttempt::class, 'quiz_id', 'quiz_id');
    }

    /**
     * Configuration for this quiz (one-to-one).
     */
    public function configuration(): HasOne
    {
        return $this->hasOne(QuizConfiguration::class, 'quiz_id', 'quiz_id');
    }

    /**
     * Grades for this quiz (one per attempt).
     */
    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class, 'quiz_id', 'quiz_id');
    }

    /**
     * Get the fully combined scheduled date+time as a Carbon instance.
     *
     * Handles the fact that scheduled_date is cast to 'date' and start_time
     * is cast to 'datetime:H:i', both of which can serialize unexpectedly
     * when concatenated directly.
     */
    public function getScheduledDateTime(): Carbon
    {
        $dateStr = $this->scheduled_date instanceof Carbon
            ? $this->scheduled_date->format('Y-m-d')
            : $this->scheduled_date;
        $timeStr = $this->start_time instanceof Carbon
            ? $this->start_time->format('H:i:s')
            : $this->start_time;

        return Carbon::parse($dateStr.' '.$timeStr);
    }

    /**
     * Get the IDs of groups this lecturer can teach.
     * Combines the lecturer's own group_id with explicit lecturer_group_access records.
     *
     * Delegates to User::accessibleGroupIds() so all roles are handled consistently.
     */
    public static function lecturerAccessibleGroupIds(User $lecturer): array
    {
        if ($lecturer->isSystemAdmin()) {
            return Group::pluck('id')->toArray();
        }

        return $lecturer->accessibleGroupIds();
    }

    /**
     * Get all quizzes created by this lecturer (or all if admin) with
     * their grades, student info, and statistics.
     *
     * Used by the lecturer results overview page.
     *
     * @param  \App\Models\User  $user  The lecturer or admin
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function lecturerQuizzesWithGrades(User $user)
    {
        $query = self::with([
            'grades.student:id,full_name,email',
            'group',
            'lecturer:id,full_name',
            'configuration',
        ])
            ->withCount('questions');

        if ($user->isSystemAdmin()) {
            // Admins see all quizzes
            $quizzes = $query->latest()->get();
        } else {
            // Lecturers see only their own quizzes
            $accessibleGroupIds = self::lecturerAccessibleGroupIds($user);

            $quizzes = $query->where('lecturer_id', $user->id)
                ->whereIn('group_id', $accessibleGroupIds)
                ->latest()
                ->get();
        }

        // Attach computed statistics per quiz
        return $quizzes->map(function ($quiz) {
            $quizGrades = $quiz->grades;

            $quiz->stats = [
                'total_attempts' => $quizGrades->count(),
                'average_score'  => $quizGrades->count() > 0
                    ? round($quizGrades->avg('percentage'), 1)
                    : 0,
                'highest_score'  => $quizGrades->count() > 0
                    ? round($quizGrades->max('percentage'), 1)
                    : 0,
                'lowest_score'   => $quizGrades->count() > 0
                    ? round($quizGrades->min('percentage'), 1)
                    : 0,
            ];

            return $quiz;
        });
    }
}
