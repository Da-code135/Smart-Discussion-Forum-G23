<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemConfig extends Model
{
    protected $fillable = ['config_key', 'config_value'];

    /**
     * Get a configuration value by key with caching.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function getValue(string $key, $default = null)
    {
        $cacheKey = "system_config.{$key}";
        
        return Cache::remember($cacheKey, 3600, function () use ($key, $default) {
            $config = self::where('config_key', $key)->first();
            return $config ? $config->config_value : $default;
        });
    }

    /**
     * Clear cache for specific config key
     *
     * @param string $key
     * @return void
     */
    public static function clearCache(string $key): void
    {
        Cache::forget("system_config.{$key}");
    }

    /**
     * Clear all config caches
     *
     * @return void
     */
    public static function clearAllCaches(): void
    {
        Cache::forget('system_configs.all');
        $configs = self::all();
        foreach ($configs as $config) {
            Cache::forget("system_config.{$config->config_key}");
        }
    }
}
