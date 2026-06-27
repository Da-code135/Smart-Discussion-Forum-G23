<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlacklistRecord extends Model
{
    protected $fillable = ['user_id', 'reason', 'expires_at', 'lifted_at', 'lifted_by'];
    
    // Disable timestamps as the table doesn't have created_at and updated_at columns
    public $timestamps = false;
    
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
        return $this->belongsTo(User::class, 'lifted_by');//here we're specifying which column should be searched
    }
}
