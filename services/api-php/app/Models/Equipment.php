<?php

namespace App\Models;

use App\Enums\DifficultyUnit;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Equipment extends Model
{
    use HasFactory, HasTranslations;

    protected $fillable = [
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

    public function exercises(): HasMany
    {
        return $this->hasMany(Exercise::class);
    }
}
