<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property string|null $slug
 * @property string $name
 * @property string|null $description
 * @property-read (\Illuminate\Database\Eloquent\Relations\Pivot&object{weekday: string})|null $pivot Set when loaded through a program
 */
class WorkoutTemplate extends Model
{
    /** @use HasFactory<\Database\Factories\WorkoutTemplateFactory> */
    use HasFactory, HasTranslations;

    protected $fillable = [];

    public function translatableFields(): array
    {
        return ['name', 'description'];
    }

    /**
     * @return BelongsToMany<Program, $this>
     */
    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class)->withPivot('weekday');
    }

    /**
     * @return MorphMany<Activity, $this>
     */
    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'workout');
    }
}
