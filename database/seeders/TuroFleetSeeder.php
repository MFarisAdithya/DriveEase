<?php

namespace Database\Seeders;

use App\Models\Car;
use App\Models\CarType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TuroFleetSeeder extends Seeder
{
    /**
     * Replace the existing fleet with Turo reference vehicles while preserving
     * customer accounts, identity documents, and transaction-linked cars.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Seeder armada Turo hanya boleh dijalankan pada lingkungan lokal atau pengujian.');
        }

        $canonicalTypes = [
            'MPV' => 'Multi Purpose Vehicle dan minivan lapang yang nyaman untuk keluarga maupun perjalanan rombongan.',
            'SUV' => 'Sport Utility Vehicle tangguh dengan kabin lega dan ground clearance tinggi.',
            'Sedan' => 'Sedan modern dengan kenyamanan kabin senyap dan pengendalian presisi.',
            'City Car' => 'Mobil kompak lincah, mudah diparkir, dan hemat bahan bakar untuk aktivitas perkotaan.',
            'Electric' => 'Kendaraan listrik modern bebas emisi dengan akselerasi instan dan kabin senyap.',
            'Convertible' => 'Mobil sport atap terbuka berkarakter mewah untuk pengalaman berkendara istimewa.',
        ];

        $turoFleet = [
            [
                'category' => 'Sedan',
                'name' => 'Toyota Corolla LE',
                'brand' => 'Toyota',
                'model_year' => 2023,
                'transmission' => 'automatic',
                'capacity' => 5,
                'price_per_day' => 550000,
                'plate_number' => 'B 1421 TUA',
                'status' => 'available',
                'description' => 'Referensi Turo 2023 Toyota Corolla LE. Sedan irit dan stabil dengan transmisi otomatis CVT serta kabin nyaman untuk 5 penumpang.',
            ],
            [
                'category' => 'Sedan',
                'name' => 'Subaru WRX Base',
                'brand' => 'Subaru',
                'model_year' => 2023,
                'transmission' => 'manual',
                'capacity' => 5,
                'price_per_day' => 950000,
                'plate_number' => 'B 2532 TUB',
                'status' => 'available',
                'description' => 'Referensi Turo 2023 Subaru WRX Base. Sedan sport bertenaga turbo dengan transmisi manual 6-percepatan dan penggerak semua roda.',
            ],
            [
                'category' => 'SUV',
                'name' => 'Volkswagen Tiguan SE',
                'brand' => 'Volkswagen',
                'model_year' => 2021,
                'transmission' => 'automatic',
                'capacity' => 7,
                'price_per_day' => 750000,
                'plate_number' => 'B 3643 TUC',
                'status' => 'available',
                'description' => 'Referensi Turo 2021 Volkswagen Tiguan SE. SUV Eropa serbaguna berkapasitas 7 penumpang dengan suspensi mantap dan interior lega.',
            ],
            [
                'category' => 'SUV',
                'name' => 'Kia Telluride EX X-Line',
                'brand' => 'Kia',
                'model_year' => 2023,
                'transmission' => 'automatic',
                'capacity' => 7,
                'price_per_day' => 1100000,
                'plate_number' => 'B 4754 TUD',
                'status' => 'available',
                'description' => 'Referensi Turo 2023 Kia Telluride EX X-Line. SUV premium tiga baris berkapasitas 7 penumpang dengan fitur keselamatan lengkap.',
            ],
            [
                'category' => 'MPV',
                'name' => 'Chrysler Pacifica Touring L',
                'brand' => 'Chrysler',
                'model_year' => 2024,
                'transmission' => 'automatic',
                'capacity' => 7,
                'price_per_day' => 900000,
                'plate_number' => 'B 5865 TUE',
                'status' => 'available',
                'description' => 'Referensi Turo 2024 Chrysler Pacifica Touring L. Minivan keluarga mewah berkapasitas 7 penumpang dengan pintu geser elektrik.',
            ],
            [
                'category' => 'MPV',
                'name' => 'Honda Odyssey EX-L',
                'brand' => 'Honda',
                'model_year' => 2018,
                'transmission' => 'automatic',
                'capacity' => 8,
                'price_per_day' => 700000,
                'plate_number' => 'B 6976 TUF',
                'status' => 'available',
                'description' => 'Referensi Turo 2018 Honda Odyssey EX-L. MPV berkapasitas hingga 8 penumpang dengan jok kulit nyaman dan bagasi luas.',
            ],
            [
                'category' => 'City Car',
                'name' => 'Kia Soul LX',
                'brand' => 'Kia',
                'model_year' => 2025,
                'transmission' => 'automatic',
                'capacity' => 5,
                'price_per_day' => 475000,
                'plate_number' => 'B 7187 TUG',
                'status' => 'available',
                'description' => 'Referensi Turo 2025 Kia Soul LX. Hatchback kompak bergaya unik dengan kabin tinggi yang lega dan mudah bermanuver di kota.',
            ],
            [
                'category' => 'Electric',
                'name' => 'Polestar 4 Base',
                'brand' => 'Polestar',
                'model_year' => 2026,
                'transmission' => 'automatic',
                'capacity' => 5,
                'price_per_day' => 1350000,
                'plate_number' => 'B 8298 TUH',
                'status' => 'available',
                'description' => 'Referensi Turo 2026 Polestar 4 Base. SUV coupe listrik modern dengan desain minimalis Skandinavia dan jarak tempuh jauh.',
            ],
            [
                'category' => 'Electric',
                'name' => 'Tesla Model 3 Mid Range',
                'brand' => 'Tesla',
                'model_year' => 2018,
                'transmission' => 'automatic',
                'capacity' => 5,
                'price_per_day' => 1250000,
                'plate_number' => 'B 9319 TUI',
                'status' => 'rented',
                'description' => 'Referensi Turo 2018 Tesla Model 3 Mid Range. Sedan listrik futuristik dengan layar sentuh terpusat dan akselerasi responsif.',
            ],
            [
                'category' => 'Convertible',
                'name' => 'Porsche 911 Carrera',
                'brand' => 'Porsche',
                'model_year' => 2026,
                'transmission' => 'automatic',
                'capacity' => 4,
                'price_per_day' => 2800000,
                'plate_number' => 'B 9911 TUJ',
                'status' => 'available',
                'description' => 'Referensi Turo 2026 Porsche 911 Carrera. Mobil sport ikonik atap terbuka dengan performa tinggi dan handling presisi.',
            ],
        ];

        DB::transaction(function () use ($canonicalTypes, $turoFleet): void {
            $typeMap = [];
            foreach ($canonicalTypes as $typeName => $typeDescription) {
                $type = CarType::updateOrCreate(
                    ['name' => $typeName],
                    ['description' => $typeDescription]
                );
                $typeMap[$typeName] = $type->id;
            }

            // Car adalah model Eloquent untuk tabel cars.
            // query() memulai builder baru, lockForUpdate() mengunci baris selama transaksi,
            // orderBy('id') mengurutkan dari ID terkecil, dan get() mengembalikan Collection.
            //
            // Baris ini membaca seluruh data mobil yang ada secara terkunci agar pemeriksaan
            // riwayat sewa dan pengarsipan berlangsung aman di dalam satu transaksi database.
            $existingCars = Car::query()->lockForUpdate()->orderBy('id')->get();
            $carIdsWithHistory = DB::table('rentals')
                ->whereIn('car_id', $existingCars->pluck('id'))
                ->distinct()
                ->pluck('car_id')
                ->all();

            [$carsWithHistory, $carsWithoutHistory] = $existingCars->partition(
                fn (Car $car): bool => in_array($car->id, $carIdsWithHistory, true)
            );

            // isNotEmpty() mengecek apakah Collection memiliki anggota sebelum menghapus.
            // Hanya unit tanpa baris pada tabel rentals yang boleh dihapus dari database.
            if ($carsWithoutHistory->isNotEmpty()) {
                Car::whereIn('id', $carsWithoutHistory->pluck('id'))->delete();
            }

            // Array asosiatif $turoKeys memetakan pasangan nama dan merek Turo yang dinormalisasi
            // memakai strtolower() dan trim() dengan operator penggabungan string (.).
            // Struktur ini menjadi tabel pencarian cepat agar loop berikutnya mengenali unit Turo.
            $turoKeys = [];
            foreach ($turoFleet as $vehicle) {
                $turoKeys[strtolower(trim($vehicle['name'])).'|'.strtolower(trim($vehicle['brand']))] = true;
            }

            $preservedTuroKeys = [];
            $usedPlates = [];

            // foreach menelusuri setiap objek Car yang pernah memiliki riwayat pada tabel rentals.
            // Setiap plat nomor lama dicatat ke $usedPlates dengan strtoupper() supaya pengecekan
            // bentrok plat bersifat case-insensitive dan atribut unit historis tidak pernah ditimpa.
            foreach ($carsWithHistory as $car) {
                $usedPlates[strtoupper($car->plate_number)] = true;
                $carKey = strtolower(trim($car->name)).'|'.strtolower(trim($car->brand));

                // Operator === membandingkan nilai sekaligus tipe data.
                // isset() memastikan kunci ada di array, sedangkan operator ! membalik nilai boolean.
                // Jika unit bersejarah ini masih aktif (archived_at bernilai null) dan sudah termasuk
                // daftar referensi Turo pertama yang ditemukan, biarkan barisnya tetap utuh.
                if ($car->archived_at === null && isset($turoKeys[$carKey]) && ! isset($preservedTuroKeys[$carKey])) {
                    $preservedTuroKeys[$carKey] = true;

                    continue;
                }

                // Properti archived_at sengaja tidak dimasukkan ke $fillable pada model Car,
                // sehingga nilainya ditetapkan langsung pada properti objek lalu disimpan dengan save().
                // Unit lama atau duplikat yang punya histori rental hanya diarsipkan tanpa mengubah
                // nama, merek, tarif, plat nomor, status maupun relasi pada histori transaksi.
                if ($car->archived_at === null) {
                    $car->archived_at = now();
                    $car->save();
                }
            }

            // Loop ini menambahkan armada referensi Turo yang belum terwakili oleh unit Turo aktif
            // hasil pelestarian di atas, sambil memilihkan plat nomor pengganti jika plat utama masih
            // dipakai oleh baris kendaraan bersejarah yang diarsipkan.
            foreach ($turoFleet as $vehicle) {
                $vehicleKey = strtolower(trim($vehicle['name'])).'|'.strtolower(trim($vehicle['brand']));

                if (isset($preservedTuroKeys[$vehicleKey])) {
                    continue;
                }

                $plateNumber = $this->resolveAvailablePlateNumber($vehicle['plate_number'], $usedPlates);
                $usedPlates[strtoupper($plateNumber)] = true;

                Car::create([
                    'car_type_id' => $typeMap[$vehicle['category']],
                    'name' => $vehicle['name'],
                    'brand' => $vehicle['brand'],
                    'model_year' => $vehicle['model_year'],
                    'transmission' => $vehicle['transmission'],
                    'capacity' => $vehicle['capacity'],
                    'price_per_day' => $vehicle['price_per_day'],
                    'plate_number' => $plateNumber,
                    'status' => $vehicle['status'],
                    'image' => null,
                    'description' => $vehicle['description'],
                ]);
            }

            CarType::whereNotIn('name', array_keys($canonicalTypes))
                ->doesntHave('cars')
                ->delete();
        });
    }

    /**
     * Resolve a unique vehicle plate number within the 15-character column limit.
     *
     * @param  array<string, bool>  $usedPlates
     */
    private function resolveAvailablePlateNumber(string $preferredPlate, array $usedPlates): string
    {
        // Jika plat nomor pilihan belum terdaftar di $usedPlates, kembalikan langsung apa adanya.
        if (! isset($usedPlates[strtoupper($preferredPlate)])) {
            return $preferredPlate;
        }

        // Perulangan while membentuk akhiran angka bertahap (-2, -3, dst.) dan memotong awalan
        // memakai substr() agar panjang total selalu <= 15 karakter sesuai kolom plate_number.
        $suffix = 2;
        while (true) {
            $suffixText = '-'.$suffix;
            $maxBaseLength = 15 - strlen($suffixText);
            $candidate = substr($preferredPlate, 0, $maxBaseLength).$suffixText;

            if (! isset($usedPlates[strtoupper($candidate)])) {
                return $candidate;
            }

            $suffix++;
        }
    }
}
