<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Answer extends Model
{
    protected $primaryKey = 'answer_id';
    protected $table = 'answers';
    
    protected $fillable = [
        'question_id',
        'answer_text',
        'is_correct',
    ];
    
    protected $casts = [
        'is_correct' => 'boolean',
    ];

    // Relationships

    /**
     * Answer belongs to a question
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id', 'question_id');
    }
}