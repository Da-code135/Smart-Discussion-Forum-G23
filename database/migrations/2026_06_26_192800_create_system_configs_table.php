<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('system_configs', function (Blueprint $table) {
            $table->id();
            $table->string('config_key')->unique();
            $table->text('config_value')->nullable();
            $table->timestamps();
        });

        // Insert default configuration values
        DB::table('system_configs')->insert([
            ['config_key' => 'inactivity_warning_days', 'config_value' => '30', 'created_at' => now(), 'updated_at' => now()],
            ['config_key' => 'warning_response_days', 'config_value' => '7', 'created_at' => now(), 'updated_at' => now()],
            ['config_key' => 'blacklist_duration_days', 'config_value' => '90', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_configs');
    }
};
