<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateExercisesTable extends Migration
{
    public function up()
    {
        Schema::create('exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained('equipment')->onDelete('restrict');
            $table->string('effort_type')->default('repetitions');
            $table->unsignedInteger('rest_time_seconds')->default(90);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('exercises');
    }
}
