<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalPayment extends Model
{
    use HasFactory;

    public const TYPE_BOOKING = 'booking';

    public const TYPE_SHORTFALL = 'shortfall';

    public const STATUS_PENDING_APPROVAL = 'pending_approval';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'rental_id',
        'uploaded_by',
        'payment_type',
        'claimed_amount',
        'verified_amount',
        'proof_path',
        'status',
        'rejection_reason',
        'verified_by',
        'verified_at',
        'received_at',
        'submitted_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'proof_path',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'claimed_amount' => 'decimal:2',
            'verified_amount' => 'decimal:2',
            'verified_at' => 'datetime',
            'received_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function isPendingApproval(): bool
    {
        return $this->status === self::STATUS_PENDING_APPROVAL;
    }

    /**
     * Check whether admin verification has exceeded the 1-hour SLA (SYS-06).
     */
    public function isAdminVerificationOverdue(?CarbonInterface $at = null): bool
    {
        if (! $this->isPendingApproval() || $this->submitted_at === null) {
            return false;
        }

        $reference = $at ? $at->copy()->timezone('Asia/Jakarta') : now('Asia/Jakarta');

        // Kebijakan SKPL menetapkan batas verifikasi admin maksimal 1 jam; jika terlampaui,
        // sistem menampilkan peringatan kepada admin tanpa membatalkan pesanan secara otomatis.
        return $this->submitted_at->copy()->timezone('Asia/Jakarta')->addHour()->lessThan($reference);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING_APPROVAL => 'Menunggu Verifikasi Admin',
            self::STATUS_APPROVED => 'Disetujui',
            self::STATUS_REJECTED => 'Ditolak',
            default => ucfirst((string) $this->status),
        };
    }
}
