<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Blueprint;

class CreateLecturerGroupAccessTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('lecturer_group_access', function (Blueprint $table) {
            $table->foreignId('lecturer_id')->constrained('users');
            $table->foreignId('group_id')->constrained('groups');
            $table->timestamps();
            $table->unique(['lecturer_id', 'group_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lecturer_group_access');
    }
}