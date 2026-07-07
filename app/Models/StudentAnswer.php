<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentAnswer extends Model
{
    protected $table = 'student_answers';

    protected $fillable = [
        'attempt_id',
        'question_id',
        'selected_answer_id',
    ];

    // Relationships

    /**
     * Student answer belongs to an attempt
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(StudentAttempt::class, 'attempt_id', 'attempt_id');
    }

    /**
     * Student answer references a question
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id', 'question_id');
    }

    /**
     * Student answer references the answer they selected
     */
    public function selectedAnswer(): BelongsTo
    {
        return $this->belongsTo(Answer::class, 'selected_answer_id', 'answer_id');
    }
}
