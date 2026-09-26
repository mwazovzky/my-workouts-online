<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateActivitiesTable extends Migration
{
    public function up()
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->morphs('workout');
            $table->foreignId('exercise_id')->constrained()->onDelete('cascade');
            $table->integer('order');
            $table->timestamps();
            $table->unique(['workout_type', 'workout_id', 'order']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('activities');
    }
}
