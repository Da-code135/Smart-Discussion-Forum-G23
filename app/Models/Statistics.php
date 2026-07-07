<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Statistics extends Model
{
    /**
     * The table associated with the model.
     *
     * Laravel would infer "statistics" from the class name anyway,
     * but being explicit avoids confusion with the pluralisation.
     */
    protected $table = 'statistics';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'group_id',
        'total_members',
        'active_members_this_week',
        'total_topics',
        'total_posts',
        'unanswered_questions',
        'inactive_members_30days',
        'last_calculated_at',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'last_calculated_at' => 'datetime',
    ];

    // -------------------------------------------------------------------
    //  Relationships
    // -------------------------------------------------------------------

    /**
     * The group these statistics belong to.
     * Each statistics row belongs to exactly one group.
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    // -------------------------------------------------------------------
    //  Helpers
    // -------------------------------------------------------------------

    /**
     * Calculate the active member percentage as a whole number.
     *
     * Returns 0 if there are no members (avoids division by zero).
     */
    public function activePercentage(): int
    {
        if ($this->total_members === 0) {
            return 0;
        }

        return (int) round(($this->active_members_this_week / $this->total_members) * 100);
    }

    /**
     * Calculate the average number of posts per topic.
     *
     * Returns 0 if there are no topics (avoids division by zero).
     */
    public function averagePostsPerTopic(): int
    {
        if ($this->total_topics === 0) {
            return 0;
        }

        return (int) round($this->total_posts / $this->total_topics);
    }
}
