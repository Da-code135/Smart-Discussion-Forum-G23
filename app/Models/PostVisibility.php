<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostVisibility extends Model
{
    /**
     * Explicit table name because Eloquent would pluralize to 'post_visibilities'.
     */
    protected $table = 'post_visibility';

    /**
     * This table has no updated_at column — only created_at is tracked.
     */
    protected $timestamps = true;

    // Only created_at exists in the database (no updated_at)
    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'post_id',
        'excluded_user_id',
    ];

    /**
     * The post this visibility rule applies to.
     */
    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * The user who is excluded from seeing the post.
     */
    public function excludedUser()
    {
        return $this->belongsTo(User::class, 'excluded_user_id');
    }
}
