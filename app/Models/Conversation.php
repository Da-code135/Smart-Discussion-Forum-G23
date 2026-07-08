<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'type',
        'name',
        'last_activity_at',
    ];

    protected $casts = [
        'last_activity_at' => 'datetime',
    ];

    // -------------------------------------------------------------------
    //  Relationships
    // -------------------------------------------------------------------

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    // -------------------------------------------------------------------
    //  Scopes
    // -------------------------------------------------------------------

    /**
     * Scope: only conversations belonging to a specific group.
     */
    public function scopeForGroup($query, int $groupId)
    {
        return $query->where('group_id', $groupId);
    }

    /**
     * Scope: conversations the given user participates in, within their group.
     */
    public function scopeForUserInGroup($query, User $user)
    {
        return $query->where('group_id', $user->group_id)
            ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id));
    }
}
