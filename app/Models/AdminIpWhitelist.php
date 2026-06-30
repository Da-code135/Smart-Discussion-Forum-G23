<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AdminIpWhitelist extends Model
{
    use HasFactory;

    protected $table = 'admin_ip_whitelist';

    protected $fillable = [
        'ip_address',
        'description',
        'is_active',
        'created_by',
        'expires_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the user who created this whitelist entry
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if IP is currently allowed
     */
    public function isCurrentlyAllowed(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Check if IP has expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Scope to only active IPs
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to only non-expired IPs
     */
    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Scope to currently valid IPs (active and not expired)
     */
    public function scopeCurrentlyValid($query)
    {
        return $query->active()->notExpired();
    }

    /**
     * Check if specific IP is allowed
     */
    public static function isIpAllowed(string $ip): bool
    {
        return self::currentlyValid()
            ->where('ip_address', $ip)
            ->exists();
    }

    /**
     * Add IP to whitelist
     */
    public static function addIp(
        string $ip,
        string $description = null,
        $expiresAt = null,
        int $createdBy = null
    ): self {
        return self::create([
            'ip_address' => $ip,
            'description' => $description,
            'is_active' => true,
            'expires_at' => $expiresAt,
            'created_by' => $createdBy,
        ]);
    }

    /**
     * Remove IP from whitelist
     */
    public static function removeIp(string $ip): bool
    {
        return self::where('ip_address', $ip)->delete() > 0;
    }

    /**
     * Deactivate IP (soft delete approach)
     */
    public function deactivate(): bool
    {
        return $this->update(['is_active' => false]);
    }

    /**
     * Activate IP
     */
    public function activate(): bool
    {
        return $this->update(['is_active' => true]);
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute(): string
    {
        if (!$this->is_active) {
            return 'Inactive';
        }

        if ($this->isExpired()) {
            return 'Expired';
        }

        return 'Active';
    }

    /**
     * Get status color for UI
     */
    public function getStatusColorAttribute(): string
    {
        if (!$this->is_active) {
            return 'gray';
        }

        if ($this->isExpired()) {
            return 'red';
        }

        return 'green';
    }
}
