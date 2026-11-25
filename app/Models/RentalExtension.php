<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalExtension extends Model
{
    use HasFactory;

    public const STATUS_PENDING_APPROVAL = 'pending_approval';

    public const STATUS_AWAITING_PAYMENT = 'awaiting_payment';

    public const STATUS_PAYMENT_SUBMITTED = 'payment_submitted';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_REJECTED = 'rejected';

    /**
     * Kolom yang dapat diisi secara massal oleh layanan perpanjangan sewa.
     *
     * @var list<string>
     */
    protected $fillable = [
        'rental_id',
        'requested_by',
        'additional_days',
        'previous_return_at',
        'proposed_return_at',
        'daily_price',
        'additional_amount',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'payment_proof',
        'payment_submitted_at',
        'payment_deadline_at',
        'payment_verified_by',
        'payment_verified_at',
        'payment_rejection_reason',
        'notes',
    ];

    /**
     * Sembunyikan path berkas bukti pembayaran dari serialisasi JSON agar tidak bocor (NFR-01).
     *
     * @var list<string>
     */
    protected $hidden = [
        'payment_proof',
    ];

    /**
     * Konversi tipe kolom angka, nominal rupiah, dan stempel waktu secara konsisten.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'additional_days' => 'integer',
            'previous_return_at' => 'datetime',
            'proposed_return_at' => 'datetime',
            'daily_price' => 'decimal:2',
            'additional_amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'payment_submitted_at' => 'datetime',
            'payment_deadline_at' => 'datetime',
            'payment_verified_at' => 'datetime',
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

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function paymentVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payment_verified_by');
    }

    /**
     * Periksa apakah pengajuan perpanjangan masih terbuka dan belum selesai/ditolak.
     */
    public function isOpen(): bool
    {
        // in_array() dengan mode ketat (true) memeriksa apakah status saat ini berada pada
        // daftar status aktif agar pelanggan tidak membuat dua pengajuan perpanjangan sekaligus.
        return in_array($this->status, [
            self::STATUS_PENDING_APPROVAL,
            self::STATUS_AWAITING_PAYMENT,
            self::STATUS_PAYMENT_SUBMITTED,
        ], true);
    }

    /**
     * Periksa apakah batas pembayaran perpanjangan telah lewat saat belum ada bukti terunggah.
     */
    public function isPaymentExpired(?CarbonInterface $at = null): bool
    {
        if ($this->status !== self::STATUS_AWAITING_PAYMENT || $this->payment_deadline_at === null) {
            return false;
        }

        $reference = $at ? $at->copy()->timezone('Asia/Jakarta') : now('Asia/Jakarta');

        return $this->payment_deadline_at->copy()->timezone('Asia/Jakarta')->lte($reference);
    }

    /**
     * Kembalikan label status perpanjangan berbahasa Indonesia untuk tampilan antarmuka.
     */
    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING_APPROVAL => 'Menunggu Persetujuan Admin',
            self::STATUS_AWAITING_PAYMENT => 'Disetujui — Menunggu Pembayaran Tambahan',
            self::STATUS_PAYMENT_SUBMITTED => 'Bukti Terunggah — Menunggu Verifikasi Admin',
            self::STATUS_COMPLETED => 'Perpanjangan Aktif & Lunas',
            self::STATUS_REJECTED => 'Perpanjangan Ditolak',
            default => ucfirst((string) $this->status),
        };
    }
}
