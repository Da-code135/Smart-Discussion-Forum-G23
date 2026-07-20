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
        'is_removed',
    ];

    protected $casts = [
        'is_removed' => 'boolean',
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
    //  Scopes
    // -------------------------------------------------------------------

    /**
     * Scope: only messages that haven't been removed.
     */
    public function scopeNotRemoved($query)
    {
        return $query->where('is_removed', false);
    }

    // -------------------------------------------------------------------
    //  Delivery status helpers (for sender's view)
    // -------------------------------------------------------------------

    /**
     * Status priority: higher value = more advanced.
     */
    private const STATUS_PRIORITY = [
        'sent' => 0,
        'delivered' => 1,
        'read' => 2,
    ];

    /**
     * Get the aggregated delivery status across all recipients.
     *
     * From the sender's perspective the message is:
     *   - "read"      → every recipient has read it
     *   - "delivered"  → at least one recipient has read or received it, but not all read
     *   - "sent"       → no recipient has received or read it yet
     */
    public function getDeliveryStatusAttribute(): string
    {
        if ($this->relationLoaded('statusRows') && $this->statusRows->isNotEmpty()) {
            $worst = $this->statusRows->min(fn ($row) => self::STATUS_PRIORITY[$row->status] ?? 0);

            return array_search($worst, self::STATUS_PRIORITY) ?? 'sent';
        }

        return 'sent';
    }

    /**
     * Human-readable label for the delivery status.
     */
    public function getDeliveryStatusLabelAttribute(): string
    {
        return match ($this->delivery_status) {
            'read' => 'Read',
            'delivered' => 'Delivered',
            default => 'Sent',
        };
    }

    /**
     * Material icon name for the delivery status.
     */
    public function getDeliveryStatusIconAttribute(): string
    {
        return match ($this->delivery_status) {
            'read' => 'done_all',
            'delivered' => 'done_all',
            default => 'done',
        };
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
