<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Equipment extends Model
{
    use HasFactory, HasTranslations;

    protected $fillable = [];

    public function translatableFields(): array
    {
        return ['name', 'unit'];
    }

    public function exercises(): HasMany
    {
        return $this->hasMany(Exercise::class);
    }
}
