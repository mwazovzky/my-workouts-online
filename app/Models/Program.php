<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Program extends Model
{
    use HasFactory, HasTranslations;

    protected $fillable = [];

    public function translatableFields(): array
    {
        return ['name', 'description'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'program_user');
    }

    public function workoutTemplates(): BelongsToMany
    {
        return $this->belongsToMany(WorkoutTemplate::class)->withPivot('weekday');
    }
}
