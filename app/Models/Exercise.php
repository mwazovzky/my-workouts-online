<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Exercise extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'equipment_id',
    ];

    public function equipment()
    {
        return $this->belongsTo(Equipment::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'category_exercise');
    }
}
