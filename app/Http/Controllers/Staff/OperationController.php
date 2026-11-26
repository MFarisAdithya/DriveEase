<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\Inspection;
use App\Models\Rental;
use App\Services\RentalOperationService;
use App\Services\RentalScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OperationController extends Controller
{
    public function __construct(
        private readonly RentalOperationService $operationService,
        private readonly RentalScheduleService $scheduleService,
    ) {}

    /**
     * Tampilkan jadwal operasional pengambilan dan pengembalian kendaraan berdasarkan periode untuk staf (S-02).
     */
    public function index(Request $request): View
    {
        $this->scheduleService->releaseExpiredHolds();

        $fromDate = trim((string) $request->query('from', ''));
        $toDate = trim((string) $request->query('to', ''));
        $statusFilter = trim((string) $request->query('status', ''));

        $query = Rental::query()
            ->with([
                'user:id,name,phone,verification_status,verified_at',
                'car.carType',
                'pickupInspection:id,rental_id,inspection_type,inspected_at',
                'returnInspection:id,rental_id,inspection_type,inspected_at',
            ])
            ->whereIn('rental_status', [
                Rental::STATUS_CONFIRMED,
                Rental::STATUS_PICKED_UP,
                Rental::STATUS_RETURNED,
            ]);

        if (in_array($statusFilter, [Rental::STATUS_CONFIRMED, Rental::STATUS_PICKED_UP, Rental::STATUS_RETURNED], true)) {
            $query->where('rental_status', $statusFilter);
        }

        if ($fromDate !== '' && strtotime($fromDate) !== false) {
            $fromWib = CarbonImmutable::parse($fromDate, RentalScheduleService::TIMEZONE)->startOfDay()->format('Y-m-d H:i:s');
            $query->where('return_at', '>=', $fromWib);
        }

        if ($toDate !== '' && strtotime($toDate) !== false) {
            $toWib = CarbonImmutable::parse($toDate, RentalScheduleService::TIMEZONE)->endOfDay()->format('Y-m-d H:i:s');
            $query->where('pickup_at', '<=', $toWib);
        }

        $schedules = $query->orderBy('pickup_at')->paginate(15)->withQueryString();
        $impactedNextBookings = $this->operationService->detectImpactedNextBookings();

        return view('staff.schedules.index', compact('schedules', 'impactedNextBookings'));
    }

    /**
     * Tampilkan detail transaksi operasional dan formulir inspeksi serah-terima/pengembalian untuk staf (S-02, S-03, S-04).
     */
    public function show(Rental $rental): View
    {
        abort_unless(
            in_array($rental->rental_status, [
                Rental::STATUS_CONFIRMED,
                Rental::STATUS_PICKED_UP,
                Rental::STATUS_RETURNED,
                Rental::STATUS_COMPLETED,
            ], true),
            403,
            'Staf hanya dapat mengakses jadwal transaksi terkonfirmasi dan operasional berjalan.'
        );

        // Muat data non-sensitif penyewa secara eksplisit tanpa dokumen privat KTP/SIM ataupun bukti bayar.
        $rental->load([
            'user:id,name,phone,verification_status,verified_at',
            'car.carType',
            'car.readinessChecker:id,name',
            'inspections.inspector:id,name',
            'histories.actor:id,name',
        ]);

        $handoverCheck = $this->operationService->evaluateHandoverReadiness($rental);
        $carImpacts = $rental->car ? $this->operationService->detectImpactedNextBookings($rental->car) : collect();

        return view('staff.schedules.show', [
            'rental' => $rental,
            'handoverCheck' => $handoverCheck,
            'carImpacts' => $carImpacts,
            'fuelLevels' => Inspection::FUEL_LEVEL_LABELS,
        ]);
    }

    /**
     * Catat inspeksi awal dan konfirmasi pengambilan kendaraan oleh staf lapangan (S-03, SYS-08).
     */
    public function storePickup(Request $request, Rental $rental): RedirectResponse
    {
        $validated = $request->validate([
            'identity_checked' => ['accepted'],
            'actual_pickup_at' => ['nullable', 'date'],
            'odometer_km' => ['required', 'integer', 'min:0', 'max:9999999'],
            'fuel_level' => ['required', Rule::in(array_keys(Inspection::FUEL_LEVEL_LABELS))],
            'condition_notes' => ['nullable', 'string', 'max:1000'],
            'condition_photo' => ['required', 'image', 'mimes:jpg,jpeg,png', 'extensions:jpg,jpeg,png', 'max:4096'],
        ]);

        $this->operationService->confirmPickup(
            $rental,
            $request->user(),
            $validated,
            $request->file('condition_photo')
        );

        return redirect()
            ->route('staff.schedules.show', $rental)
            ->with('success', 'Serah-terima dan inspeksi awal kendaraan berhasil dicatat.');
    }

    /**
     * Catat inspeksi akhir dan pengembalian kendaraan oleh staf lapangan (S-04, SYS-10).
     */
    public function storeReturn(Request $request, Rental $rental): RedirectResponse
    {
        $validated = $request->validate([
            'actual_return_at' => ['nullable', 'date'],
            'odometer_km' => ['required', 'integer', 'min:0', 'max:9999999'],
            'fuel_level' => ['required', Rule::in(array_keys(Inspection::FUEL_LEVEL_LABELS))],
            'condition_notes' => ['nullable', 'string', 'max:1000'],
            'has_damage' => ['nullable', 'boolean'],
            'damage_description' => ['nullable', 'required_if:has_damage,1', 'string', 'min:5', 'max:2000'],
            'condition_photo' => ['required', 'image', 'mimes:jpg,jpeg,png', 'extensions:jpg,jpeg,png', 'max:4096'],
        ]);

        $this->operationService->confirmReturn(
            $rental,
            $request->user(),
            $validated,
            $request->file('condition_photo')
        );

        return redirect()
            ->route('staff.schedules.show', $rental)
            ->with('success', 'Pengembalian kendaraan dan inspeksi akhir berhasil dicatat.');
    }

    /**
     * Tampilkan daftar kesiapan armada bagi staf operasional untuk pemeriksaan, laporan kerusakan, dan penandaan layak (S-05).
     */
    public function readinessIndex(): View
    {
        $cars = Car::activeInventory()
            ->with(['carType', 'readinessChecker:id,name'])
            ->orderByRaw("CASE WHEN readiness_status = 'needs_maintenance' THEN 0 WHEN readiness_status = 'needs_preparation' THEN 1 WHEN readiness_status = 'in_use' THEN 2 ELSE 3 END")
            ->orderBy('name')
            ->get();

        $impactedNextBookings = $this->operationService->detectImpactedNextBookings();

        return view('staff.readiness.index', compact('cars', 'impactedNextBookings'));
    }

    /**
     * Laporkan kerusakan unit oleh staf sehingga kendaraan ditahan dalam status perawatan (S-05).
     */
    public function reportDamage(Request $request, Car $car): RedirectResponse
    {
        $validated = $request->validate([
            'maintenance_reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $this->operationService->reportVehicleDamage($car, $request->user(), $validated['maintenance_reason']);

        return back()->with('success', 'Laporan kerusakan kendaraan tersimpan. Unit ditahan sampai dinyatakan layak kembali.');
    }

    /**
     * Nyatakan kendaraan telah diperiksa dan siap disewakan kembali oleh staf (S-05).
     */
    public function markReady(Request $request, Car $car): RedirectResponse
    {
        $validated = $request->validate([
            'readiness_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->operationService->markVehicleReady($car, $request->user(), $validated['readiness_notes'] ?? null);

        return back()->with('success', 'Kendaraan telah dinyatakan layak dan siap serah-terima.');
    }
}
