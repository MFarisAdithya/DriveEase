# DriveEase — Sistem Informasi Manajemen Rental Kendaraan

DriveEase adalah aplikasi berbasis web yang dirancang untuk mengelola proses operasional persewaan kendaraan secara terpadu, mulai dari manajemen armada mobil, autentikasi multi-peran, pencatatan transaksi, hingga pengawasan ketersediaan unit. Proyek ini dikembangkan untuk memenuhi tugas mata kuliah Pemrograman Web Lanjut (PWL).

---

## 1. Deskripsi Proyek

Aplikasi **DriveEase** Progress 1 mengimplementasikan pondasi utama sistem sewa mobil mencakup:
- Arsitektur berbasis **Laravel MVC** terintegrasi dengan basis data **PostgreSQL**.
- Autentikasi sesi mandiri (login & logout) yang aman.
- Sistem otorisasi 3 peran (*Admin*, *Staff*, *Customer*) dengan pembatasan akses di tingkat middleware backend.
- Modul CRUD (Create, Read, Update, Delete) armada kendaraan lengkap dengan validasi form dan pesan notifikasi (flash message).
- Dashboard informatif yang disesuaikan untuk masing-masing peran pengguna dengan statistik riil dari basis data.

---

## 2. Teknologi yang Digunakan (Technology Stack)

- **Backend Framework**: PHP 8.3 / Laravel 13
- **Database Engine**: PostgreSQL 14 (Database: `driveease_db`)
- **Template Engine**: Laravel Blade
- **Styling**: Tailwind CSS & Vite
- **ORM**: Eloquent ORM
- **Testing**: PHPUnit / Laravel Feature Testing Suite
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
php artisan migrate:fresh
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
| **Admin** | `admin@driveease.test` | Akses penuh ke Admin Console (`/admin/dashboard`), manajemen CRUD armada mobil (`/admin/cars`), melihat ringkasan statistik operasional dari basis data. |
| **Staff (Staf Lapangan)** | `staff@driveease.test` | Mengakses Staff Portal (`/staff/dashboard`), melihat statistik ringkas dan daftar seluruh armada dalam mode *read-only*. Dilarang keras mengakses fitur tambah/edit/hapus kendaraan (otomatis diblokir 403 Forbidden). |
| **Customer (Pelanggan)** | `customer@driveease.test` | Mengakses Customer Portal (`/customer/dashboard`), menjelajahi katalog unit kendaraan yang berstatus *Tersedia* lengkap dengan rincian spesifikasi teknis dan tarif harian. Dilarang mengakses rute manajemen admin. |

---

## 7. Fitur yang Telah Selesai (Progress 1)

1. **Setup & Integrasi PostgreSQL**: Basis data PostgreSQL `driveease_db` terhubung stabil via PDO pgsql.
2. **Database Migrations & Models**:
   - `roles`: Menyimpan peran (`admin`, `staff`, `customer`).
   - `users`: Ekstensi model user dengan `role_id`, `phone`, `address`.
   - `car_types`: Kategori armada (*SUV*, *MPV*, *Sedan*, *City Car*).
   - `cars`: Tabel armada dengan atribut lengkap (nama, nomor plat unik, merek, tahun, transmisi, kapasitas, tarif, status, deskripsi).
3. **Autentikasi Sesi & Proteksi Middleware**:
   - Login & Logout dengan validasi ketat dan regenerasi token sesi CSRF.
   - Redirect cerdas ke dashboard sesuai peran pengguna.
   - Middleware `role:...` di tingkat backend yang mengamankan rute terproteksi.
4. **CRUD Kendaraan untuk Admin**:
   - Daftar kendaraan dengan filter kategori, filter status, dan pencarian instan.
   - Tambah kendaraan baru dengan validasi data (plat nomor unik, harga sewa > 0, kapasitas > 0).
   - Detail spesifikasi kendaraan.
   - Ubah/edit data kendaraan.
   - Hapus kendaraan dengan konfirmasi keamanan.
   - Tampilan pesan sukses (flash alert) dan pesan error validasi per kolom.
5. **Role-Specific Dashboards**:
   - **Admin Dashboard**: Statistik riil dari basis data (Total Armada, Tersedia, Disewa, Masa Perawatan) serta 5 unit terbaru.
   - **Staff Dashboard**: Ringkasan armada dan tabel pantau status kendaraan *read-only*.
   - **Customer Dashboard**: Katalog kartu kendaraan yang berstatus siap sewa dengan rincian tarif per hari.
6. **Desain Antarmuka Modern & Responsif**:
   - Menggunakan palet warna *Navy Blue* (`#0b1d3a`) dan aksen *Muted Teal* (`#0d9488`).
   - Halaman error ramah pengguna (HTTP 403 & 404).
7. **Pengujian Otomatis (Automated Feature Testing)**:
   - 14 pengujian fitur (14 passed, 53 assertions) berjalan sukses di PHPUnit.

---

## 8. Rencana Pengembangan Selanjutnya (Progress 2)

Fitur yang dijadwalkan untuk tahap berikutnya:
- Registrasi publik untuk calon penyewa dengan unggah berkas KTP & SIM.
- Modul pemesanan/reservasi sewa kendaraan (*Rentals*) dengan pemilihan tanggal sewa.
- Algoritma pencegahan bentrok jadwal (*double-booking prevention*).
- Validasi bukti pembayaran sewa oleh Admin.
- Formulir inspeksi fisik kendaraan keluar dan masuk (*Check-in / Check-out checklist*) oleh Staf Garasi.
- Kalkulasi denda otomatis bila terjadi keterlambatan atau kerusakan unit.
- Rekapitulasi laporan pendapatan keuangan dan riwayat transaksi sewa.
