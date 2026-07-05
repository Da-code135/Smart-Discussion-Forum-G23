<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    protected $primaryKey = 'question_id';

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
    public function quiz()
    {
        return $this->belongsTo(Quiz::class, 'quiz_id', 'quiz_id');
    }

    /**
     * All answers for this question.
     */
    public function answers()
    {
        return $this->hasMany(Answer::class, 'question_id', 'question_id');
    }
}
