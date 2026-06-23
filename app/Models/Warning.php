<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Warning extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
