<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Quiz extends Model
{
    protected $primaryKey = 'quiz_id';
    protected $table = 'quizzes';
    
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
        'start_time' => 'datetime:H:i',
        'is_active' => 'boolean',
        'published_at' => 'datetime',
    ];

    // Relationships

    /**
     * Quiz belongs to a lecturer (user)
     */
    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lecturer_id');
    }

    /**
     * Quiz has many questions
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'quiz_id', 'quiz_id');
    }

    /**
     * Quiz has many student attempts
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(StudentAttempt::class, 'quiz_id', 'quiz_id');
    }

    /**
     * Quiz has one configuration
     */
    public function configuration(): HasOne
    {
        return $this->hasOne(QuizConfiguration::class, 'quiz_id', 'quiz_id');
    }

    /**
     * Quiz has many grades (one per attempt)
     */
    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class, 'quiz_id', 'quiz_id');
    }
}