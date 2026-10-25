<?php

namespace App\Models;

use App\Enums\DifficultyUnit;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string|null $slug
 * @property string $name
 */
class Equipment extends Model
{
    /** @use HasFactory<\Database\Factories\EquipmentFactory> */
    use HasFactory, HasTranslations;

    protected $fillable = [
        'name',
        'difficulty_unit',
    ];

    public function translatableFields(): array
    {
        return ['name'];
    }

    protected function casts(): array
    {
        return [
            'difficulty_unit' => DifficultyUnit::class,
        ];
    }

    /**
     * @return HasMany<Exercise, $this>
     */
    public function exercises(): HasMany
    {
        return $this->hasMany(Exercise::class);
    }
}
