<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds new configuration keys for the Analytics, ML & Administration module
     * (Tasks 9 & 10 — Admin Config Panel).
     *
     * - days_before_second_warning: days after Warning 1 before issuing Warning 2
     * - days_before_blacklist:       days after Warning 2 before automatic blacklist
     * - quiz_late_join_allowed:      whether students can join a quiz after it has started
     */
    public function up(): void
    {
        $now = now();

        $newConfigs = [
            [
                'config_key' => 'days_before_second_warning',
                'config_value' => '14',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'config_key' => 'days_before_blacklist',
                'config_value' => '14',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'config_key' => 'quiz_late_join_allowed',
                'config_value' => '0',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        foreach ($newConfigs as $config) {
            DB::table('system_configs')->updateOrInsert(
                ['config_key' => $config['config_key']],
                $config
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('system_configs')->whereIn('config_key', [
            'days_before_second_warning',
            'days_before_blacklist',
            'quiz_late_join_allowed',
        ])->delete();
    }
};
