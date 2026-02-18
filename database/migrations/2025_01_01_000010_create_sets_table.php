<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSetsTable extends Migration
{
    public function up()
    {
        Schema::create('sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->onDelete('cascade');
            $table->integer('order');
            $table->integer('effort_value');
            $table->integer('difficulty_value')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->timestamps();
            $table->unique(['activity_id', 'order']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('sets');
    }
}
