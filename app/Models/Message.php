<?php

namespace App\Models;

use App\Services\MessageStatusService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'body',
    ];

    // -------------------------------------------------------------------
    //  Relationships
    // -------------------------------------------------------------------

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function statusRows(): HasMany
    {
        return $this->hasMany(MessageStatus::class);
    }

    // -------------------------------------------------------------------
    //  Booted — auto-create status rows for all participants except sender
    // -------------------------------------------------------------------

    protected static function booted(): void
    {
        static::created(function (Message $message) {
            app(MessageStatusService::class)
                ->createInitialStatusRows($message);
        });
    }
}
