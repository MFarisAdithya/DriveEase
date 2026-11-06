# DriveEase — Sistem Informasi Manajemen Rental Kendaraan

DriveEase adalah aplikasi berbasis web yang dirancang untuk mengelola proses operasional persewaan kendaraan secara terpadu, mulai dari manajemen armada mobil, kategori kendaraan, autentikasi multi-peran, pendaftaran pelanggan mandiri, katalog publik dinamis, verifikasi identitas resmi pelanggan (KTP & SIM), hingga pengawasan ketersediaan unit. Proyek ini dikembangkan untuk memenuhi tugas mata kuliah Pemrograman Web Lanjut (PWL).

---

## 1. Deskripsi Proyek

Aplikasi **DriveEase** mengimplementasikan modul inti mencakup:
- Arsitektur berbasis **Laravel MVC** terintegrasi dengan basis data **PostgreSQL**.
- Autentikasi sesi mandiri (login, logout, register pelanggan baru) yang aman.
- Sistem otorisasi 3 peran (*Admin*, *Staff*, *Customer*) dengan pembatasan akses di tingkat middleware backend.
- Modul CRUD (Create, Read, Update, Delete) armada kendaraan lengkap dengan validasi form dan pesan notifikasi (flash message).
- Modul CRUD kategori kendaraan (*Car Types*) untuk Administrator dengan proteksi integritas relasi armada.
- Katalog kendaraan publik dinamis berbasis PostgreSQL dengan filter kategori, transmisi, pencarian instan, dan halaman rincian spesifikasi.
- **Modul Verifikasi Identitas Customer**:
  - Pengunggahan dokumen KTP (validasi tepat 16 digit angka) dan SIM A/Umum dengan pembatasan ukuran berkas maksimal 2 MB (format JPG, JPEG, PNG, WEBP).
  - Penyimpanan berkas privat terenkripsi pada direktori aman (`storage/app/private/identities`) yang tidak terekspos ke direktori publik web.
  - Streaming dokumen terlindungi otorisasi (`GET /documents/identity/{user}/{type}`), hanya dapat diakses oleh Admin atau pemilik dokumen itu sendiri dengan header `Cache-Control: private, no-store`.
  - Panel peninjauan verifikasi Admin (`/admin/verifications`) dengan filter status (*all*, *pending*, *verified*, *rejected*).
  - Mekanisme persetujuan (*Approve*) dan penolakan (*Reject*) dengan kewajiban mengisi alasan penolakan (*rejection reason*).
  - Mekanisme pengiriman ulang (*resubmission*) oleh pelanggan yang ditolak hingga status kembali menjadi *pending*.
  - Helper eligibilitas pemesanan `canMakeReservation()` pada Model `User` sesuai aturan bisnis BR-08.
- Dashboard informatif yang disesuaikan untuk masing-masing peran pengguna dengan statistik riil dari basis data (termasuk metrik verifikasi tertunda).

---

## 2. Teknologi yang Digunakan (Technology Stack)

- **Backend Framework**: PHP 8.3 / Laravel 13
- **Database Engine**: PostgreSQL 14 (Database: `driveease_db`)
- **Template Engine**: Laravel Blade
- **Styling**: Tailwind CSS & Vite
- **ORM**: Eloquent ORM
- **Testing**: PHPUnit / Laravel Feature Testing Suite (48 Automated Tests Passed, 178 Assertions)
- **Version Control**: Git

---

## 3. Konfigurasi Database PostgreSQL

Pastikan service PostgreSQL telah berjalan di komputer lokal (default port `5432`).

Buat database baru untuk aplikasi:
```sql
CREATE DATABASE driveease_db;
```

---

## 4. Konfigurasi Environment (`.env`)

Pastikan berkas `.env` memiliki konfigurasi koneksi basis data PostgreSQL sebagai berikut:

```env
APP_NAME=DriveEase
APP_ENV=local
APP_KEY=base64:...
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=driveease_db
DB_USERNAME=postgres
DB_PASSWORD=

# Kata sandi default untuk akun demo hasil seeder
DEMO_USER_PASSWORD=password123
```

---

## 5. Panduan Instalasi & Menjalankan Proyek

### A. Clone / Unduh Repositori
```bash
git clone <url-repo-anda>
cd "PWL KEL 3"
```

### B. Pasang Dependensi PHP & Frontend
```bash
composer install
npm install
npm run build
```

### C. Konfigurasi Environment & Key
```bash
cp .env.example .env
php artisan key:generate
```

### D. Jalankan Migrasi & Seeder Data Awal
```bash
php artisan migrate
php artisan db:seed
```

### E. Jalankan Server Aplikasi
```bash
php artisan serve
```
Akses aplikasi melalui peramban web di: **`http://localhost:8000`**

---

