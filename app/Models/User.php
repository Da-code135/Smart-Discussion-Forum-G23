<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * Boot the model and register model event hooks.
     *
     * Enforces that every user must have a group_id. Because there is no
     * default/General group, a null group_id would leave a user without
     * any group — a state the application must never allow.
     */
    protected static function booted(): void
    {
        static::saving(function (User $user) {
            if (is_null($user->group_id)) {
                throw new \RuntimeException(
                    'Every user must belong to a group. A group_id is required.'
                );
            }
        });
    }

    protected $fillable = [
        "full_name",
        "email",
        "password",
        "role_id",
        "group_id",
        "account_status",
        "last_active_at",
        "profile_picture",
        "is_warned",
        "blacklisted_at",
        "email_verified_at",
    ];

    protected $hidden = ["password", "remember_token"];
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            "email_verified_at" => "datetime",
            "password" => "hashed",
            "last_active_at" => "datetime",
        ];
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function warnings()
    {
        return $this->hasMany(Warning::class);
    }

    public function blacklistRecords()
    {
        return $this->hasMany(BlacklistRecord::class);
    }

    public function emailVerificationTokens()
    {
        return $this->hasMany(EmailVerificationToken::class);
    }

    public function onboardingAgreements()
    {
        return $this->hasMany(OnboardingAgreement::class);
    }

    /**
     * Get groups this user administers (for Group Administrators)
     */
    public function administeredGroups()
    {
        return $this->belongsToMany(Group::class, 'group_admins')
            ->withPivot('assigned_by', 'assigned_at')
            ->withTimestamps();
    }

    /**
     * Get groups this user can teach (for Lecturers).
     * Maps via the lecturer_group_access pivot table.
     */
    public function taughtGroups()
    {
        return $this->belongsToMany(Group::class, 'lecturer_group_access', 'lecturer_id', 'group_id')
            ->withTimestamps();
    }

    /**
     * Check if a lecturer can teach a specific group.
     */
    public function canTeachGroup(Group $group): bool
    {
        if ($this->isSystemAdmin()) {
            return true;
        }

        // Own group or explicitly assigned
        if ($this->group_id === $group->id) {
            return true;
        }

        return $this->taughtGroups()->where('groups.id', $group->id)->exists();
    }

    /**
     * Check if user is System Administrator
     */
    public function isSystemAdmin(): bool
    {
        return $this->role && $this->role->role_name === "System Administrator";
    }

    /**
     * Check if user is Group Administrator
     */
    public function isGroupAdmin(): bool
    {
        return $this->role && $this->role->role_name === "Group Administrator";
    }

    /**
     * Check if user is any type of admin
     */
    public function isAdmin(): bool
    {
        return $this->isSystemAdmin() || $this->isGroupAdmin();
    }

    /**
     * Check if user is a Lecturer
     */
    public function isLecturer(): bool
    {
        return $this->role && $this->role->role_name === 'Lecturer';
    }

    /**
     * Check if user is a Student
     */
    public function isStudent(): bool
    {
        return $this->role && $this->role->role_name === 'Student';
    }

    /**
     * The notifications belonging to this user.
     */
    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Check if user can admin specific group
     */
    public function canAdminGroup(Group $group): bool
    {
        // System admins can admin all groups
        if ($this->isSystemAdmin()) {
            return true;
        }

        // Group admins can only admin their assigned groups
        if ($this->isGroupAdmin()) {
            return $this->administeredGroups()
                ->where("groups.id", $group->id)
                ->exists();
        }

        return false;
    }

    public function canAdminUser(User $targetUser): bool
    {
        // System admins can admin all users
        if ($this->isSystemAdmin()) {
            return true;
        }

        // Group admins can only admin users in their groups
        if ($this->isGroupAdmin()) {
            $adminGroupIds = $this->administeredGroups()->pluck("groups.id");
            return $adminGroupIds->contains($targetUser->group_id);
        }

        return false;
    }

    /**
     * Check if user is blacklisted
     */
    public function isBlacklisted(): bool
    {
        return $this->blacklisted_at !== null;
    }

    /**
     * Get the IDs of all groups this user can access.
     *
     * - Regular members: only their own group
     * - Group Admins: their own + administered groups
     * - Lecturers: their own + taught groups
     * - System Admins: bypass this filter entirely (caller checks isSystemAdmin first)
     *
     * @return int[]
     */
    public function accessibleGroupIds(): array
    {
        $ids = [$this->group_id];

        if ($this->isGroupAdmin()) {
            $ids = array_merge(
                $ids,
                $this->administeredGroups()->pluck('groups.id')->toArray(),
            );
        }

        // Any user with taught group access (primarily lecturers)
        $ids = array_merge(
            $ids,
            $this->taughtGroups()->pluck('groups.id')->toArray(),
        );

        return array_unique(array_filter($ids));
    }

    /**
     * Check if user can access a specific group.
     * Covers System Admins, Group Admins, Lecturers, and own-group membership.
     */
    public function canAccessGroup(int $groupId): bool
    {
        if ($this->isSystemAdmin()) {
            return true;
        }

        return in_array($groupId, $this->accessibleGroupIds(), true);
    }

    /**
     * Get the user's warning status
     */
    public function getWarningStatus(): array
    {
        return [
            "is_warned" => $this->is_warned,
            "warning_count" => $this->warnings()
                ->whereNull("is_resolved")
                ->count(),
            "blacklisted_at" => $this->blacklisted_at,
        ];
    }
}
