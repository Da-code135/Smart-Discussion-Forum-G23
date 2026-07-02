<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Warning extends Model
{
    protected $fillable = [
        'user_id',
        'warning_number',
        'reason',
        'response_deadline',
        'is_acknowledged',
        'is_resolved',
        'resolved_at',
        'created_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_acknowledged' => 'boolean',
            'is_resolved' => 'boolean',
            'response_deadline' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if this warning is the third warning for the user
     */
    public function isThirdWarning(): bool
    {
        return $this->warning_number >= 3;
    }

    /**
     * Check if this warning has expired (response deadline passed)
     */
    public function hasExpired(): bool
    {
        return now()->greaterThan($this->response_deadline);
    }
}
