<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Car extends Model
{
    use HasFactory;

    protected $fillable = [
        'car_type_id',
        'name',
        'plate_number',
        'brand',
        'model_year',
        'transmission',
        'capacity',
        'price_per_day',
        'status',
        'image',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'model_year' => 'integer',
            'capacity' => 'integer',
            'price_per_day' => 'decimal:2',
        ];
    }

    public function carType(): BelongsTo
    {
        return $this->belongsTo(CarType::class);
    }
}
