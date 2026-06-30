<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Topic extends Model
{
    /** @use HasFactory<\Database\Factories\TopicFactory> */
    use HasFactory;

    protected $fillable = [
        'group_id',
        'created_by',
        'title',
        'description',
        'status',
        'post_type',
    ];

    /**
     * The group that owns this topic.
     * Every topic belongs to exactly one group (group isolation).
     */
    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * The user who created this topic.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * All replies (posts) in this topic, ordered chronologically.
     */
    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    /**
     * Scope: only active (non-archived) topics.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope: only topics visible to a specific group.
     */
    public function scopeForGroup($query, int $groupId)
    {
        return $query->where('group_id', $groupId);
    }

    /**
     * Scope: filter by post_type (discussion or question).
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('post_type', $type);
    }
}
