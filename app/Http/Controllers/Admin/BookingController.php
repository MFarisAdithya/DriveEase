<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\Rental;
use App\Models\RentalSetting;
use App\Models\Role;
use App\Models\User;
use App\Services\RentalBookingService;
use App\Services\RentalFinancialService;
use App\Services\RentalScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
     * Display all online and walk-in bookings for admin inspection (A-06, A-15).
     */
    public function index(Request $request): View
    {
        $this->scheduleService->releaseExpiredHolds();

        $query = Rental::with(['user', 'car.carType', 'creator']);

        if ($request->filled('rental_status')) {
            $query->where('rental_status', (string) $request->query('rental_status'));
        }

        if ($request->filled('booking_channel') && in_array($request->query('booking_channel'), [Rental::CHANNEL_ONLINE, Rental::CHANNEL_WALK_IN], true)) {
            $query->where('booking_channel', (string) $request->query('booking_channel'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($q) use ($search): void {
                $q->where('rental_code', 'ilike', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search): void {
                        $uq->where('name', 'ilike', "%{$search}%")
                            ->orWhere('email', 'ilike', "%{$search}%");
                    })
                    ->orWhereHas('car', function ($cq) use ($search): void {
                        $cq->where('name', 'ilike', "%{$search}%")
                            ->orWhere('plate_number', 'ilike', "%{$search}%");
                    });
            });
        }

        $bookings = $query->latest()->paginate(12)->withQueryString();

        // Hitung pesanan menunggu verifikasi admin yang sudah melewati batas 1 jam (SYS-06).
        $overdueVerificationCount = Rental::query()
            ->where('rental_status', Rental::STATUS_PENDING)
            ->where('payment_status', Rental::PAYMENT_PENDING_APPROVAL)
            ->whereNotNull('payment_submitted_at')
            ->where('payment_submitted_at', '<', now('Asia/Jakarta')->subHour()->format('Y-m-d H:i:s'))
            ->count();

        return view('admin.bookings.index', compact('bookings', 'overdueVerificationCount'));
    }

    /**
     * Show the walk-in booking form and assisted customer registration form (A-15).
     */
    public function create(Request $request): View
    {
        $customers = User::query()
            ->whereHas('role', fn ($q) => $q->where('name', 'customer'))
            ->whereNull('deactivated_at')
            ->orderBy('name')
            ->get();

        $cars = Car::bookableForSchedule()->with('carType')->orderBy('name')->get();
        $hasValidPaymentAccount = RentalSetting::current()?->hasValidPaymentAccount() ?? false;
        $selectedCarId = $request->integer('car_id') ?: null;
        $selectedCustomerId = $request->integer('customer_id') ?: null;
        $defaultPickupAt = $request->string('pickup_at')->trim()->toString()
            ?: CarbonImmutable::now(RentalScheduleService::TIMEZONE)->addDay()->setTime(9, 0)->format('Y-m-d\TH:i');
        $defaultDays = max(1, min(RentalScheduleService::MAX_DAYS, $request->integer('total_days', 1)));
        $terms = RentalSetting::publicInfo()->terms;
        $idempotencyKey = (string) Str::uuid();

        return view('admin.bookings.create', compact(
            'customers',
            'cars',
            'hasValidPaymentAccount',
            'selectedCarId',
            'selectedCustomerId',
            'defaultPickupAt',
            'defaultDays',
            'terms',
            'idempotencyKey'
        ));
    }

    /**
     * Store a walk-in booking using the same verification, pricing, and locking rules as online booking (A-15).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:users,id'],
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

        $customer = User::findOrFail((int) $validated['customer_id']);

        // Pemesanan langsung oleh admin tetap menggunakan RentalBookingService sehingga aturan
        // verifikasi identitas, tarif 24 jam WIB, jeda 1 jam, dan status awal belum lunas tetap berlaku.
        $rental = $this->bookingService->createBooking(
            customer: $customer,
            carId: (int) $validated['car_id'],
            pickupAt: $pickupAt,
            totalDays: (int) $validated['total_days'],
            termsAccepted: true,
            channel: Rental::CHANNEL_WALK_IN,
            createdBy: $request->user(),
            notes: $validated['notes'] ?? null,
            idempotencyKey: $validated['idempotency_key'] ?? null,
        );

        return redirect()->route('admin.bookings.show', $rental)
            ->with('success', "Pemesanan langsung {$rental->rental_code} berhasil dibuat untuk {$customer->name}.");
    }

    /**
     * Register a walk-in customer account in pending verification state before booking (A-15).
     */
    public function registerWalkInCustomer(Request $request): RedirectResponse
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => Str::lower(trim($request->input('email')))]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:25'],
            'address' => ['nullable', 'string', 'max:500'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $customerRole = Role::firstOrCreate(
            ['name' => 'customer'],
            ['display_name' => 'Pelanggan']
        );

        // Akun pelanggan baru hasil pendaftaran langsung tetap berstatus 'pending'
        // dan wajib menyelesaikan verifikasi identitas sebelum dapat dibuatkan pesanan.
        $customer = User::create([
            'role_id' => $customerRole->id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'address' => $validated['address'] ?? null,
            'password' => Hash::make($validated['password']),
            'verification_status' => 'pending',
        ]);

        return redirect()->route('admin.verifications.show', $customer)
            ->with('success', "Akun pelanggan {$customer->name} berhasil dibuat. Lengkapi unggahan KTP/SIM dan setujui verifikasi sebelum membuat pemesanan langsung.");
    }

    /**
     * Show full booking details for admin review.
     */
    public function show(Rental $rental): View
    {
        $this->scheduleService->releaseExpiredHolds($rental->car_id);
        $rental->refresh()->load(['user', 'car.carType', 'creator', 'payments.verifier', 'histories.actor']);

        return view('admin.bookings.show', compact('rental'));
    }

    /**
     * Cancel an unpaid pending booking from the admin portal (SYS-05).
     */
    public function cancel(Request $request, Rental $rental): RedirectResponse
    {
        $validated = $request->validate([
            'cancellation_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->bookingService->cancelUnpaidBooking(
            $rental,
            $request->user(),
            $validated['cancellation_reason'] ?? null
        );

        return redirect()->route('admin.bookings.show', $rental)
            ->with('success', 'Pesanan berhasil dibatalkan dan jadwal kendaraan telah dilepas.');
    }

    /**
     * Cancel a booking with refund calculation according to cancellation policy (A-06, A-10).
     */
    public function cancelWithRefund(
        Request $request,
        Rental $rental,
        RentalFinancialService $financialService
    ): RedirectResponse {
        $validated = $request->validate([
            'cancellation_type' => ['required', 'in:customer_request,rental_fault,no_show'],
            'cancellation_reason' => ['required', 'string', 'min:5', 'max:1000'],
            'refund_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $financialService->cancelWithRefund(
            rental: $rental,
            admin: $request->user(),
            cancellationType: (string) $validated['cancellation_type'],
            reason: (string) $validated['cancellation_reason'],
            overrideRefundAmount: isset($validated['refund_amount']) && $validated['refund_amount'] !== ''
                ? (float) $validated['refund_amount']
                : null,
        );

        return redirect()->route('admin.bookings.show', $rental)
            ->with('success', 'Pesanan dibatalkan dan perhitungan pengembalian dana telah dicatat.');
    }

    /**
     * Record actual refund transfer date, amount, and reference number (A-10).
     */
    public function realizeRefund(
        Request $request,
        Rental $rental,
        RentalFinancialService $financialService
    ): RedirectResponse {
        $validated = $request->validate([
            'transfer_amount' => ['required', 'numeric', 'min:1'],
            'transferred_at' => ['required', 'date'],
            'refund_reference' => ['required', 'string', 'max:100'],
            'refund_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $financialService->realizeRefund(
            rental: $rental,
            admin: $request->user(),
            transferAmount: (float) $validated['transfer_amount'],
            transferredAt: CarbonImmutable::parse((string) $validated['transferred_at'], RentalScheduleService::TIMEZONE),
            reference: (string) $validated['refund_reference'],
            notes: $validated['refund_notes'] ?? null,
        );

        return redirect()->route('admin.bookings.show', $rental)
            ->with('success', 'Realisasi transfer pengembalian dana berhasil dicatat.');
    }
}