## 6. Penjelasan Akun Demo & Tiga Peran Pengguna

Password untuk semua akun demo adalah: **`password123`**

| Peran (Role) | Email Login | Wewenang & Batasan Akses |
|:---|:---|:---|
| **Admin** | `admin@driveease.test` | Akses penuh ke Admin Console (`/admin/dashboard`), manajemen CRUD armada mobil (`/admin/cars`), manajemen CRUD kategori kendaraan (`/admin/car-types`), peninjauan dan persetujuan/penolakan verifikasi identitas customer (`/admin/verifications`), serta melihat ringkasan statistik operasional dari basis data. |
| **Staff (Staf Lapangan)** | `staff@driveease.test` | Mengakses Staff Portal (`/staff/dashboard`), melihat statistik ringkas dan daftar seluruh armada dalam mode *read-only*. Dilarang keras mengakses fitur tambah/edit/hapus kendaraan, kategori, atau menyetujui verifikasi dokumen (otomatis diblokir 403 Forbidden). |
| **Customer (Pelanggan)** | `customer@driveease.test` | Mengakses Customer Portal (`/customer/dashboard`), formulir verifikasi identitas (`/customer/verification`), menjelajahi katalog unit kendaraan yang berstatus *Tersedia* lengkap dengan rincian spesifikasi teknis dan tarif harian. Dilarang mengakses rute manajemen admin. |

---

## 7. Rute & Modul yang Tersedia

### Modul Publik & Autentikasi
- `GET /` — Beranda (redirect cerdas ke dashboard bila login, atau ke katalog `/cars` bagi guest)
- `GET /cars` — Katalog armada dinamis publik dengan fitur pencarian teks, filter kategori, dan pagination
- `GET /cars/{car}` — Detail spesifikasi lengkap armada kendaraan
- `GET /login` & `POST /login` — Masuk ke akun sistem
- `GET /register` & `POST /register` — Pendaftaran akun pelanggan mandiri (auto-assigned role `customer`)
- `POST /logout` — Keluar dari sistem

### Modul Akses Dokumen Privat (`auth`)
- `GET /documents/identity/{user}/{type}` — Streaming foto KTP / SIM dari storage privat (diotorisasi khusus Admin & pemilik dokumen, Staff diblokir 403)

### Modul Khusus Administrator (`auth, role:admin`)
- `GET /admin/dashboard` — Dashboard ringkasan metrik basis data & notifikasi verifikasi tertunda
- Resource `/admin/cars` — CRUD armada mobil (Index, Create, Store, Show, Edit, Update, Destroy)
- Resource `/admin/car-types` — CRUD kategori kendaraan (Index, Create, Store, Edit, Update, Destroy) dengan proteksi relasi armada
- `GET /admin/verifications` — Daftar seluruh verifikasi identitas customer dengan tab filter status (*all*, *pending*, *verified*, *rejected*)
- `GET /admin/verifications/{user}` — Pratinjau detail dokumen KTP & SIM customer
- `PATCH /admin/verifications/{user}/approve` — Menyetujui verifikasi dokumen customer
- `PATCH /admin/verifications/{user}/reject` — Menolak verifikasi customer disertai alasan wajib

### Modul Khusus Staf (`auth, role:staff`)
- `GET /staff/dashboard` — Dashboard operasional lapangan (*read-only* list)

### Modul Khusus Pelanggan (`auth, role:customer`)
- `GET /customer/dashboard` — Dashboard pelanggan dengan kartu status verifikasi (*pending*, *verified*, *rejected*) dan armada siap sewa
- `GET /customer/verification` — Formulir pengunggahan & peninjauan identitas KTP & SIM
- `POST /customer/verification` — Proses validasi dan penyimpanan berkas identitas ke storage privat

---

## 8. Pengujian Otomatis (Automated Feature Testing)

Proyek ini dilengkapi dengan 48 skenario pengujian fitur komprehensif (`tests/Feature`) yang dijalankan langsung terhadap PostgreSQL:
```bash
php artisan test
```
Hasil: **48 Passed, 178 Assertions, 0 Failed**.

---

## 9. Rencana Pengembangan Selanjutnya (Progress 2)

Fitur yang dijadwalkan untuk tahap berikutnya:
- Modul pemesanan/reservasi sewa kendaraan (*Rentals*) dengan pemilihan tanggal sewa.
- Algoritma pencegahan bentrok jadwal (*double-booking prevention*).
- Validasi bukti pembayaran sewa oleh Admin.
- Formulir inspeksi fisik kendaraan keluar dan masuk (*Check-in / Check-out checklist*) oleh Staf Garasi.
- Kalkulasi denda otomatis bila terjadi keterlambatan atau kerusakan unit.
- Rekapitulasi laporan pendapatan keuangan dan riwayat transaksi sewa.
