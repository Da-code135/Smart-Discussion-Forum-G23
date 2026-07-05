<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizConfiguration extends Model
{
    protected $primaryKey = 'config_id';
    protected $table = 'quiz_configuration';
    
    protected $fillable = [
        'quiz_id',
        'allow_late_join',
        'notification_minutes_before',
        'participation_criteria',
        'lock_screen_on_start',
        'show_results_after_close',
        'show_correct_answers',
    ];
    
    protected $casts = [
        'allow_late_join' => 'boolean',
        'lock_screen_on_start' => 'boolean',
        'show_results_after_close' => 'boolean',
        'show_correct_answers' => 'boolean',
    ];

    // Relationships

    /**
     * Configuration belongs to a quiz
     */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class, 'quiz_id', 'quiz_id');
    }
}