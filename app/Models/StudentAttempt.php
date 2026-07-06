<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StudentAttempt extends Model
{
    protected $primaryKey = 'attempt_id';

    protected $table = 'student_attempts';

    protected $fillable = [
        'quiz_id',
        'student_id',
        'start_time',
        'submit_time',
        'is_auto_submit',
        'is_late',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'submit_time' => 'datetime',
        'is_auto_submit' => 'boolean',
        'is_late' => 'boolean',
    ];

    // Relationships

    /**
     * Attempt belongs to a quiz
     */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class, 'quiz_id', 'quiz_id');
    }

    /**
     * Attempt belongs to a student (user)
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * Attempt has many student answers
     */
    public function answers(): HasMany
    {
        return $this->hasMany(StudentAnswer::class, 'attempt_id', 'attempt_id');
    }

    /**
     * Attempt has one grade
     */
    public function grade(): HasOne
    {
        return $this->hasOne(Grade::class, 'attempt_id', 'attempt_id');
    }
}
