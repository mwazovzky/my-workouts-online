<?php

namespace App\Models;

use App\Enums\EffortType;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string|null $slug
 * @property string $name
 * @property string|null $description
 */
class Exercise extends Model
{
    /** @use HasFactory<\Database\Factories\ExerciseFactory> */
    use HasFactory, HasTranslations, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'equipment_id',
        'effort_type',
        'rest_time_seconds',
    ];

    public function translatableFields(): array
    {
        return ['name', 'description'];
    }

    public function casts(): array
    {
        return [
            'equipment_id' => 'integer',
            'effort_type' => EffortType::class,
            'rest_time_seconds' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Equipment, $this>
     */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    /**
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_exercise');
    }
}
