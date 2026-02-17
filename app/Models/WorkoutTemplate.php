<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class WorkoutTemplate extends Model
{
    use HasFactory, HasTranslations;

    protected $fillable = [];

    public function translatableFields(): array
    {
        return ['name', 'description'];
    }

    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class)->withPivot('weekday');
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'workout');
    }
}
