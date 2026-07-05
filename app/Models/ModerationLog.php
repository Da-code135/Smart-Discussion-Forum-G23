<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ModerationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_id',
        'admin_id',
        'action',
        'reason',
    ];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
