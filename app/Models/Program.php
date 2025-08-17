<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Program extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'program_user');
    }

    public function workoutTemplates()
    {
        return $this->belongsToMany(WorkoutTemplate::class, 'program_workout_template')->withPivot('weekday');
    }
}
