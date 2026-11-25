<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RentalSetting extends Model
{
    /**
     * Tabel rental_settings menyimpan tepat satu baris konfigurasi aktif (id = 1)
     * tanpa kolom created_at/updated_at maupun auto-increment.
     */
    public $timestamps = false;

    public $incrementing = false;

    /**
     * Daftar kolom yang boleh diisi melalui mass assignment oleh admin.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'business_name',
        'services',
        'address',
        'contact',
        'operating_hours',
        'terms',
        'bank_name',
        'account_number',
        'account_holder',
    ];

    /**
     * Rincian rekening bank disembunyikan saat model diserialisasi ke array atau JSON
     * agar tidak bocor ke respons publik di luar alur admin dan pembayaran.
     *
     * @var list<string>
     */
    protected $hidden = [
        'bank_name',
        'account_number',
        'account_holder',
    ];

    /**
     * Retrieve the active rental settings record (ID 1), or null when not yet configured.
     */
    public static function current(): ?self
    {
        // static::query()->find(1) menjalankan SELECT berdasarkan primary key 1
        // pada tabel rental_settings dan mengembalikan null jika belum ada baris.
        return static::query()->find(1);
    }

    /**
     * Verify that the rental payment bank account has valid bank name, numeric account number, and holder.
     */
    public function hasValidPaymentAccount(): bool
    {
        // preg_match() memeriksa apakah nomor rekening hanya terdiri dari digit 0-9
        // setelah trim(), dan memastikan nama bank serta pemilik rekening tidak kosong.
        $bankName = trim((string) $this->bank_name);
        $accountNumber = trim((string) $this->account_number);
        $accountHolder = trim((string) $this->account_holder);

        return $bankName !== ''
            && preg_match('/^[0-9]+$/', $accountNumber) === 1
            && $accountHolder !== '';
    }

    /**
     * Provide fallback public business information when row ID 1 is not yet seeded.
     */
    public static function defaultPublicInfo(): object
    {
        // Operator (object) mengubah array asosiatif menjadi stdClass agar struktur
        // propertinya seragam dengan hasil query model di view beranda ($rentalInfo->...).
        // Objek fallback ini sengaja tidak memuat atribut rekening bank.
        return (object) [
            'business_name' => 'DriveEase Rental Mobil Lepas Kunci',
            'services' => 'Layanan sewa mobil harian lepas kunci dengan inspeksi kondisi kendaraan terstandar, pilihan armada terawat, dan verifikasi identitas pelanggan.',
            'address' => 'Jl. Prof. Dr. Sumantri Brojonegoro No. 1, Bandar Lampung',
            'contact' => '0812-3456-7890 · halo@driveease.test',
            'operating_hours' => '08.00–21.00 WIB',
            'terms' => "1. Pelanggan wajib terverifikasi KTP (16 digit) dan SIM (14 digit) sebelum memesan.\n2. Pembayaran dilakukan penuh melalui transfer bank dengan batas unggah bukti 2 jam.\n3. Durasi sewa dihitung 24 jam per hari (WIB) dengan jeda persiapan 1 jam antarpenyewaan.\n4. Pembatalan dan keterlambatan pengembalian mengikuti kebijakan resmi DriveEase.",
        ];
    }

    /**
     * Retrieve only public-safe rental information for the landing page.
     */
    public static function publicInfo(): object
    {
        // select() membatasi kolom yang diambil dari database hanya ke informasi publik
        // usaha, jam operasional, dan ketentuan rental tanpa kolom rekening bank.
        // Operator ?? mengembalikan nilai bawaan bila admin belum menyimpan pengaturan.
        return static::query()
            ->select('business_name', 'services', 'address', 'contact', 'operating_hours', 'terms')
            ->find(1) ?? static::defaultPublicInfo();
    }
}
