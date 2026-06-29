<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlacklistRecord extends Model
{
    protected $fillable = ['user_id', 'reason', 'expires_at', 'lifted_at', 'lifted_by'];

    // Timestamps enabled (created_at, updated_at added via migration)
    public $timestamps = true;

    // Cast dates to Carbon instances
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
}
