<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    /** @use HasFactory<\Database\Factories\PostFactory> */
    use HasFactory;

    protected $fillable = [
        'topic_id',
        'user_id',
        'content',
        'is_removed',
        'is_reported',
        'category_id',
    ];

    protected $casts = [
        'is_removed' => 'boolean',
        'is_reported' => 'boolean',
    ];

    /**
     * The topic this post belongs to.
     */
    public function topic()
    {
        return $this->belongsTo(Topic::class);
    }

    /**
     * The user who wrote this post.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The ML category assigned to this post (nullable until classified).
     */
    public function category()
    {
        return $this->belongsTo(TopicCategory::class, 'category_id');
    }

    /**
     * Visibility exclusions: users who cannot see this post.
     */
    public function visibilityExclusions()
    {
        return $this->hasMany(PostVisibility::class);
    }

    /**
     * Scope: only posts that haven't been removed by moderation.
     */
    public function scopeNotRemoved($query)
    {
        return $query->where('is_removed', false);
    }

    /**
     * Scope: only posts visible to a specific user.
     * Filters out posts where the user is explicitly excluded.
     */
    public function scopeVisibleToUser($query, int $userId)
    {
        return $query->whereDoesntHave('visibilityExclusions', function ($q) use ($userId) {
            $q->where('excluded_user_id', $userId);
        });
    }

    /**
     * Scope: only removed (moderated) posts.
     */
    public function scopeRemoved($query)
    {
        return $query->where('is_removed', true);
    }

    /**
     * Scope: only posts flagged for moderation review.
     */
    public function scopeReported($query)
    {
        return $query->where('is_reported', true);
    }

    /**
     * Moderation actions taken on this post.
     */
    public function moderationLogs()
    {
        return $this->hasMany(ModerationLog::class);
    }
}
