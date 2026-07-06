<?php

namespace App\Services;

use App\Models\Group;
use App\Models\Role;
use App\Models\SystemConfig;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class CacheService
{
    /**
     * Cache duration in seconds (1 hour = 3600)
     */
    private const CACHE_DURATION = 3600;

    /**
     * Long cache duration (24 hours)
     */
    private const LONG_CACHE_DURATION = 86400;

    /**
     * Get all roles with caching
     *
     * @return Collection
     */
    public function getAllRoles()
    {
        return Cache::remember('roles.all', self::LONG_CACHE_DURATION, function () {
            return Role::all();
        });
    }

    /**
     * Get role by ID with caching
     *
     * @return Role|null
     */
    public function getRoleById(int $roleId)
    {
        return Cache::remember("roles.{$roleId}", self::LONG_CACHE_DURATION, function () use ($roleId) {
            return Role::find($roleId);
        });
    }

    /**
     * Get role by name with caching
     *
     * @return Role|null
     */
    public function getRoleByName(string $roleName)
    {
        $cacheKey = 'roles.name.'.md5($roleName);

        return Cache::remember($cacheKey, self::LONG_CACHE_DURATION, function () use ($roleName) {
            return Role::where('role_name', $roleName)->first();
        });
    }

    /**
     * Get system config value with caching
     *
     * @param  mixed  $default
     * @return mixed
     */
    public function getConfigValue(string $key, $default = null)
    {
        $cacheKey = "system_config.{$key}";

        return Cache::remember($cacheKey, self::CACHE_DURATION, function () use ($key, $default) {
            $config = SystemConfig::where('config_key', $key)->first();

            return $config ? $config->config_value : $default;
        });
    }

    /**
     * Get all system configs with caching
     */
    public function getAllConfigs(): array
    {
        return Cache::remember('system_configs.all', self::CACHE_DURATION, function () {
            return SystemConfig::all()->pluck('config_value', 'config_key')->toArray();
        });
    }

    /**
     * Get user count by status with caching
     */
    public function getUserCountByStatus(?string $status = null): int
    {
        $cacheKey = $status ? "users.count.{$status}" : 'users.count.all';

        return Cache::remember($cacheKey, 300, function () use ($status) {
            $query = User::query();
            if ($status) {
                $query->where('account_status', $status);
            }

            return $query->count();
        });
    }

    /**
     * Get dashboard statistics with caching
     */
    public function getDashboardStats(): array
    {
        return Cache::remember('dashboard.stats', 300, function () {
            return [
                'total_users' => User::count(),
                'active_users' => User::where('account_status', 'active')->count(),
                'warned_users' => User::where('account_status', 'warned')->count(),
                'blacklisted_users' => User::where('account_status', 'blacklisted')->count(),
                'total_groups' => Group::count(),
                'system_admins' => User::whereHas('role', function ($q) {
                    $q->where('role_name', 'System Administrator');
                })->count(),
                'group_admins' => User::whereHas('role', function ($q) {
                    $q->where('role_name', 'Group Administrator');
                })->count(),
            ];
        });
    }

    /**
     * Get group with member count
     *
     * @return array|null
     */
    public function getGroupWithMemberCount(int $groupId)
    {
        return Cache::remember("group.{$groupId}.with_count", 300, function () use ($groupId) {
            $group = Group::withCount('users')->find($groupId);

            return $group ? $group->toArray() : null;
        });
    }

    /**
     * Clear all application caches
     */
    public function clearAllCaches(): void
    {
        Cache::forget('roles.all');
        Cache::forget('system_configs.all');
        Cache::forget('dashboard.stats');

        // Clear user count caches
        Cache::forget('users.count.all');
        Cache::forget('users.count.active');
        Cache::forget('users.count.warned');
        Cache::forget('users.count.blacklisted');
    }

    /**
     * Clear role caches
     */
    public function clearRoleCaches(): void
    {
        Cache::forget('roles.all');
        // Clear individual role caches by pattern
        $roles = Role::all();
        foreach ($roles as $role) {
            Cache::forget("roles.{$role->id}");
            Cache::forget('roles.name.'.md5($role->role_name));
        }
    }

    /**
     * Clear system config caches
     */
    public function clearConfigCaches(): void
    {
        Cache::forget('system_configs.all');
        $configs = SystemConfig::all();
        foreach ($configs as $config) {
            Cache::forget("system_config.{$config->config_key}");
        }
    }

    /**
     * Clear user-related caches
     */
    public function clearUserCaches(?int $userId = null): void
    {
        $this->clearAllCaches();

        if ($userId) {
            Cache::forget("user.{$userId}");
        }
    }

    /**
     * Clear group-related caches
     */
    public function clearGroupCaches(?int $groupId = null): void
    {
        Cache::forget('dashboard.stats');

        if ($groupId) {
            Cache::forget("group.{$groupId}.with_count");
        }
    }

    /**
     * Get cached user with relationships
     *
     * @return User|null
     */
    public function getUserWithRelations(int $userId)
    {
        return Cache::remember("user.{$userId}.relations", 300, function () use ($userId) {
            return User::with(['role', 'group'])->find($userId);
        });
    }
}
