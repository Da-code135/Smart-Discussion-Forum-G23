<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds title, message, and group_id columns to the notifications table
     * so notifications can be created without relying solely on the JSON data field.
     */
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('title')->nullable()->after('type');
            $table->text('message')->nullable()->after('title');
            $table->foreignId('group_id')->nullable()->constrained('groups')->after('message');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn('group_id');
            $table->dropColumn('message');
            $table->dropColumn('title');
        });
    }
};
