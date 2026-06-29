<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailVerificationToken extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'token',
        'email',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Check if token is valid
    public function isValid(): bool
    {
        return now()->lessThan($this->expires_at);
    }
}
