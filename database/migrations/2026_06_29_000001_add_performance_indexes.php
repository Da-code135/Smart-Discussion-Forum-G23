<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Phase 4E: Performance optimization - Add indexes for frequently queried columns
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Index for account status filtering (admin dashboard, user management)
            $table->index('account_status', 'idx_users_account_status');
            
            // Composite index for role + status filtering
            $table->index(['role_id', 'account_status'], 'idx_users_role_status');
            
            // Composite index for group + status filtering
            $table->index(['group_id', 'account_status'], 'idx_users_group_status');
            
            // Index for last_active_at (session management, inactivity checks)
            $table->index('last_active_at', 'idx_users_last_active');
            
            // Full-text search index for name (if supported)
            $table->index('full_name', 'idx_users_full_name');
        });

        Schema::table('warnings', function (Blueprint $table) {
            // Index for finding unacknowledged warnings
            $table->index(['is_acknowledged', 'is_resolved'], 'idx_warnings_status');
            
            // Index for response deadline checks
            $table->index('response_deadline', 'idx_warnings_deadline');
            
            // Composite index for user + status
            $table->index(['user_id', 'is_acknowledged'], 'idx_warnings_user_status');
        });

        Schema::table('blacklist_records', function (Blueprint $table) {
            // Index for finding active blacklists (where lifted_at is null)
            $table->index('lifted_at', 'idx_blacklist_lifted_at');
            
            // Index for expiration checks
            $table->index('expires_at', 'idx_blacklist_expires_at');
            
            // Composite index for user + active status
            $table->index(['user_id', 'lifted_at'], 'idx_blacklist_user_active');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            // Index for action filtering
            $table->index('action', 'idx_audit_logs_action');
            
            // Index for user filtering
            $table->index('user_id', 'idx_audit_logs_user');
            
            // Index for target type filtering
            $table->index('target_type', 'idx_audit_logs_target_type');
            
            // Composite index for date range queries
            $table->index(['created_at', 'action'], 'idx_audit_logs_date_action');
            
            // Composite index for user + date
            $table->index(['user_id', 'created_at'], 'idx_audit_logs_user_date');
        });

        Schema::table('system_configs', function (Blueprint $table) {
            // Make config_key a unique index for faster lookups
            $table->unique('config_key', 'idx_system_configs_key');
        });

        Schema::table('groups', function (Blueprint $table) {
            // Index for group name searches
            $table->index('group_name', 'idx_groups_name');
        });

        Schema::table('group_admins', function (Blueprint $table) {
            // Composite index for user + group lookups
            $table->index(['user_id', 'group_id'], 'idx_group_admins_user_group');
            
            // Index for assigned_by lookups
            $table->index('assigned_by', 'idx_group_admins_assigned_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_account_status');
            $table->dropIndex('idx_users_role_status');
            $table->dropIndex('idx_users_group_status');
            $table->dropIndex('idx_users_last_active');
            $table->dropIndex('idx_users_full_name');
        });

        Schema::table('warnings', function (Blueprint $table) {
            $table->dropIndex('idx_warnings_status');
            $table->dropIndex('idx_warnings_deadline');
            $table->dropIndex('idx_warnings_user_status');
        });

        Schema::table('blacklist_records', function (Blueprint $table) {
            $table->dropIndex('idx_blacklist_lifted_at');
            $table->dropIndex('idx_blacklist_expires_at');
            $table->dropIndex('idx_blacklist_user_active');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('idx_audit_logs_action');
            $table->dropIndex('idx_audit_logs_user');
            $table->dropIndex('idx_audit_logs_target_type');
            $table->dropIndex('idx_audit_logs_date_action');
            $table->dropIndex('idx_audit_logs_user_date');
        });

        Schema::table('system_configs', function (Blueprint $table) {
            $table->dropUnique('idx_system_configs_key');
        });

        Schema::table('groups', function (Blueprint $table) {
            $table->dropIndex('idx_groups_name');
        });

        Schema::table('group_admins', function (Blueprint $table) {
            $table->dropIndex('idx_group_admins_user_group');
            $table->dropIndex('idx_group_admins_assigned_by');
        });
    }
};
