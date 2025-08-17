<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProgramWorkoutTemplateTable extends Migration
{
    public function up()
    {
        Schema::create('program_workout_template', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workout_template_id')->constrained()->onDelete('cascade');
            $table->foreignId('program_id')->constrained()->onDelete('cascade');
            $table->enum('weekday', [
                'Monday',
                'Tuesday',
                'Wednesday',
                'Thursday',
                'Friday',
                'Saturday',
                'Sunday',
            ]);
        });
    }

    public function down()
    {
        Schema::dropIfExists('program_workout_template');
    }
}
