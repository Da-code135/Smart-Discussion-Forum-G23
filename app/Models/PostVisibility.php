<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostVisibility extends Model
{
    /** @var string Eloquent would otherwise use "post_visibilities". */
    protected $table = 'post_visibility';

    /** Only created_at exists in the database. */
    public const CREATED_AT = 'created_at';

    public const UPDATED_AT = null;

    protected $fillable = [
        'post_id',
        'excluded_user_id',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function excludedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'excluded_user_id');
    }
}
