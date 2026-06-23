<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlacklistRecord extends Model
{
     public function user()
    {
        return $this->belongsTo(User::class);
    }

     public function liftedBy()
    {
        return $this->belongsTo(User::class, 'lifted_by');//here we're specifying which column should be searched
    }
}
