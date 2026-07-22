<?php

namespace App\Models;

use Database\Factories\TopicCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TopicCategory extends Model
{
    /** @use HasFactory<TopicCategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'group_id',
        'category_name',
        'keyword_hints',
    ];

    /**
     * The group this category belongs to.
     */
    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * All posts classified under this category.
     */
    public function posts()
    {
        return $this->hasMany(Post::class, 'category_id');
    }

    /**
     * All topics assigned to this category.
     */
    public function topics()
    {
        return $this->hasMany(Topic::class, 'category_id');
    }

    /**
     * Scope: categories belonging to a specific group.
     */
    public function scopeForGroup($query, int $groupId)
    {
        return $query->where('group_id', $groupId);
    }
}
