<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiPasswordResetOtp extends Model
{
    protected $fillable = [
        'email',
        'otp',
        'expires_at',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at'    => 'datetime',
        ];
    }

    /**
     * An OTP is valid when it has not expired AND has not already been used.
     */
    public function isValid(): bool
    {
        return now()->isBefore($this->expires_at) && $this->used_at === null;
    }

    /**
     * Consume the OTP immediately so it can never be reused.
     * Called before the password is updated, not after, to prevent a race
     * where two rapid requests both read the OTP as unused.
     */
    public function markAsUsed(): void
    {
        $this->update(['used_at' => now()]);
    }
}
