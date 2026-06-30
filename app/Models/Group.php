<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Group extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = ['group_name', 'description', 'created_by'];
    
    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get admins of this group
     */
    public function admins()
    {
        return $this->belongsToMany(User::class, 'group_admins')
                    ->withPivot('assigned_by', 'assigned_at')
                    ->withTimestamps();
    }

    /**
     * Check if specific user is admin of this group
     */
    public function hasAdmin(User $user): bool
    {
        return $this->admins()->where('users.id', $user->id)->exists();
    }

    /**
     * Add admin to this group
     */
    public function addAdmin(User $user, ?int $assignedBy = null): void
    {
        if (!$this->hasAdmin($user)) {
            $this->admins()->attach($user->id, [
                'assigned_by' => $assignedBy,
            ]);
        }
    }

    /**
     * Remove admin from this group
     */
    public function removeAdmin(User $user): void
    {
        $this->admins()->detach($user->id);
    }
}
