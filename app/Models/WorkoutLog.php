<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkoutLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'workout_template_id',
        'date',
        'status',
        'completed_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function activities()
    {
        return $this->morphMany(Activity::class, 'workout');
    }

    public function workoutTemplate()
    {
        return $this->belongsTo(WorkoutTemplate::class);
    }
}
