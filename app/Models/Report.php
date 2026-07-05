<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    protected $fillable = [
        'user_id',
        'reason',
        'status'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reportable(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }
}