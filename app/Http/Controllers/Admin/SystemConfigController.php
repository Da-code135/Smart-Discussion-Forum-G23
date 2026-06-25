<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemConfig;
use Illuminate\Http\Request;

class SystemConfigController extends Controller
{
    // #92: SHOW SYSTEM CONFIG
    public function index()
    {
        $configs = SystemConfig::all();

        return view('admin.system-config.index', [
            'configs' => $configs,
        ]);
    }

    // #92: UPDATE SYSTEM CONFIG
    public function update(Request $request)
    {
        $validated = $request->validate([
            'max_login_attempts' => 'required|integer|min:1',
            'lockout_minutes' => 'required|integer|min:1',
            'inactivity_warning_days' => 'required|integer|min:1',
            'blacklist_duration_days' => 'required|integer|min:1',
        ]);

        foreach ($validated as $key => $value) {
            SystemConfig::updateOrCreate(
                ['config_key' => $key],
                ['config_value' => $value]
            );
        }

        return redirect()->back()->with('success', 'System configuration updated');
    }
}
