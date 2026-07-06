<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    protected $casts = [
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
}
