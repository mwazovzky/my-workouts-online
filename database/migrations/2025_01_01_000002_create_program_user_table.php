<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProgramUserTable extends Migration
{
    public function up()
    {
        Schema::create('program_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('program_id')->constrained()->onDelete('cascade');
            $table->timestamp('assigned_at')->useCurrent();
        });
    }

    public function down()
    {
        Schema::dropIfExists('program_user');
    }
}
