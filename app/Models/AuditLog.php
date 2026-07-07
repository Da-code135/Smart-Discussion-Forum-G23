<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action',
        'target_type',
        'target_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'description',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    /**
     * Get the user who performed the action
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the target model (polymorphic relationship)
     */
    public function target()
    {
        return $this->morphTo();
    }

    /**
     * Scope to filter by action type
     */
    public function scopeAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope to filter by user
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter by target
     */
    public function scopeForTarget($query, string $targetType, ?int $targetId = null)
    {
        $query = $query->where('target_type', $targetType);

        if ($targetId) {
            $query = $query->where('target_id', $targetId);
        }

        return $query;
    }

    /**
     * Scope to filter by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        return $query;
    }

    /**
     * Format the log for display
     */
    public function getFormattedDescriptionAttribute()
    {
        if ($this->description) {
            return $this->description;
        }

        $userName = $this->user ? $this->user->full_name : 'System';

        return "{$userName} performed {$this->action}";
    }

    /**
     * Get human-readable action name
     */
    public function getActionLabelAttribute()
    {
        return self::getActionLabel($this->action);
    }

    /**
     * Get action label by action name
     */
    public static function getActionLabel(string $action): string
    {
        $labels = self::getActionLabels();

        return $labels[$action] ?? ucfirst(str_replace('.', ' ', $action));
    }

    /**
     * Get the full action labels map
     */
    public static function getActionLabels(): array
    {
        return [
            'user.role.changed' => 'User Role Changed',
            'user.group.changed' => 'User Group Changed',
            'user.blacklisted' => 'User Blacklisted',
            'user.blacklist.lifted' => 'User Blacklist Lifted',
            'user.warned' => 'User Warned',
            'user.activated' => 'User Activated',
            'user.deleted' => 'User Deleted',
            'group.created' => 'Group Created',
            'group.updated' => 'Group Updated',
            'group.deleted' => 'Group Deleted',
            'group.member.added' => 'Group Member Added',
            'group.member.removed' => 'Group Member Removed',
            'system.config.updated' => 'System Configuration Updated',
            'admin.ip.added' => 'Admin IP Added',
            'admin.ip.removed' => 'Admin IP Removed',
        ];
    }
}
