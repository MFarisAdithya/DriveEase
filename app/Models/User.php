<?php

namespace App\Models;

use App\Services\IdentityDocuments;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Crypt;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'role_id',
        'name',
        'email',
        'password',
        'phone',
        'address',
        'ktp_number',
        'nik_ktp',
        'sim_number',
        'ktp_image',
        'sim_image',
        'verification_status',
        'rejection_reason',
        'verified_at',
        'verified_by',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'ktp_number',
        'nik_ktp',
        'sim_number',
        'ktp_image',
        'sim_image',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        // Method casts() memberitahu Eloquent cara mengonversi tipe kolom saat dibaca
        // atau disimpan ke database. 'datetime' mengubah timestamp menjadi objek Carbon,
        // 'hashed' otomatis meng-hash kata sandi saat diisi, dan 'encrypted' mengenkripsi
        // nomor identitas sebelum masuk ke database serta mendekripsinya saat dibaca model.
        return [
            'email_verified_at' => 'datetime',
            'verified_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'password' => 'hashed',
            'nik_ktp' => 'encrypted',
            'sim_number' => 'encrypted',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Define the one-to-many relationship to rental transactions owned by this user.
     */
    public function rentals(): HasMany
    {
        // hasMany() memungkinkan pengambilan seluruh transaksi milik satu pelanggan
        // melalui relasi Eloquent $user->rentals() untuk riwayat dan otorisasi.
        return $this->hasMany(Rental::class);
    }

    public function isAdmin(): bool
    {
        return $this->role?->name === 'admin';
    }

    public function isStaff(): bool
    {
        return $this->role?->name === 'staff';
    }

    public function isCustomer(): bool
    {
        return $this->role?->name === 'customer';
    }

    /**
     * Check whether this account has been deactivated by an administrator.
     */
    public function isDeactivated(): bool
    {
        // Operator !== membandingkan nilai dan tipe secara ketat dengan null.
        // Jika kolom deactivated_at sudah terisi waktu penonaktifan, method ini
        // mengembalikan true agar login, sesi lama, dan reset password ditolak.
        return $this->deactivated_at !== null;
    }

    public function hasRole(string|array $roles): bool
    {
        if (is_array($roles)) {
            return in_array($this->role?->name, $roles, true);
        }

        return $this->role?->name === $roles;
    }

    /**
     * Verification state helpers
     */
    public function isVerified(): bool
    {
        return $this->verification_status === 'verified';
    }

    public function isPendingVerification(): bool
    {
        return $this->verification_status === 'pending';
    }

    public function isRejectedVerification(): bool
    {
        return $this->verification_status === 'rejected';
    }

    public function hasSubmittedDocuments(): bool
    {
        $ktp = $this->ktp_number ?: $this->nik_ktp;

        return ! empty($ktp) && ! empty($this->sim_number) && app(IdentityDocuments::class)->exists($this->ktp_image) && app(IdentityDocuments::class)->exists($this->sim_image);
    }

    /**
     * BR-08: Reusable reservation eligibility check
     */
    public function canMakeReservation(): bool
    {
        return $this->isVerified();
    }

    /**
     * Keep ktp_number and nik_ktp synchronized
     */
    protected function ktpNumber(): Attribute
    {
        return Attribute::make(
            // Accessor menerima ciphertext dari kolom utama. Jika kosong,
            // accessor membaca nomor melalui cast encrypted pada kolom lama.
            get: fn (?string $value): ?string => $value === null ? $this->nik_ktp : Crypt::decryptString($value),
            set: function (?string $value): array {
                $encryptedNumber = $value === null ? null : Crypt::encryptString($value);

                return ['ktp_number' => $encryptedNumber, 'nik_ktp' => $encryptedNumber];
            },
        );
    }
}
