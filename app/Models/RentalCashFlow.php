<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalCashFlow extends Model
{
    use HasFactory;

    public const DIRECTION_INFLOW = 'inflow';

    public const DIRECTION_OUTFLOW = 'outflow';

    public const CATEGORY_MAIN_PAYMENT = 'main_payment';

    public const CATEGORY_EXTENSION_PAYMENT = 'extension_payment';

    public const CATEGORY_ADDITIONAL_CHARGE = 'additional_charge_payment';

    public const CATEGORY_RENTAL_REFUND = 'rental_refund';

    public const CATEGORY_EXTENSION_REFUND = 'extension_refund';

    /**
     * Kolom arus kas aktual yang dicatat dari tanggal penerimaan dana maupun realisasi refund (A-01, A-12).
     *
     * @var list<string>
     */
    protected $fillable = [
        'rental_id',
        'rental_payment_id',
        'rental_extension_id',
        'recorded_by',
        'direction',
        'category',
        'amount',
        'occurred_at',
        'reference',
        'notes',
    ];

    /**
     * Konversi tipe nominal desimal dan waktu terjadinya transaksi kas.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * Hubungkan baris kas dengan transaksi penyewaan induknya.
     */
    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    /**
     * Hubungkan arus masuk pembayaran utama dengan bukti pembayaran terkait apabila ada.
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(RentalPayment::class, 'rental_payment_id');
    }

    /**
     * Hubungkan arus kas perpanjangan dengan catatan perpanjangan terkait apabila ada.
     */
    public function extension(): BelongsTo
    {
        return $this->belongsTo(RentalExtension::class, 'rental_extension_id');
    }

    /**
     * Hubungkan baris kas dengan akun admin yang mengonfirmasi tanggal kas aktual.
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Terjemahkan arah arus kas ke label Bahasa Indonesia untuk tampilan laporan dan rincian pesanan.
     */
    public function directionLabel(): string
    {
        return match ($this->direction) {
            self::DIRECTION_INFLOW => 'Kas Masuk',
            self::DIRECTION_OUTFLOW => 'Kas Keluar (Refund)',
            default => ucfirst((string) $this->direction),
        };
    }

    /**
     * Terjemahkan kategori arus kas ke label Bahasa Indonesia yang mudah ditelusuri ke transaksi.
     */
    public function categoryLabel(): string
    {
        return match ($this->category) {
            self::CATEGORY_MAIN_PAYMENT => 'Pembayaran Sewa Utama',
            self::CATEGORY_EXTENSION_PAYMENT => 'Pembayaran Perpanjangan Sewa',
            self::CATEGORY_ADDITIONAL_CHARGE => 'Pembayaran Tagihan Tambahan',
            self::CATEGORY_RENTAL_REFUND => 'Realisasi Refund Sewa / Kelebihan Bayar',
            self::CATEGORY_EXTENSION_REFUND => 'Realisasi Refund Perpanjangan',
            default => ucfirst((string) $this->category),
        };
    }
}
