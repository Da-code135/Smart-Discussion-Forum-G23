<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quiz extends Model
{
    use HasFactory;

    protected $primaryKey = 'quiz_id';

    protected $fillable = [
        'lecturer_id',
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
        'is_active' => 'boolean',
        'published_at' => 'datetime',
    ];

    /**
     * The lecturer who created this quiz.
     */
    public function lecturer()
    {
        return $this->belongsTo(User::class, 'lecturer_id');
    }

    /**
     * Configuration for this quiz (one-to-one).
     */
    public function configuration()
    {
        return $this->hasOne(QuizConfiguration::class, 'quiz_id', 'quiz_id');
    }

    /**
     * All questions in this quiz.
     */
    public function questions()
    {
        return $this->hasMany(Question::class, 'quiz_id', 'quiz_id')->orderBy('question_order');
    }
}
