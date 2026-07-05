<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Question extends Model
{
    protected $primaryKey = 'question_id';
    protected $table = 'questions';
    
    protected $fillable = [
        'quiz_id',
        'question_text',
        'question_type',
        'marks',
        'question_order',
    ];

    // Relationships

    /**
     * Question belongs to a quiz
     */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class, 'quiz_id', 'quiz_id');
    }

    /**
     * Question has many answer options
     */
    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class, 'question_id', 'question_id');
    }

    /**
     * Get the correct answer for this question
     */
    public function correctAnswer()
    {
        return $this->answers()->where('is_correct', true)->first();
    }
}