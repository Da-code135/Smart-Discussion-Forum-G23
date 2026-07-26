<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExportLog extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'topic_id',
        'user_id',
        'file_type',
    ];

    /**
     * The topic that was exported.
     */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    /**
     * The user who performed the export.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
