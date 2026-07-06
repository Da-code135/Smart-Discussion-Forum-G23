<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlacklistRecord extends Model
{
    protected $fillable = [
        'user_id',
        'reason',
        'expires_at',
        'lifted_at',
        'lifted_by',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'lifted_at' => 'datetime',
        'blacklisted_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function liftedBy()
    {
        return $this->belongsTo(User::class, 'lifted_by');
    }

    /**
     * Check if the blacklist has expired
     */
    public function hasExpired(): bool
    {
        return now()->greaterThan($this->expires_at);
    }

    /**
     * Check if this is a permanent blacklist (no expiration)
     */
    public function isPermanent(): bool
    {
        return $this->expires_at === null;
    }

    /**
     * Get the current status of the blacklist
     */
    public function getStatus(): array
    {
        return [
            'active' => $this->lifted_at === null,
            'expired' => $this->hasExpired(),
            'permanent' => $this->isPermanent(),
            'remaining_days' => $this->expires_at ? now()->diffInDays($this->expires_at) : null,
        ];
    }
}
