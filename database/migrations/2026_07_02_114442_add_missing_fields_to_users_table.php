<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMissingFieldsToUsersTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add blacklisted_at timestamp if not exists
            if (! Schema::hasColumn('users', 'blacklisted_at')) {
                $table->timestamp('blacklisted_at')->nullable()->after('account_status');
            }

            // Add is_warned boolean if not exists
            if (! Schema::hasColumn('users', 'is_warned')) {
                $table->boolean('is_warned')->default(false)->after('blacklisted_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Remove is_warned column if exists
            if (Schema::hasColumn('users', 'is_warned')) {
                $table->dropColumn('is_warned');
            }

            // Remove blacklisted_at column if exists
            if (Schema::hasColumn('users', 'blacklisted_at')) {
                $table->dropColumn('blacklisted_at');
            }
        });
    }
}
