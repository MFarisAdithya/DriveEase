<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalChangeRequest extends Model
{
    use HasFactory;

    public const TYPE_RESCHEDULE = 'reschedule';

    public const TYPE_CAR_REPLACEMENT = 'car_replacement';

    public const STATUS_PENDING_ADMIN = 'pending_admin';

    public const STATUS_PENDING_CUSTOMER = 'pending_customer';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'rental_id',
        'request_type',
        'initiated_by_role',
        'requested_by',
        'proposed_car_id',
        'proposed_pickup_at',
        'proposed_total_days',
        'proposed_return_at',
        'proposed_total_amount',
        'waive_price_difference',
        'reason',
        'status',
        'responded_by',
        'responded_at',
        'response_notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'proposed_pickup_at' => 'datetime',
            'proposed_total_days' => 'integer',
            'proposed_return_at' => 'datetime',
            'proposed_total_amount' => 'decimal:2',
            'waive_price_difference' => 'boolean',
            'responded_at' => 'datetime',
        ];
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function responder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    public function proposedCar(): BelongsTo
    {
        return $this->belongsTo(Car::class, 'proposed_car_id');
    }

    public function typeLabel(): string
    {
        return $this->request_type === self::TYPE_CAR_REPLACEMENT
            ? 'Penggantian Kendaraan'
            : 'Perubahan Jadwal';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING_ADMIN => 'Menunggu Persetujuan Admin',
            self::STATUS_PENDING_CUSTOMER => 'Menunggu Persetujuan Pelanggan',
            self::STATUS_APPROVED => 'Disetujui',
            self::STATUS_REJECTED => 'Ditolak',
            default => ucfirst((string) $this->status),
        };
    }
}
