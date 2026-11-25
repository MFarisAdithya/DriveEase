<?php

namespace App\Models;

use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rental extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_PICKED_UP = 'picked_up';

    public const STATUS_RETURNED = 'returned';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    public const PAYMENT_UNPAID = 'unpaid';

    public const PAYMENT_PENDING_APPROVAL = 'pending_approval';

    public const PAYMENT_PAID = 'paid';

    public const PAYMENT_REJECTED = 'rejected';

    public const CHANNEL_ONLINE = 'online';

    public const CHANNEL_WALK_IN = 'walk_in';

    /**
     * Status penyewaan yang menahan jadwal kendaraan agar tidak bentrok dengan pesanan lain.
     *
     * @var list<string>
     */
    public const HOLDING_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONFIRMED,
        self::STATUS_PICKED_UP,
    ];

    /**
     * Peta transisi status penyewaan yang sah sesuai aturan alur transaksi SKPL (SYS-07).
     *
     * @var array<string, list<string>>
     */
    public const ALLOWED_RENTAL_TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_CONFIRMED, self::STATUS_CANCELLED, self::STATUS_EXPIRED],
        self::STATUS_CONFIRMED => [self::STATUS_PICKED_UP, self::STATUS_CANCELLED],
        self::STATUS_PICKED_UP => [self::STATUS_RETURNED],
        self::STATUS_RETURNED => [self::STATUS_COMPLETED],
        self::STATUS_COMPLETED => [],
        self::STATUS_CANCELLED => [],
        self::STATUS_EXPIRED => [],
    ];

    /**
     * Peta transisi status pembayaran yang sah secara terpisah dari status penyewaan (SYS-07).
     *
     * @var array<string, list<string>>
     */
    public const ALLOWED_PAYMENT_TRANSITIONS = [
        self::PAYMENT_UNPAID => [self::PAYMENT_PENDING_APPROVAL],
        self::PAYMENT_PENDING_APPROVAL => [self::PAYMENT_PAID, self::PAYMENT_REJECTED],
        self::PAYMENT_REJECTED => [self::PAYMENT_PENDING_APPROVAL],
        self::PAYMENT_PAID => [],
    ];

    /**
     * Atribut rental_status, payment_status, dan kolom verifikasi sengaja tidak dimasukkan
     * ke $fillable untuk mencegah manipulasi status transaksi lewat mass assignment.
     *
     * @var list<string>
     */
    protected $fillable = [
        'rental_code',
        'user_id',
        'car_id',
        'start_date',
        'end_date',
        'pickup_at',
        'return_at',
        'actual_pickup_at',
        'payment_deadline_at',
        'total_days',
        'daily_price',
        'total_amount',
        'booking_channel',
        'created_by',
        'terms_accepted_at',
        'payment_bank_name',
        'payment_account_number',
        'payment_account_holder',
        'cancelled_at',
        'cancellation_reason',
        'status_notice',
        'notes',
        'idempotency_key',
    ];

    /**
     * Konversi tipe kolom jadwal, nominal tarif, dan waktu penting secara konsisten.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'pickup_at' => 'datetime',
            'return_at' => 'datetime',
            'actual_pickup_at' => 'datetime',
            'payment_deadline_at' => 'datetime',
            'payment_verified_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'total_days' => 'integer',
            'daily_price' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function paymentVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payment_verified_by');
    }

    /**
     * Filter rentals that still hold a vehicle's schedule at the given moment.
     */
    #[Scope]
    protected function holdingSchedule(Builder $query, ?CarbonInterface $at = null): void
    {
        // Format waktu WIB dipakai agar perbandingan kolom timestamp PostgreSQL konsisten.
        // Pesanan pending yang belum mengunggah bukti dan sudah melewati tenggat tidak lagi
        // menahan jadwal kendaraan, sedangkan bukti yang dikirim tepat waktu tetap ditahan.
        $cutoff = ($at ? $at->copy()->timezone('Asia/Jakarta') : now('Asia/Jakarta'))->format('Y-m-d H:i:s');

        $query->whereIn('rental_status', self::HOLDING_STATUSES)
            ->whereRaw(
                'NOT (rental_status = ? AND payment_status = ? AND payment_proof IS NULL AND payment_deadline_at IS NOT NULL AND payment_deadline_at <= ?)',
                [self::STATUS_PENDING, self::PAYMENT_UNPAID, $cutoff]
            );
    }

    /**
     * Check whether the rental status may legally transition to the given target status.
     */
    public function canTransitionRentalStatusTo(string $target): bool
    {
        $allowed = self::ALLOWED_RENTAL_TRANSITIONS[$this->rental_status] ?? [];

        return in_array($target, $allowed, true);
    }

    /**
     * Transition rental_status strictly according to ALLOWED_RENTAL_TRANSITIONS.
     */
    public function transitionRentalStatus(string $target, ?string $notice = null): void
    {
        // Jika transisi tidak ada pada daftar yang diizinkan, lempar DomainException
        // agar perubahan status ilegal berhenti sebelum menyentuh database.
        if (! $this->canTransitionRentalStatusTo($target)) {
            throw new DomainException("Transisi status penyewaan dari '{$this->rental_status}' ke '{$target}' tidak diizinkan.");
        }

        $this->rental_status = $target;

        if ($notice !== null) {
            $this->status_notice = $notice;
        }
    }

    /**
     * Check whether the payment status may legally transition to the given target status.
     */
    public function canTransitionPaymentStatusTo(string $target): bool
    {
        $allowed = self::ALLOWED_PAYMENT_TRANSITIONS[$this->payment_status] ?? [];

        return in_array($target, $allowed, true);
    }

    /**
     * Transition payment_status strictly according to ALLOWED_PAYMENT_TRANSITIONS.
     */
    public function transitionPaymentStatus(string $target): void
    {
        if (! $this->canTransitionPaymentStatusTo($target)) {
            throw new DomainException("Transisi status pembayaran dari '{$this->payment_status}' ke '{$target}' tidak diizinkan.");
        }

        $this->payment_status = $target;
    }

    /**
     * Record actual pickup timestamp without altering the agreed return deadline (SYS-03).
     */
    public function recordActualPickup(CarbonInterface $actualPickupAt): void
    {
        // Keterlambatan pelanggan saat mengambil kendaraan tidak menggeser return_at
        // maupun end_date yang sudah disepakati pada saat pemesanan awal.
        $this->actual_pickup_at = $actualPickupAt;
    }

    /**
     * Determine whether an unpaid booking has exceeded its upload deadline without proof.
     */
    public function isPaymentExpired(?CarbonInterface $at = null): bool
    {
        if ($this->rental_status !== self::STATUS_PENDING || $this->payment_status !== self::PAYMENT_UNPAID) {
            return false;
        }

        if ($this->payment_proof !== null || $this->payment_deadline_at === null) {
            return false;
        }

        $reference = $at ? $at->copy()->timezone('Asia/Jakarta') : now('Asia/Jakarta');

        return $this->payment_deadline_at->copy()->timezone('Asia/Jakarta')->lte($reference);
    }

    /**
     * Determine whether this booking currently holds the vehicle schedule.
     */
    public function isHoldingSchedule(?CarbonInterface $at = null): bool
    {
        return in_array($this->rental_status, self::HOLDING_STATUSES, true)
            && ! $this->isPaymentExpired($at);
    }

    /**
     * Determine whether this booking can still be cancelled directly while unpaid.
     */
    public function canBeCancelledUnpaid(): bool
    {
        return $this->rental_status === self::STATUS_PENDING
            && $this->payment_status === self::PAYMENT_UNPAID
            && $this->payment_proof === null;
    }

    /**
     * Return a human-readable Indonesian label for rental_status.
     */
    public function rentalStatusLabel(): string
    {
        return match ($this->rental_status) {
            self::STATUS_PENDING => 'Menunggu Pembayaran / Konfirmasi',
            self::STATUS_CONFIRMED => 'Terkonfirmasi',
            self::STATUS_PICKED_UP => 'Sedang Berjalan',
            self::STATUS_RETURNED => 'Sudah Dikembalikan',
            self::STATUS_COMPLETED => 'Selesai',
            self::STATUS_CANCELLED => 'Dibatalkan',
            self::STATUS_EXPIRED => 'Kedaluwarsa',
            default => ucfirst((string) $this->rental_status),
        };
    }

    /**
     * Return a human-readable Indonesian label for payment_status.
     */
    public function paymentStatusLabel(): string
    {
        return match ($this->payment_status) {
            self::PAYMENT_UNPAID => 'Belum Dibayar',
            self::PAYMENT_PENDING_APPROVAL => 'Menunggu Verifikasi Admin',
            self::PAYMENT_PAID => 'Lunas',
            self::PAYMENT_REJECTED => 'Bukti Ditolak',
            default => ucfirst((string) $this->payment_status),
        };
    }

    /**
     * Return a human-readable Indonesian label for booking_channel.
     */
    public function channelLabel(): string
    {
        return $this->booking_channel === self::CHANNEL_WALK_IN ? 'Pemesanan Langsung (Admin)' : 'Pemesanan Online';
    }

    /**
     * Return the current status notice or next action guidance for dashboard and detail pages (SYS-14).
     */
    public function nextActionNotice(): string
    {
        if (is_string($this->status_notice) && $this->status_notice !== '') {
            return $this->status_notice;
        }

        return match ($this->rental_status) {
            self::STATUS_PENDING => 'Segera lakukan transfer sesuai nominal tagihan sebelum batas waktu pembayaran berakhir.',
            self::STATUS_CONFIRMED => 'Pembayaran lunas terverifikasi. Datang sesuai jadwal pengambilan dengan membawa identitas asli.',
            self::STATUS_PICKED_UP => 'Kendaraan sedang dalam masa sewa. Kembalikan sesuai jadwal agar terhindar dari denda.',
            self::STATUS_RETURNED => 'Kendaraan telah dikembalikan dan menunggu penyelesaian transaksi.',
            self::STATUS_COMPLETED => 'Transaksi sewa telah selesai.',
            self::STATUS_CANCELLED => 'Pesanan ini telah dibatalkan dan jadwal kendaraan sudah dilepas.',
            self::STATUS_EXPIRED => 'Batas waktu pembayaran terlampaui sehingga penahanan kendaraan dilepas otomatis.',
            default => 'Pantau rincian pesanan Anda secara berkala.',
        };
    }
}
