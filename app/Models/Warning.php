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

    protected $casts = [
        'is_acknowledged' => 'boolean',
        'is_resolved' => 'boolean',
        'response_deadline' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
