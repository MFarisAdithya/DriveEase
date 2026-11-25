<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\CarType;
use App\Services\RentalOperationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CarController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        // Car::with('carType') melakukan eager loading relasi kategori agar
        // daftar armada tidak memicu query N+1 ketika menampilkan nama kategori.
        $query = Car::with('carType');

        // Cabang if/elseif memisahkan filter unit arsip dari status unit aktif
        // sehingga unit yang sudah diarsipkan tidak ikut muncul pada filter aktif.
        if ($request->status === 'archived') {
            $query->whereNotNull('archived_at');
        } elseif (in_array($request->status, ['available', 'rented', 'maintenance'], true)) {
            $query->activeInventory()->where('status', $request->status);
        }

        // is_numeric() mencegah string acak pada query car_type_id dikirim ke kolom integer PostgreSQL.
        if ($request->filled('car_type_id') && is_numeric($request->car_type_id)) {
            $query->where('car_type_id', (int) $request->car_type_id);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            // Closure di dalam where() membungkus kondisi OR dengan tanda kurung SQL
            // agar filter pencarian tetap terikat bersama filter status dan kategori.
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('brand', 'ilike', "%{$search}%")
                    ->orWhere('plate_number', 'ilike', "%{$search}%");
            });
        }

        $cars = $query->latest()->paginate(10)->withQueryString();
        $carTypes = CarType::orderBy('name')->get();

        return view('admin.cars.index', compact('cars', 'carTypes'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $carTypes = CarType::orderBy('name')->get();

        return view('admin.cars.create', compact('carTypes'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->normalizePlateNumber($request);
        $validated = $this->validateVehicle($request);

        // Operator + pada array menggabungkan hasil validasi dengan nilai default null
        // untuk maintenance_reason saat status bukan 'maintenance' (aturan exclude_unless).
        Car::create($validated + [
            'maintenance_reason' => null,
            'readiness_status' => $this->resolveReadinessStatus($validated['status']),
            'readiness_checked_by' => $request->user()?->id,
            'readiness_checked_at' => now('Asia/Jakarta'),
        ]);

        return redirect()->route('admin.cars.index')
            ->with('success', 'Kendaraan "'.$validated['name'].'" berhasil ditambahkan.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Car $car): View
    {
        $car->load(['carType', 'readinessChecker']);

        return view('admin.cars.show', compact('car'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Car $car): View
    {
        $carTypes = CarType::orderBy('name')->get();

        return view('admin.cars.edit', compact('car', 'carTypes'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Car $car): RedirectResponse
    {
        $this->normalizePlateNumber($request);
        $validated = $this->validateVehicle($request, $car);

        // Ketika unit keluar dari status maintenance, exclude_unless menghapus
        // maintenance_reason dari $validated sehingga + ['maintenance_reason' => null]
        // otomatis membersihkan alasan perawatan lama di database.
        $car->update($validated + [
            'maintenance_reason' => null,
            'readiness_status' => $this->resolveReadinessStatus($validated['status']),
            'readiness_checked_by' => $request->user()?->id,
            'readiness_checked_at' => now('Asia/Jakarta'),
        ]);

        return redirect()->route('admin.cars.index')
            ->with('success', 'Data kendaraan "'.$car->name.'" berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Car $car): RedirectResponse
    {
        $name = $car->name;
        // DB::transaction() dan lockForUpdate() memastikan pemeriksaan riwayat rental
        // dan keputusan arsip/hapus berjalan atomik tanpa race condition.
        $archived = DB::transaction(function () use ($car): bool {
            $lockedCar = Car::query()->lockForUpdate()->findOrFail($car->id);
            if (DB::table('rentals')->where('car_id', $lockedCar->id)->exists()) {
                $lockedCar->archived_at ??= now();
                $lockedCar->save();

                return true;
            }

            $lockedCar->delete();

            return false;
        });

        return redirect()->route('admin.cars.index')
            ->with('success', 'Kendaraan "'.$name.'" berhasil '.($archived ? 'diarsipkan.' : 'dihapus dari sistem.'));
    }

    /**
     * Laporkan kerusakan unit oleh admin agar unit ditahan sampai pemeriksaan selesai (S-05, A4).
     */
    public function reportDamage(
        Request $request,
        Car $car,
        RentalOperationService $operationService
    ): RedirectResponse {
        $validated = $request->validate([
            'maintenance_reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $operationService->reportVehicleDamage($car, $request->user(), $validated['maintenance_reason']);

        return back()->with('success', 'Kerusakan unit berhasil dicatat. Kendaraan ditahan pada status perawatan.');
    }

    /**
     * Nyatakan kendaraan layak disewakan kembali oleh admin setelah pemeriksaan & persiapan (S-05, A4).
     */
    public function markReady(
        Request $request,
        Car $car,
        RentalOperationService $operationService
    ): RedirectResponse {
        $validated = $request->validate([
            'readiness_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $operationService->markVehicleReady($car, $request->user(), $validated['readiness_notes'] ?? null);

        return back()->with('success', 'Kendaraan dinyatakan layak dan siap untuk penyewaan berikutnya.');
    }

    /**
     * Petakan status operasional armada menjadi readiness_status yang konsisten saat create/update admin.
     */
    private function resolveReadinessStatus(string $carStatus): string
    {
        return match ($carStatus) {
            'maintenance' => Car::READINESS_NEEDS_MAINTENANCE,
            'rented' => Car::READINESS_IN_USE,
            default => Car::READINESS_READY,
        };
    }

    /**
     * Normalize plate_number to uppercase trimmed format before validation.
     */
    private function normalizePlateNumber(Request $request): void
    {
        // Str::upper(trim(...)) menyeragamkan huruf besar dan menghapus spasi berlebih
        // agar constraint unik PostgreSQL menolak duplikasi plat beda kapitalisasi.
        if (is_string($request->input('plate_number'))) {
            $request->merge([
                'plate_number' => Str::upper(trim($request->input('plate_number'))),
            ]);
        }
    }

    /**
     * Validate vehicle attributes consistently across create and update operations.
     *
     * @return array<string, mixed>
     */
    private function validateVehicle(Request $request, ?Car $car = null): array
    {
        $currentYear = (int) date('Y');

        // Rule::unique('cars', 'plate_number')->ignore($car?->id) memeriksa keunikan
        // plat nomor dengan batas panjang 15 karakter sesuai kolom database.
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'car_type_id' => ['required', 'exists:car_types,id'],
            'brand' => ['required', 'string', 'max:50'],
            'plate_number' => [
                'required',
                'string',
                'max:15',
                Rule::unique('cars', 'plate_number')->ignore($car?->id),
            ],
            'model_year' => ['required', 'integer', 'min:1990', 'max:'.($currentYear + 1)],
            'transmission' => ['required', 'in:manual,automatic'],
            'capacity' => ['required', 'integer', 'min:1', 'max:50'],
            'price_per_day' => ['required', 'numeric', 'min:1'],
            'status' => ['required', 'in:available,rented,maintenance'],
            'maintenance_reason' => ['exclude_unless:status,maintenance', 'required', 'string', 'max:2000'],
            'description' => ['nullable', 'string'],
        ], [
            'name.required' => 'Nama kendaraan wajib diisi.',
            'car_type_id.required' => 'Kategori kendaraan wajib dipilih.',
            'car_type_id.exists' => 'Kategori kendaraan tidak valid.',
            'brand.required' => 'Merek kendaraan wajib diisi.',
            'plate_number.required' => 'Nomor plat polisi wajib diisi.',
            'plate_number.max' => 'Nomor plat polisi maksimal 15 karakter.',
            'plate_number.unique' => $car === null
                ? 'Nomor plat polisi sudah terdaftar.'
                : 'Nomor plat polisi sudah terdaftar pada armada lain.',
            'model_year.required' => 'Tahun pembuatan wajib diisi.',
            'model_year.min' => 'Tahun pembuatan minimal tahun 1990.',
            'model_year.max' => 'Tahun pembuatan tidak valid.',
            'transmission.required' => 'Transmisi wajib dipilih.',
            'transmission.in' => 'Pilihan transmisi tidak valid.',
            'capacity.required' => 'Kapasitas penumpang wajib diisi.',
            'capacity.min' => 'Kapasitas minimal 1 penumpang.',
            'price_per_day.required' => 'Tarif sewa per hari wajib diisi.',
            'price_per_day.numeric' => 'Tarif sewa per hari harus berupa angka.',
            'price_per_day.min' => 'Tarif sewa harus lebih dari 0.',
            'status.required' => 'Status kendaraan wajib dipilih.',
            'status.in' => 'Pilih status tersedia, sedang disewa, atau dalam perawatan.',
            'maintenance_reason.required' => 'Alasan perawatan wajib diisi saat status kendaraan dalam perawatan.',
            'maintenance_reason.max' => 'Alasan perawatan maksimal 2000 karakter.',
        ]);
    }
}
