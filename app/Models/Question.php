<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    /**
     * The quiz this question belongs to.
     */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class, 'quiz_id', 'quiz_id');
    }

    /**
     * All answers for this question.
     */
    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class, 'question_id', 'question_id');
    }

    /**
     * Get the correct answer for this question.
     */
    public function correctAnswer()
    {
        return $this->answers()->where('is_correct', true)->first();
    }
}
