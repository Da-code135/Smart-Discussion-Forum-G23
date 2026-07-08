<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageStatus extends Model
{
    /**
     * No CREATED_AT column — only UPDATED_AT tracks when status last changed.
     */
    public const CREATED_AT = null;

    protected $table = 'message_status';

    protected $fillable = [
        'message_id',
        'user_id',
        'status',
    ];

    protected $casts = [
        'updated_at' => 'datetime',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
