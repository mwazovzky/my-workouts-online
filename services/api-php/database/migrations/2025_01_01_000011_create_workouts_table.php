<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWorkoutsTable extends Migration
{
    public function up()
    {
        Schema::create('workouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workout_template_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('status')->default('in_progress');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('workouts');
    }
}
