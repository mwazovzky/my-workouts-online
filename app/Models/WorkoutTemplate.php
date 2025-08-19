<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkoutTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
    ];

    public function programs()
    {
        return $this->belongsToMany(Program::class)->withPivot('weekday');
    }

    public function activities()
    {
        return $this->morphMany(Activity::class, 'workout');
    }
}
