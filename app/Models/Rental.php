<?php

namespace App\Models;

use App\Services\RentalScheduleService;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public const REFUND_NONE = 'none';

    public const REFUND_PENDING = 'pending';

    public const REFUND_PROCESSED = 'processed';

    public const CANCEL_CUSTOMER = 'customer_request';

    public const CANCEL_RENTAL_FAULT = 'rental_fault';

    public const CANCEL_NO_SHOW = 'no_show';

    public const CANCEL_IDENTITY = 'identity_failed';

    public const CANCEL_PAYMENT_EXPIRED = 'payment_expired';

    public const IDENTITY_PENDING = 'pending';

    public const IDENTITY_VERIFIED = 'verified';

    public const IDENTITY_REJECTED = 'rejected';

    public const IDENTITY_FOLLOWUP = 'followup';

    public const ADDITIONAL_CHARGE_NONE = 'none';

    public const ADDITIONAL_CHARGE_UNPAID = 'unpaid';

    public const ADDITIONAL_CHARGE_PENDING_APPROVAL = 'pending_approval';

    public const ADDITIONAL_CHARGE_PAID = 'paid';

    public const ADDITIONAL_CHARGE_REJECTED = 'rejected';

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
        'paid_amount',
        'overpayment_amount',
        'booking_channel',
        'created_by',
        'terms_accepted_at',
        'payment_bank_name',
        'payment_account_number',
        'payment_account_holder',
        'payment_submitted_at',
        'payment_rejected_at',
        'payment_rejection_reason',
        'cancelled_at',
        'cancellation_reason',
        'cancellation_type',
        'cancellation_requested_at',
        'cancellation_request_reason',
        'refund_percentage',
        'refund_amount',
        'refund_realized_amount',
        'refund_status',
        'refund_transferred_at',
        'refund_reference',
        'refund_notes',
        'refund_processed_by',
        'status_notice',
        'notes',
        'idempotency_key',
        'actual_return_at',
        'late_minutes',
        'late_fee_amount',
        'fuel_fee_amount',
        'fuel_fee_reason',
        'damage_fee_amount',
        'damage_fee_reason',
        'damage_evidence_path',
        'additional_charges_total',
        'additional_charges_assessed_at',
        'additional_charges_assessed_by',
        'additional_charge_bank_name',
        'additional_charge_account_number',
        'additional_charge_account_holder',
        'additional_charge_status',
        'additional_charge_paid_amount',
        'additional_charge_proof',
        'additional_charge_submitted_at',
        'additional_charge_verified_by',
        'additional_charge_verified_at',
        'additional_charge_payment_reference',
        'additional_charge_rejection_reason',
        'completed_at',
        'completed_by',
    ];

    /**
     * Sembunyikan path dokumen pembayaran dan bukti kerusakan agar tidak bocor di serialisasi JSON (NFR-01).
     *
     * @var list<string>
     */
    protected $hidden = [
        'payment_proof',
        'damage_evidence_path',
        'additional_charge_proof',
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
            'actual_return_at' => 'datetime',
            'payment_deadline_at' => 'datetime',
            'identity_deadline_at' => 'datetime',
            'review_followup_at' => 'datetime',
            'payment_review_blocked' => 'boolean',
            'payment_verified_at' => 'datetime',
            'payment_submitted_at' => 'datetime',
            'payment_rejected_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'cancellation_requested_at' => 'datetime',
            'refund_transferred_at' => 'datetime',
            'additional_charges_assessed_at' => 'datetime',
            'additional_charge_submitted_at' => 'datetime',
            'additional_charge_verified_at' => 'datetime',
            'completed_at' => 'datetime',
            'total_days' => 'integer',
            'late_minutes' => 'integer',
            'daily_price' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'overpayment_amount' => 'decimal:2',
            'refund_percentage' => 'integer',
            'refund_amount' => 'decimal:2',
            'refund_realized_amount' => 'decimal:2',
            'late_fee_amount' => 'decimal:2',
            'fuel_fee_amount' => 'decimal:2',
            'damage_fee_amount' => 'decimal:2',
            'additional_charges_total' => 'decimal:2',
            'additional_charge_paid_amount' => 'decimal:2',
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

    public function refundProcessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refund_processed_by');
    }

    public function additionalChargeAssessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'additional_charges_assessed_by');
    }

    public function additionalChargeVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'additional_charge_verified_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class)->orderBy('inspected_at');
    }

    public function pickupInspection(): HasOne
    {
        return $this->hasOne(Inspection::class)->where('inspection_type', Inspection::TYPE_PICKUP);
    }

    public function returnInspection(): HasOne
    {
        return $this->hasOne(Inspection::class)->where('inspection_type', Inspection::TYPE_RETURN);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(RentalPayment::class)->orderByDesc('id');
    }

    public function changeRequests(): HasMany
    {
        return $this->hasMany(RentalChangeRequest::class)->orderByDesc('id');
    }

    public function extensions(): HasMany
    {
        return $this->hasMany(RentalExtension::class)->orderByDesc('id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(RentalHistory::class)->orderByDesc('id');
    }

    /**
     * Ambil catatan arus kas aktual berbasis tanggal penerimaan dan realisasi refund (WIB).
     */
    public function cashFlows(): HasMany
    {
        // hasMany() mengikat satu pesanan ke banyak entri buku kas (parsial, lunas, tambahan, maupun refund)
        // dan diurutkan menurun berdasarkan waktu aktual terjadinya arus kas.
        return $this->hasMany(RentalCashFlow::class)->orderByDesc('occurred_at')->orderByDesc('id');
    }

    /**
     * Filter rentals that still hold a vehicle's schedule at the given moment.
     */
    #[Scope]
    protected function holdingSchedule(Builder $query, ?CarbonInterface $at = null): void
    {
        $cutoff = ($at ? $at->copy()->timezone('Asia/Jakarta') : now('Asia/Jakarta'))->format('Y-m-d H:i:s');

        // whereNot membalik kelompok kondisi yang melepaskan jadwal. IS NOT NULL
        // mencegah perbandingan timestamp null menghasilkan UNKNOWN di PostgreSQL.
        $query->whereIn('rental_status', self::HOLDING_STATUSES)
            ->whereNot(function (Builder $pending) use ($cutoff): void {
                $pending->where('rental_status', self::STATUS_PENDING)
                    ->where(function (Builder $released) use ($cutoff): void {
                        $released->where(fn (Builder $payment) => $payment->where('payment_review_blocked', false)
                            ->whereIn('payment_status', [self::PAYMENT_UNPAID, self::PAYMENT_REJECTED])
                            ->whereNotNull('payment_deadline_at')->where('payment_deadline_at', '<=', $cutoff))
                            ->orWhere(fn (Builder $identity) => $identity->whereNotNull('identity_status')
                                ->where('identity_status', self::IDENTITY_REJECTED)
                                ->whereNotNull('identity_deadline_at')->where('identity_deadline_at', '<=', $cutoff))
                            ->orWhere(fn (Builder $ended) => $ended->whereNotNull('identity_status')
                                ->whereNotNull('return_at')->where('return_at', '<=', $cutoff));
                    })->orWhere(fn (Builder $confirmed) => $confirmed->where('rental_status', self::STATUS_CONFIRMED)
                    ->whereNotNull('review_followup_at')->whereNotNull('return_at')->where('return_at', '<=', $cutoff));
            });
    }

    /**
     * Panggil setelah mengunci baris Rental. Pembayaran dan verifikasi identitas
     * dapat menyimpan hasilnya sendiri, tetapi hanya hasil lengkap mengubah status sewa.
     */
    public function confirmWhenReady(CarbonInterface $at): bool
    {
        if ($this->rental_status !== self::STATUS_PENDING || $this->payment_status !== self::PAYMENT_PAID
            || $this->remainingBalance() > 0 || ! $this->hasVerifiedIdentity()
            || ($this->identity_status !== null && $this->return_at?->lte($at))) {
            return false;
        }

        $this->transitionRentalStatus(self::STATUS_CONFIRMED, 'Identitas terverifikasi dan pembayaran lunas. Pesanan terkonfirmasi.');

        return true;
    }

    public function hasVerifiedIdentity(): bool
    {
        // Pesanan lama belum menyimpan status identitas sendiri; relasi user
        // tetap menjadi sumber verifikasi untuk baris lama.
        return $this->identity_status !== null
            ? $this->identity_status === self::IDENTITY_VERIFIED
            : ($this->user?->isVerified() ?? false);
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
     * Determine whether an unpaid or rejected booking has exceeded its upload deadline without pending verification.
     */
    public function isPaymentExpired(?CarbonInterface $at = null): bool
    {
        if ($this->rental_status !== self::STATUS_PENDING || $this->payment_review_blocked
            || ! in_array($this->payment_status, [self::PAYMENT_UNPAID, self::PAYMENT_REJECTED], true)) {
            return false;
        }

        if ($this->payment_deadline_at === null) {
            return false;
        }

        $reference = $at ? $at->copy()->timezone('Asia/Jakarta') : now('Asia/Jakarta');

        return $this->payment_deadline_at->copy()->timezone('Asia/Jakarta')->lte($reference);
    }

    public function isIdentityExpired(CarbonInterface $at): bool
    {
        return $this->rental_status === self::STATUS_PENDING && $this->identity_status === self::IDENTITY_REJECTED
            && $this->identity_deadline_at?->lte($at);
    }

    /**
     * Calculate remaining unpaid balance for this rental.
     */
    public function remainingBalance(): float
    {
        return max(0.0, round((float) $this->total_amount - (float) $this->paid_amount, 2));
    }

    /**
     * Determine whether the customer/admin can upload or re-upload a payment proof right now.
     */
    public function canUploadPaymentProof(?CarbonInterface $at = null): bool
    {
        if ($this->rental_status !== self::STATUS_PENDING) {
            return false;
        }

        if (! in_array($this->payment_status, [self::PAYMENT_UNPAID, self::PAYMENT_REJECTED], true)) {
            return false;
        }

        $reference = $at ?? now('Asia/Jakarta');

        return ! $this->payment_review_blocked && ! $this->isPaymentExpired($reference)
            && ! $this->isIdentityExpired($reference);
    }

    /**
     * Check whether pending payment verification has exceeded 1 hour SLA (SYS-06).
     */
    public function isAdminVerificationOverdue(?CarbonInterface $at = null): bool
    {
        if ($this->rental_status !== self::STATUS_PENDING || $this->payment_status !== self::PAYMENT_PENDING_APPROVAL) {
            return false;
        }

        if ($this->payment_submitted_at === null) {
            return false;
        }

        $reference = $at ? $at->copy()->timezone('Asia/Jakarta') : now('Asia/Jakarta');

        return RentalScheduleService::reviewOverdue($this->payment_submitted_at, $reference);
    }

    /**
     * Record an immutable audit trail entry in rental_histories (SYS-13).
     *
     * @param  array<string, mixed>  $metadata
     */
    public function recordHistory(
        ?User $actor,
        string $action,
        ?string $fromStatus = null,
        ?string $toStatus = null,
        ?string $reason = null,
        array $metadata = []
    ): RentalHistory {
        // create() melalui relasi histories() menyisipkan satu catatan kronologis
        // berisi pelaku, aksi, status asal/tujuan, alasan, dan metadata terstruktur.
        return $this->histories()->create([
            'actor_id' => $actor?->id,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'reason' => $reason,
            'metadata' => $metadata !== [] ? $metadata : null,
            'created_at' => now('Asia/Jakarta'),
        ]);
    }

    /**
     * Determine whether this booking currently holds the vehicle schedule.
     */
    public function isHoldingSchedule(?CarbonInterface $at = null): bool
    {
        $reference = $at ?? now('Asia/Jakarta');

        return in_array($this->rental_status, self::HOLDING_STATUSES, true)
            && ! $this->isPaymentExpired($reference) && ! $this->isIdentityExpired($reference)
            && ! ($this->return_at?->lte($reference)
                && (($this->rental_status === self::STATUS_PENDING && $this->identity_status !== null)
                    || ($this->rental_status === self::STATUS_CONFIRMED && $this->review_followup_at !== null)));
    }

    /**
     * Determine whether this booking can still be cancelled directly while unpaid.
     */
    public function canBeCancelledUnpaid(): bool
    {
        return $this->rental_status === self::STATUS_PENDING
            && $this->payment_status === self::PAYMENT_UNPAID
            && $this->payment_proof === null
            && ! $this->isPaymentExpired()
            && ! $this->isIdentityExpired(now('Asia/Jakarta'));
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
     * Return a human-readable Indonesian label for refund_status.
     */
    public function refundStatusLabel(): string
    {
        return match ($this->refund_status) {
            self::REFUND_PENDING => 'Menunggu Transfer Refund',
            self::REFUND_PROCESSED => 'Refund Sudah Ditransfer',
            default => 'Tidak Ada Refund',
        };
    }

    /**
     * Hitung sisa kewajiban pengembalian dana yang belum direalisasikan admin.
     */
    public function pendingRefundBalance(): float
    {
        return max(0.0, round((float) $this->refund_amount - (float) $this->refund_realized_amount, 2));
    }

    /**
     * Hitung sisa tagihan biaya tambahan (denda telat, BBM, kerusakan) yang masih harus dibayar.
     */
    public function remainingAdditionalChargeBalance(): float
    {
        return max(0.0, round((float) $this->additional_charges_total - (float) $this->additional_charge_paid_amount, 2));
    }

    /**
     * Periksa apakah transaksi yang sedang berjalan telah melewati batas waktu pengembalian yang disepakati.
     */
    public function isReturnOverdue(?CarbonInterface $at = null): bool
    {
        if ($this->rental_status !== self::STATUS_PICKED_UP || $this->return_at === null) {
            return false;
        }

        $reference = $at ? $at->copy()->timezone('Asia/Jakarta') : now('Asia/Jakarta');

        return $this->return_at->copy()->timezone('Asia/Jakarta')->lessThan($reference);
    }

    /**
     * Kembalikan label status pembayaran biaya tambahan berbahasa Indonesia.
     */
    public function additionalChargeStatusLabel(): string
    {
        return match ($this->additional_charge_status) {
            self::ADDITIONAL_CHARGE_UNPAID => 'Menunggu Pembayaran Tagihan Tambahan',
            self::ADDITIONAL_CHARGE_PENDING_APPROVAL => 'Bukti Terunggah — Menunggu Verifikasi Admin',
            self::ADDITIONAL_CHARGE_PAID => 'Tagihan Tambahan Lunas',
            self::ADDITIONAL_CHARGE_REJECTED => 'Bukti Tagihan Tambahan Ditolak',
            default => 'Tidak Ada Tagihan Tambahan',
        };
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
