<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemConfig extends Model
{
    protected $fillable = ['config_key', 'config_value'];

    /**
     * Get a configuration value by key.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function getValue(string $key, $default = null)
    {
        $config = self::where('config_key', $key)->first();
        return $config ? $config->config_value : $default;
    }
}
