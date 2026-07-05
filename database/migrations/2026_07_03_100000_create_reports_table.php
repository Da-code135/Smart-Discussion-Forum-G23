<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateReportsTable extends Migration
{
    public function up()
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->morphs('reportable'); // Polymorphic relation for topics/posts/replies
            $table->text('reason');
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('reports');
    }
}