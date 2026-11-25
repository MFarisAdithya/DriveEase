<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Car extends Model
{
    use HasFactory;

    public const READINESS_READY = 'ready';

    public const READINESS_IN_USE = 'in_use';

    public const READINESS_NEEDS_PREPARATION = 'needs_preparation';

    public const READINESS_NEEDS_MAINTENANCE = 'needs_maintenance';

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
        'maintenance_reason',
        'readiness_status',
        'readiness_notes',
        'readiness_checked_by',
        'readiness_checked_at',
        'image',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'model_year' => 'integer',
            'capacity' => 'integer',
            'price_per_day' => 'decimal:2',
            'archived_at' => 'datetime',
            'readiness_checked_at' => 'datetime',
        ];
    }

    public function carType(): BelongsTo
    {
        return $this->belongsTo(CarType::class);
    }

    public function readinessChecker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'readiness_checked_by');
    }

    /**
     * Define the one-to-many relationship to rental transactions for this vehicle.
     */
    public function rentals(): HasMany
    {
        // hasMany() menghubungkan satu kendaraan (cars.id) dengan banyak baris
        // transaksi penyewaan pada tabel rentals melalui foreign key car_id.
        return $this->hasMany(Rental::class);
    }

    #[Scope]
    protected function activeInventory(Builder $query): void
    {
        // whereNull() menambahkan syarat bahwa kolom archived_at masih kosong.
        // Scope ini digunakan sebelum get(), count() atau paginate() agar
        // katalog dan dasbor menghitung hanya unit yang belum diarsipkan.
        $query->whereNull('archived_at');
    }

    #[Scope]
    protected function readyForRental(Builder $query): void
    {
        // Operator chaining whereNull()->where() menyaring hanya kendaraan aktif
        // (archived_at bernilai null) yang berstatus available untuk kesiapan dasar.
        $query->whereNull('archived_at')->where('status', 'available');
    }

    #[Scope]
    protected function readyForHandover(Builder $query): void
    {
        // Kesiapan serah-terima aktual mewajibkan unit tidak diarsipkan, berstatus available,
        // dan sudah melewati pemeriksaan persiapan oleh staf/admin (readiness_status = ready).
        $query->whereNull('archived_at')
            ->where('status', 'available')
            ->where('readiness_status', self::READINESS_READY);
    }

    #[Scope]
    protected function bookableForSchedule(Builder $query): void
    {
        // Kendaraan dapat menerima pemesanan periode mendatang selama belum diarsipkan
        // dan tidak sedang berstatus maintenance. Unit yang sedang berstatus 'rented'
        // tetap boleh dipesan untuk jadwal mendatang yang tidak bentrok.
        $query->whereNull('archived_at')->where('status', '!=', 'maintenance');
    }

    /**
     * Determine whether this vehicle has been archived to preserve rental history.
     */
    public function isArchived(): bool
    {
        // Operator !== mengecek secara ketat apakah timestamp archived_at sudah terisi.
        return $this->archived_at !== null;
    }

    /**
     * Determine whether this vehicle is currently under maintenance.
     */
    public function isInMaintenance(): bool
    {
        return $this->status === 'maintenance';
    }

    /**
     * Determine whether this vehicle can accept future schedule bookings.
     */
    public function isBookableForSchedule(): bool
    {
        // Unit yang tidak diarsipkan dan tidak dalam perawatan boleh dipesan untuk
        // periode mendatang selama jadwalnya tidak bentrok (termasuk jeda 1 jam).
        return ! $this->isArchived() && ! $this->isInMaintenance();
    }

    /**
     * Periksa apakah kendaraan benar-benar layak dan siap diserahterimakan saat ini (SYS-08, S-05).
     */
    public function isReadyForHandover(): bool
    {
        return ! $this->isArchived()
            && $this->status === 'available'
            && ($this->readiness_status ?? self::READINESS_READY) === self::READINESS_READY;
    }

    /**
     * Determine whether this vehicle is active and ready to accept rentals.
     */
    public function isReadyForRental(): bool
    {
        // Operator && mewajibkan kedua kondisi bernilai true: tidak diarsipkan
        // dan status operasional unit berada pada kondisi available.
        return ! $this->isArchived() && $this->status === 'available';
    }

    /**
     * Kembalikan label status kesiapan operasional unit berbahasa Indonesia.
     */
    public function readinessLabel(): string
    {
        return match ($this->readiness_status) {
            self::READINESS_READY => 'Layak & Siap Serah-Terima',
            self::READINESS_IN_USE => 'Sedang Digunakan Penyewa',
            self::READINESS_NEEDS_PREPARATION => 'Perlu Pemeriksaan & Persiapan',
            self::READINESS_NEEDS_MAINTENANCE => 'Bermasalah / Perlu Perawatan',
            default => ucfirst((string) $this->readiness_status),
        };
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
