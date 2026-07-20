<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Conversation extends Model
{
    use HasFactory;
    use SoftDeletes;

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

    /**
     * The most recent message in this conversation.
     * Used in the conversation list to show a preview.
     */
    public function lastMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latest('id');
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
     * Scope: conversations the given user participates in, within their group(s).
     *
     * System Admins participate in conversations across all groups;
     * regular users are scoped to their own group.
     */
    public function scopeForUserInGroup($query, User $user)
    {
        if ($user->isSystemAdmin()) {
            return $query->whereHas('participants', fn ($q) => $q->where('user_id', $user->id));
        }

        return $query->where('group_id', $user->group_id)
            ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id));
    }

    /**
     * Scope: all conversations (System Admin full access).
     */
    public function scopeForAdmin($query, User $user)
    {
        if (! $user->isSystemAdmin()) {
            return $query->whereRaw('1 = 0'); // No access for non-admins
        }

        return $query;
    }
}
