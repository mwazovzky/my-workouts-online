<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property string|null $slug
 * @property string $name
 */
class Category extends Model
{
    /** @use HasFactory<\Database\Factories\CategoryFactory> */
    use HasFactory, HasTranslations;

    protected $fillable = [];

    public function translatableFields(): array
    {
        return ['name'];
    }

    /**
     * @return BelongsToMany<Exercise, $this>
     */
    public function exercises(): BelongsToMany
    {
        return $this->belongsToMany(Exercise::class, 'category_exercise');
    }
}
