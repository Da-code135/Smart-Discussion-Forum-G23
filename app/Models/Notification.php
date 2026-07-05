<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    /** @use HasFactory<\Database\Factories\NotificationFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'data',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    /**
     * The user who receives this notification.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope: only unread notifications.
     */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }
}
