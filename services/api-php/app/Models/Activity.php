<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Activity extends Model
{
    use HasFactory;

    protected $fillable = [
        'workout_id',
        'workout_type',
        'exercise_id',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'workout_id' => 'integer',
            'exercise_id' => 'integer',
            'order' => 'integer',
        ];
    }

    public function workout(): MorphTo
    {
        return $this->morphTo();
    }

    public function exercise(): BelongsTo
    {
        // Include soft-deleted (retired) exercises so historical workouts and
        // templates still resolve the exercise name, effort, and difficulty.
        return $this->belongsTo(Exercise::class)->withTrashed();
    }

    public function sets(): HasMany
    {
        return $this->hasMany(Set::class);
    }
}
