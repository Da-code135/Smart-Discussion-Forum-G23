<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;



class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'full_name',
        'email',
        'password',
        'role_id',
        'group_id',
        'account_status',
        'last_active_at',
        'profile_picture',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_active_at' => 'datetime',
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
     * Check if user is System Administrator
     */
    public function isSystemAdmin(): bool
    {
        return $this->role && $this->role->role_name === 'System Administrator';
    }

    /**
     * Check if user is Group Administrator
     */
    public function isGroupAdmin(): bool
    {
        return $this->role && $this->role->role_name === 'Group Administrator';
    }

    /**
     * Check if user is any type of admin
     */
    public function isAdmin(): bool
    {
        return $this->isSystemAdmin() || $this->isGroupAdmin();
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
            return $this->administeredGroups()->where('groups.id', $group->id)->exists();
        }

        return false;
    }

    /**
     * Check if user can admin specific user
     */
    public function canAdminUser(User $targetUser): bool
    {
        // System admins can admin all users
        if ($this->isSystemAdmin()) {
            return true;
        }

        // Group admins can only admin users in their groups
        if ($this->isGroupAdmin()) {
            $adminGroupIds = $this->administeredGroups()->pluck('groups.id');
            return $adminGroupIds->contains($targetUser->group_id);
        }

        return false;
    }
}
