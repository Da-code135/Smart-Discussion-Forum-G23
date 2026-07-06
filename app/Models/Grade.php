<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Grade extends Model
{
    protected $primaryKey = 'grade_id';

    protected $table = 'grades';

    protected $fillable = [
        'attempt_id',
        'student_id',
        'quiz_id',
        'group_id',
        'total_score',
        'max_score',
        'percentage',
        'participation_mark',
        'final_grade',
        'graded_at',
    ];

    protected $casts = [
        'total_score' => 'decimal:2',
        'max_score' => 'decimal:2',
        'percentage' => 'decimal:2',
        'participation_mark' => 'decimal:2',
        'final_grade' => 'decimal:2',
        'graded_at' => 'datetime',
    ];

    // Relationships

    /**
     * Grade belongs to an attempt
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(StudentAttempt::class, 'attempt_id', 'attempt_id');
    }

    /**
     * Grade belongs to a student
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * Grade belongs to a quiz
     */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class, 'quiz_id', 'quiz_id');
    }

    /**
     * Scope: only grades for a specific group.
     */
    public function scopeForGroup($query, int $groupId)
    {
        return $query->where('group_id', $groupId);
    }
}
