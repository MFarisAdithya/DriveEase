<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

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
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'verified_at' => 'datetime',
            'password' => 'hashed',
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
        return !empty($ktp) && !empty($this->sim_number) && !empty($this->ktp_image) && !empty($this->sim_image);
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
            get: fn ($value) => $value ?: $this->attributes['nik_ktp'] ?? null,
            set: function ($value) {
                $this->attributes['nik_ktp'] = $value;
                return $value;
            }
        );
    }
}
