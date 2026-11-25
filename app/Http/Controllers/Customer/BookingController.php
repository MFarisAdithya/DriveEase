<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\Rental;
use App\Models\RentalSetting;
use App\Services\RentalBookingService;
use App\Services\RentalFinancialService;
use App\Services\RentalScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class BookingController extends Controller
{
    public function __construct(
        private readonly RentalScheduleService $scheduleService,
        private readonly RentalBookingService $bookingService,
    ) {}

    /**
     * List bookings belonging strictly to the signed-in customer (SYS-01, C-07).
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        // Lepaskan pesanan kedaluwarsa milik pelanggan sebelum merender daftar pesanan aktif.
        $this->scheduleService->releaseExpiredHolds();

        $bookings = $user->rentals()
            ->with('car.carType')
            ->latest()
            ->paginate(10);

        return view('customer.bookings.index', compact('bookings'));
    }

    /**
     * Show the online booking creation form for verified customers only (C-03, C-05).
     */
    public function create(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        // Pelanggan yang belum lolos verifikasi identitas diarahkan ke halaman verifikasi
        // agar tidak bisa membuat pemesanan sebelum KTP dan SIM disetujui admin (C-03).
        if (! $user->canMakeReservation()) {
            return redirect()->route('customer.verification.create')
                ->with('error', 'Identitas KTP dan SIM Anda harus terverifikasi sebelum dapat membuat pemesanan.');
        }

        $cars = Car::bookableForSchedule()->with('carType')->orderBy('name')->get();
        $hasValidPaymentAccount = RentalSetting::current()?->hasValidPaymentAccount() ?? false;
        $selectedCarId = $request->integer('car_id') ?: null;
        $defaultPickupAt = $request->string('pickup_at')->trim()->toString()
            ?: CarbonImmutable::now(RentalScheduleService::TIMEZONE)->addDay()->setTime(9, 0)->format('Y-m-d\TH:i');
        $defaultDays = max(1, min(RentalScheduleService::MAX_DAYS, $request->integer('total_days', 1)));

        $preview = null;
        if ($selectedCarId !== null) {
            $selectedCar = $cars->firstWhere('id', $selectedCarId);
            if ($selectedCar !== null) {
                try {
                    $parsedPickup = $this->scheduleService->parsePickupAt($defaultPickupAt);
                    if ($parsedPickup->greaterThan(CarbonImmutable::now(RentalScheduleService::TIMEZONE))) {
                        $preview = $this->scheduleService->calculatePricing($selectedCar, $parsedPickup, $defaultDays);
                        $preview['available'] = $this->scheduleService->isCarAvailableForPeriod(
                            $selectedCar,
                            $preview['pickup_at'],
                            $preview['return_at']
                        );
                    }
                } catch (Throwable) {
                    $preview = null;
                }
            }
        }

        $terms = RentalSetting::publicInfo()->terms;
        $idempotencyKey = (string) Str::uuid();

        return view('customer.bookings.create', compact(
            'cars',
            'hasValidPaymentAccount',
            'selectedCarId',
            'defaultPickupAt',
            'defaultDays',
            'preview',
            'terms',
            'idempotencyKey'
        ));
    }

    /**
     * Store a new online booking using shared schedule, pricing, and lock rules (C-05).
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->canMakeReservation()) {
            return redirect()->route('customer.verification.create')
                ->with('error', 'Identitas KTP dan SIM Anda harus terverifikasi sebelum dapat membuat pemesanan.');
        }

        $validated = $request->validate([
            'car_id' => ['required', 'integer', 'exists:cars,id'],
            'pickup_at' => ['required', 'date'],
            'total_days' => ['required', 'integer', 'min:1', 'max:'.RentalScheduleService::MAX_DAYS],
            'accept_terms' => ['accepted'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ]);

        try {
            $pickupAt = $this->scheduleService->parsePickupAt((string) $validated['pickup_at']);
        } catch (Throwable) {
            return back()->withInput()->withErrors([
                'pickup_at' => 'Format waktu pengambilan tidak valid.',
            ]);
        }

        // Delegasikan validasi bisnis, penguncian baris kendaraan, penghitungan tarif WIB,
        // serta snapshot rekening ke RentalBookingService agar aturan online dan walk-in identik.
        $rental = $this->bookingService->createBooking(
            customer: $user,
            carId: (int) $validated['car_id'],
            pickupAt: $pickupAt,
            totalDays: (int) $validated['total_days'],
            termsAccepted: true,
            channel: Rental::CHANNEL_ONLINE,
            createdBy: $user,
            notes: $validated['notes'] ?? null,
            idempotencyKey: $validated['idempotency_key'] ?? null,
        );

        return redirect()->route('customer.bookings.show', $rental)
            ->with('success', "Pesanan {$rental->rental_code} berhasil dibuat. Segera unggah bukti transfer sebelum tenggat pembayaran.");
    }

    /**
     * Display details for a booking owned by the signed-in customer (SYS-01, C-07).
     */
    public function show(Request $request, Rental $rental): View
    {
        // abort_unless() menolak pelanggan lain membaca detail transaksi yang bukan miliknya.
        abort_unless($rental->user_id === $request->user()->id, 403, 'Anda tidak berhak mengakses pesanan ini.');

        $this->scheduleService->releaseExpiredHolds($rental->car_id);
        $rental->refresh()->load([
            'car.carType',
            'payments',
            'changeRequests.proposedCar',
            'extensions.approver',
            'histories.actor',
        ]);

        return view('customer.bookings.show', compact('rental'));
    }

    /**
     * Cancel the signed-in customer's own unpaid booking and release its schedule hold (C-08, SYS-05).
     */
    public function cancel(Request $request, Rental $rental): RedirectResponse
    {
        abort_unless($rental->user_id === $request->user()->id, 403, 'Anda tidak berhak membatalkan pesanan ini.');

        $this->bookingService->cancelUnpaidBooking(
            $rental,
            $request->user(),
            'Dibatalkan oleh pelanggan sebelum pembayaran.'
        );

        return redirect()->route('customer.bookings.show', $rental)
            ->with('success', 'Pesanan berhasil dibatalkan dan jadwal kendaraan telah dilepas.');
    }

    /**
     * Submit a cancellation request after payment proof submission or confirmation (C-08).
     */
    public function requestCancellation(
        Request $request,
        Rental $rental,
        RentalFinancialService $financialService
    ): RedirectResponse {
        abort_unless($rental->user_id === $request->user()->id, 403, 'Anda tidak berhak mengajukan pembatalan untuk pesanan ini.');

        $validated = $request->validate([
            'cancellation_request_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $financialService->requestCustomerCancellation(
            $rental,
            $request->user(),
            (string) $validated['cancellation_request_reason']
        );

        return redirect()->route('customer.bookings.show', $rental)
            ->with('success', 'Pengajuan pembatalan berhasil dikirim dan sedang ditinjau admin.');
    }
}
