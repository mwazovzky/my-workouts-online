<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Set extends Model
{
    use HasFactory;

    protected $fillable = [
        'activity_id',
        'order',
        'effort_value',
        'difficulty_value',
        'is_completed',
    ];

    protected function casts(): array
    {
        return [
            'activity_id' => 'integer',
            'order' => 'integer',
            'effort_value' => 'integer',
            'difficulty_value' => 'integer',
            'is_completed' => 'boolean',
        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }
}
