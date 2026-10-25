<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property string|null $slug
 * @property string $name
 * @property string|null $description
 */
class Program extends Model
{
    /** @use HasFactory<\Database\Factories\ProgramFactory> */
    use HasFactory, HasTranslations;

    protected $fillable = [];

    public function translatableFields(): array
    {
        return ['name', 'description'];
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'program_user');
    }

    /**
     * @return BelongsToMany<WorkoutTemplate, $this>
     */
    public function workoutTemplates(): BelongsToMany
    {
        return $this->belongsToMany(WorkoutTemplate::class)->withPivot('weekday');
    }
}
