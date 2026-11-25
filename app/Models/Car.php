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
        ];
    }

    public function carType(): BelongsTo
    {
        return $this->belongsTo(CarType::class);
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
        // (archived_at bernilai null) yang berstatus available untuk kesiapan fisik.
        $query->whereNull('archived_at')->where('status', 'available');
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
     * Determine whether this vehicle is physically ready for handover right now.
     */
    public function isReadyForHandover(): bool
    {
        return $this->isReadyForRental();
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
