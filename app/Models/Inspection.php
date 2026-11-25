<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inspection extends Model
{
    use HasFactory;

    public const TYPE_PICKUP = 'pickup';

    public const TYPE_RETURN = 'return';

    /**
     * Peta tingkat bahan bakar sesuai kolom enum tabel inspections dan label Bahasa Indonesia.
     *
     * @var array<string, string>
     */
    public const FUEL_LEVEL_LABELS = [
        'empty' => 'Kosong (E)',
        'quarter' => 'Seperempat (1/4)',
        'half' => 'Setengah (1/2)',
        'three_quarters' => 'Tiga Perempat (3/4)',
        'full' => 'Penuh (F)',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'rental_id',
        'inspector_id',
        'inspection_type',
        'identity_checked',
        'odometer_km',
        'fuel_level',
        'condition_notes',
        'condition_photo',
        'has_damage',
        'damage_description',
        'penalty_fee',
        'penalty_reason',
        'inspected_at',
    ];

    /**
     * Sembunyikan path foto kondisi agar berkas privat hanya dapat dibuka melalui route terotorisasi (NFR-01).
     *
     * @var list<string>
     */
    protected $hidden = [
        'condition_photo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'identity_checked' => 'boolean',
            'odometer_km' => 'integer',
            'has_damage' => 'boolean',
            'penalty_fee' => 'decimal:2',
            'inspected_at' => 'datetime',
        ];
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }

    public function typeLabel(): string
    {
        return $this->inspection_type === self::TYPE_PICKUP
            ? 'Inspeksi Pengambilan (Awal)'
            : 'Inspeksi Pengembalian (Akhir)';
    }

    public function fuelLevelLabel(): string
    {
        return self::FUEL_LEVEL_LABELS[$this->fuel_level] ?? ucfirst((string) $this->fuel_level);
    }
}
