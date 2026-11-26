<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Models\CarType;
use App\Models\RentalSetting;
use App\Services\RentalScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\View\View;
use Throwable;

class PublicCarController extends Controller
{
    /**
     * Display the public landing page showcase.
     */
    public function landing(): View
    {
        // activeInventory() mengecualikan armada berarsip, sedangkan orderByRaw()
        // memprioritaskan unit berstatus available sebelum mengambil 3 model unik.
        $featuredCars = Car::activeInventory()->with('carType')
            ->orderByRaw("CASE WHEN status = 'available' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->get()
            ->unique(fn (Car $car): string => strtolower(trim($car->name)))
            ->take(3)
            ->values();

        // RentalSetting::publicInfo() hanya memilih kolom informasi usaha publik
        // (tanpa bank_name, account_number, account_holder) serta memberi nilai bawaan
        // ketika baris pengaturan belum disimpan admin.
        $rentalInfo = RentalSetting::publicInfo();

        return view('landing', compact('featuredCars', 'rentalInfo'));
    }

    /**
     * Display a listing of the vehicles for public / customers, including optional period filter (G-02, C-04).
     */
    public function index(Request $request, RentalScheduleService $scheduleService): View
    {
        $periodSummary = null;
        $periodErrors = new MessageBag;
        $hasPeriodInput = $request->filled('pickup_at') || $request->filled('total_days');

        if ($hasPeriodInput) {
            [$pickupAt, $totalDays, $periodErrors] = $this->validatePeriodInput($request, $scheduleService);

            if ($periodErrors->isEmpty() && $pickupAt !== null && $totalDays !== null) {
                $scheduleService->releaseExpiredHolds();
                $returnAt = $scheduleService->calculateReturnAt($pickupAt, $totalDays);

                // Jika periode sewa diisi dan valid, gunakan availableCarsQuery() yang sama
                // dengan endpoint API agar daftar armada tersedia konsisten di semua kanal.
                $cars = $scheduleService->availableCarsQuery(
                    $pickupAt,
                    $returnAt,
                    $request->only(['search', 'car_type_id', 'transmission'])
                )->latest()->paginate(9)->withQueryString();

                $periodSummary = [
                    'pickup_at' => $pickupAt,
                    'return_at' => $returnAt,
                    'total_days' => $totalDays,
                ];
            } else {
                // Jika input periode tidak valid, kosongkan daftar agar pengunjung tidak keliru
                // melihat unit yang tidak diverifikasi jadwalnya sebelum memperbaiki tanggal sewa.
                $cars = Car::query()->whereRaw('1 = 0')->paginate(9)->withQueryString();
            }
        } else {
            $query = Car::activeInventory()->with('carType');

            // Search by name or brand
            if ($request->filled('search')) {
                $search = trim((string) $request->search);
                $query->where(function ($q) use ($search): void {
                    $q->where('name', 'ilike', "%{$search}%")
                        ->orWhere('brand', 'ilike', "%{$search}%");
                });
            }

            // Filter by category
            if ($request->filled('car_type_id') && is_numeric($request->car_type_id)) {
                $query->where('car_type_id', (int) $request->car_type_id);
            }

            // Filter by transmission
            if ($request->filled('transmission') && in_array($request->transmission, ['manual', 'automatic'], true)) {
                $query->where('transmission', $request->transmission);
            }

            // Order by availability first, then newest
            $cars = $query->orderByRaw("CASE WHEN status = 'available' THEN 1 ELSE 2 END")
                ->latest()
                ->paginate(9)
                ->withQueryString();
        }

        $carTypes = CarType::orderBy('name')->get();

        return view('cars.index', compact('cars', 'carTypes', 'periodSummary', 'periodErrors'));
    }

    /**
     * Display the specified vehicle details and optional period availability check (G-03, C-04).
     */
    public function show(Request $request, Car $car, RentalScheduleService $scheduleService): View
    {
        abort_if($car->archived_at !== null, 404);
        $car->load('carType');

        // Other recommendations in same category
        $relatedCars = Car::activeInventory()->with('carType')
            ->where('car_type_id', $car->car_type_id)
            ->where('id', '!=', $car->id)
            ->take(3)
            ->get();

        // Pertahankan seluruh filter pencarian katalog (termasuk periode sewa jika dikirim)
        // agar tautan kembali ke katalog memulihkan filter terakhir pengguna (G-02).
        $catalogFilters = collect($request->only(['search', 'car_type_id', 'transmission', 'pickup_at', 'total_days', 'page']))
            ->filter(fn ($value): bool => is_string($value) && $value !== '' && strlen($value) <= 255)
            ->all();

        $periodCheck = null;
        $periodErrors = new MessageBag;

        if ($request->filled('pickup_at') || $request->filled('total_days')) {
            [$pickupAt, $totalDays, $periodErrors] = $this->validatePeriodInput($request, $scheduleService);

            if ($periodErrors->isEmpty() && $pickupAt !== null && $totalDays !== null) {
                $scheduleService->releaseExpiredHolds($car->id);
                $pricing = $scheduleService->calculatePricing($car, $pickupAt, $totalDays);
                $isAvailable = $scheduleService->isCarAvailableForPeriod($car, $pricing['pickup_at'], $pricing['return_at']);

                $periodCheck = [
                    'available' => $isAvailable,
                    'pricing' => $isAvailable ? $pricing : null,
                ];
            }
        }

        return view('cars.show', compact('car', 'relatedCars', 'catalogFilters', 'periodCheck', 'periodErrors'));
    }

    /**
     * Validate optional period parameters for public catalog and detail pages.
     *
     * @return array{0: ?CarbonImmutable, 1: ?int, 2: MessageBag}
     */
    private function validatePeriodInput(Request $request, RentalScheduleService $scheduleService): array
    {
        $validator = Validator::make($request->only(['pickup_at', 'total_days']), [
            'pickup_at' => ['required', 'date'],
            'total_days' => ['required', 'integer', 'min:1', 'max:'.RentalScheduleService::MAX_DAYS],
        ]);

        $pickupAt = null;
        $validator->after(function ($validator) use ($request, $scheduleService, &$pickupAt): void {
            if ($validator->errors()->has('pickup_at')) {
                return;
            }

            try {
                $parsed = $scheduleService->parsePickupAt((string) $request->input('pickup_at'));
                if ($parsed->lessThanOrEqualTo(CarbonImmutable::now(RentalScheduleService::TIMEZONE))) {
                    $validator->errors()->add('pickup_at', 'Waktu pengambilan kendaraan harus berada di masa mendatang (WIB).');

                    return;
                }

                $pickupAt = $parsed;
            } catch (Throwable) {
                $validator->errors()->add('pickup_at', 'Format waktu pengambilan tidak valid.');
            }
        });

        if ($validator->fails()) {
            return [null, null, $validator->errors()];
        }

        return [$pickupAt, (int) $request->input('total_days'), new MessageBag];
    }
}
