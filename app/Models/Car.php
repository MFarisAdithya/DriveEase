<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

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

    /**
     * Resolve the vehicle's display photo (unit photo or configured model fallback).
     *
     * @return array{url: ?string, is_model: bool}
     */
    public function resolvePhoto(): array
    {
        if ($this->image && Storage::disk('public')->exists($this->image)) {
            return [
                'url' => Storage::disk('public')->url($this->image),
                'is_model' => false,
            ];
        }

        $modelPhoto = collect(config('catalog.photos'))->first(
            fn (string $file, string $model): bool => str_starts_with(strtolower(trim($this->name)).' ', $model.' ')
        );

        if ($modelPhoto) {
            return [
                'url' => asset('images/models/'.$modelPhoto),
                'is_model' => true,
            ];
        }

        return [
            'url' => null,
            'is_model' => false,
        ];
    }
}
