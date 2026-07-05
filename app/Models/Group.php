<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Group extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        "group_name",
        "description",
        "created_by",
        "group_type",
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, "created_by");
    }

    /**
     * Get admins of this group
     */
    public function admins()
    {
        return $this->belongsToMany(User::class, "group_admins")
            ->withPivot("assigned_by", "assigned_at")
            ->withTimestamps();
    }

    /**
     * Check if specific user is admin of this group
     */
    public function hasAdmin(User $user): bool
    {
        return $this->admins()->where("users.id", $user->id)->exists();
    }

    /**
     * Add admin to this group
     */
    public function addAdmin(User $user, ?int $assignedBy = null): void
    {
        if (!$this->hasAdmin($user)) {
            $this->admins()->attach($user->id, [
                "assigned_by" => $assignedBy,
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

    /**
     * Auto-promote the first Member-role user in a student group to Group Admin.
     *
     * Called after a user is assigned to a student group. If this user is the
     * very first "Member"-role student in the group, they are automatically added
     * to the group_admins pivot table so they can manage the group.
     *
     * @param  User  $user  The user who was just assigned to this group.
     * @param  int|null  $assignedBy  The admin who assigned them (null for self-registration).
     */
    public function autoPromoteFirstStudent(User $user, ?int $assignedBy = null): void
    {
        // Only applies to student groups
        if ($this->group_type !== 'student') {
            return;
        }

        // Count users in this group with the 'Member' role (role_name = 'Member')
        $memberCount = $this->users()
            ->whereHas('role', fn ($q) => $q->where('role_name', 'Member'))
            ->count();

        // In a fresh call this count already includes the newly assigned user,
        // so 1 means they are the very first member.
        if ($memberCount === 1) {
            $this->addAdmin($user, $assignedBy);
        }
    }
}
