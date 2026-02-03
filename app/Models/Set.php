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
        'repetitions',
        'weight',
        'is_completed',
    ];

    protected function casts(): array
    {
        return [
            'activity_id' => 'integer',
            'order' => 'integer',
            'repetitions' => 'integer',
            'weight' => 'integer',
            'is_completed' => 'boolean',
        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }
}
