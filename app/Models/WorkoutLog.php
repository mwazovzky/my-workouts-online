<?php

namespace App\Models;

use App\QueryBuilders\WorkoutLogBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Query\Builder;

class WorkoutLog extends Model
{
    use HasFactory;

    public function newEloquentBuilder($query): WorkoutLogBuilder
    {
        /** @var Builder $query */
        return new WorkoutLogBuilder($query);
    }

    protected $fillable = [
        'user_id',
        'workout_template_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'workout_template_id' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'workout');
    }

    public function workoutTemplate(): BelongsTo
    {
        return $this->belongsTo(WorkoutTemplate::class);
    }
}
