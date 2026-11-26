<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\Rental;
use App\Models\RentalSetting;
use App\Models\Role;
use App\Models\User;
use App\Services\IdentityDocuments;
use App\Services\RentalBookingService;
use App\Services\RentalFinancialService;
use App\Services\RentalOperationService;
use App\Services\RentalScheduleService;
use App\Services\WalkInConfirmation;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
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

        $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ], [
            'date_to.after_or_equal' => 'Tanggal akhir filter tidak boleh lebih awal dari tanggal mulai.',
        ]);

        $query = Rental::with(['user', 'car.carType', 'creator']);

        if ($request->filled('rental_status')) {
            $query->where('rental_status', (string) $request->query('rental_status'));
        }

        if ($request->filled('payment_status') && in_array($request->query('payment_status'), [
            Rental::PAYMENT_UNPAID,
            Rental::PAYMENT_PENDING_APPROVAL,
            Rental::PAYMENT_PAID,
            Rental::PAYMENT_REJECTED,
        ], true)) {
            $query->where('payment_status', (string) $request->query('payment_status'));
        }

        if ($request->filled('date_from') && strtotime((string) $request->query('date_from')) !== false) {
            $fromWib = CarbonImmutable::parse((string) $request->query('date_from'), RentalScheduleService::TIMEZONE)->startOfDay();
            $query->where('pickup_at', '>=', $fromWib->format('Y-m-d H:i:s'));
        }

        if ($request->filled('date_to') && strtotime((string) $request->query('date_to')) !== false) {
            $toWib = CarbonImmutable::parse((string) $request->query('date_to'), RentalScheduleService::TIMEZONE)->endOfDay();
            $query->where('pickup_at', '<=', $toWib->format('Y-m-d H:i:s'));
        }

        if ($request->filled('booking_channel') && in_array($request->query('booking_channel'), [Rental::CHANNEL_ONLINE, Rental::CHANNEL_WALK_IN], true)) {
            $query->where('booking_channel', (string) $request->query('booking_channel'));
        }

        // Filter operasional cepat membantu admin menemukan sewa aktif, pembayaran lewat 1 jam SLA,
        // permohonan perubahan jadwal/unit, dan pengajuan perpanjangan yang menunggu tindakan (A-06, SYS-06).
        $activeFilter = (string) $request->query('filter', '');
        if ($activeFilter === 'active_rentals') {
            $query->whereIn('rental_status', [Rental::STATUS_CONFIRMED, Rental::STATUS_PICKED_UP]);
        } elseif ($activeFilter === 'overdue_verification') {
            $query->where('rental_status', Rental::STATUS_PENDING)
                ->where('payment_status', Rental::PAYMENT_PENDING_APPROVAL)
                ->whereNotNull('payment_submitted_at')
                ->where('payment_submitted_at', '<=', RentalScheduleService::reviewCutoff(now('Asia/Jakarta'))->format('Y-m-d H:i:s'));
        } elseif ($activeFilter === 'review_followup') {
            $query->needsReviewFollowup();
        } elseif ($activeFilter === 'pending_changes') {
            $query->whereHas('changeRequests', function ($cq): void {
                $cq->whereIn('status', ['pending_admin', 'pending_customer']);
            });
        } elseif ($activeFilter === 'pending_extensions') {
            $query->whereHas('extensions', function ($eq): void {
                $eq->whereIn('status', ['pending_approval', 'awaiting_payment', 'payment_submitted']);
            });
        } elseif ($activeFilter === 'affected_bookings') {
            $impactedIds = app(RentalOperationService::class)
                ->detectImpactedNextBookings()
                ->map(fn (array $impact): int => $impact['rental']->id)
                ->all();

            $query->where(function ($aq) use ($impactedIds): void {
                $aq->whereNotNull('cancellation_requested_at')
                    ->orWhereHas('changeRequests', fn ($cq) => $cq->whereIn('status', ['pending_admin', 'pending_customer']))
                    ->orWhereHas('histories', fn ($hq) => $hq->where('action', 'next_booking_impacted'));

                if ($impactedIds !== []) {
                    $aq->orWhereIn('id', $impactedIds);
                }
            });
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
            ->where('payment_submitted_at', '<=', RentalScheduleService::reviewCutoff(now('Asia/Jakarta'))->format('Y-m-d H:i:s'))
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
            'confirmation_method' => ['nullable', 'in:in_person,phone,message'],
        ]);

        try {
            $pickupAt = $this->scheduleService->parsePickupAt((string) $validated['pickup_at']);
        } catch (Throwable) {
            return back()->withInput()->withErrors([
                'pickup_at' => 'Format waktu pengambilan tidak valid.',
            ]);
        }

        $customer = User::findOrFail((int) $validated['customer_id']);
        if ($customer->email === null && empty($validated['confirmation_method'])) {
            return back()->withInput()->withErrors(['confirmation_method' => 'Pilih metode konfirmasi persetujuan penyewa walk-in.']);
        }

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
            confirmationMethod: $validated['confirmation_method'] ?? null,
        );

        return redirect()->route('admin.bookings.show', $rental)
            ->with('success', "Pemesanan langsung {$rental->rental_code} berhasil dibuat untuk {$customer->name}.");
    }

    /**
     * Catat identitas penyewa walk-in tanpa membuat kredensial login (UAT-10, A-15).
     */
    public function registerWalkInCustomer(Request $request, IdentityDocuments $documents): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:25'],
            'address' => ['nullable', 'string', 'max:500'],
            'email' => ['prohibited'],
            'password' => ['prohibited'],
            'ktp_number' => ['required', 'digits:16'],
            'sim_number' => ['required', 'digits:14'],
            'ktp_image' => ['required', 'image', 'mimes:jpg,jpeg,png', 'extensions:jpg,jpeg,png', 'max:2048'],
            'sim_image' => ['required', 'image', 'mimes:jpg,jpeg,png', 'extensions:jpg,jpeg,png', 'max:2048'],
        ]);

        // only() membatasi input lama yang dikembalikan ke formulir. Nomor KTP
        // dan SIM tidak ikut dimasukkan ke flash session ketika penyimpanan gagal.
        $contact = $request->only('name', 'phone', 'address');
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput($contact);
        }

        $validated = $validator->validated();
        $paths = [];
        try {
            // store() membuat nama berkas dan mengembalikan path relatif pada disk privat.
            // Simpan kedua path sebelum membuat baris pelanggan agar antrean verifikasi
            // tidak menerima catatan yang kehilangan salah satu berkasnya.
            foreach (['ktp_image', 'sim_image'] as $field) {
                $path = $validated[$field]->store('identities', 'local');
                if ($path === false) {
                    throw new RuntimeException('Gagal menyimpan dokumen walk-in.');
                }
                $paths[$field] = $path;
                if (! $documents->exists($path)) {
                    throw new RuntimeException('Dokumen walk-in tidak ditemukan setelah unggah.');
                }
            }

            // Callback transaksi mengembalikan model User setelah Eloquent menyimpan
            // profil. Model yang sama dipakai antrean verifikasi dan rentals.user_id.
            // Nomor identitas melalui mutator/cast terenkripsi sebelum masuk PostgreSQL.
            $customer = DB::transaction(function () use ($validated, $paths): User {
                $customerRole = Role::query()->where('name', 'customer')->firstOrFail();

                $customer = new User([
                    'role_id' => $customerRole->id,
                    'name' => $validated['name'],
                    'phone' => $validated['phone'],
                    'address' => $validated['address'] ?? null,
                    'ktp_number' => $validated['ktp_number'],
                    'sim_number' => $validated['sim_number'],
                    'verification_status' => 'pending',
                ] + $paths);
                $customer->identity_submitted_at = now(RentalScheduleService::TIMEZONE);
                $customer->saveOrFail();

                return $customer;
            });
        } catch (Throwable) {
            // Bersihkan hanya berkas baru milik permintaan ini jika salah satu
            // unggahan atau simpan profil gagal. Dokumen pelanggan lain tetap utuh.
            foreach ($paths as $path) {
                $documents->delete($path);
            }

            return back()->withInput($contact)->with('error', 'Identitas walk-in belum dapat disimpan. Silakan coba kembali.');
        }

        return redirect()->route('admin.verifications.show', $customer)
            ->with('success', "Identitas walk-in {$customer->name} tersimpan dan siap diperiksa.");
    }

    /**
     * Show full booking details for admin review.
     */
    public function show(Rental $rental, RentalOperationService $operationService): View
    {
        $this->scheduleService->releaseExpiredHolds($rental->car_id);
        $rental->refresh()->load([
            'user',
            'car.carType',
            'creator',
            'payments.verifier',
            'changeRequests.proposedCar',
            'extensions.approver',
            'inspections.inspector',
            'histories.actor',
            'cashFlows.recorder',
        ]);
        $availableReplacementCars = Car::bookableForSchedule()->with('carType')->orderBy('name')->get();
        $impactedNextBookings = $rental->car ? $operationService->detectImpactedNextBookings($rental->car) : collect();

        return view('admin.bookings.show', compact('rental', 'availableReplacementCars', 'impactedNextBookings'));
    }

    /**
     * Cancel an unpaid pending booking from the admin portal (SYS-05).
     */
    public function cancel(Request $request, Rental $rental): RedirectResponse
    {
        // Pembatalan walk-in memakai formulir berkategori agar alasan dan
        // jawaban manual tersimpan walaupun tagihan belum dibayar.
        if ($rental->booking_channel === Rental::CHANNEL_WALK_IN) {
            return back()->withErrors(['cancellation_type' => 'Gunakan pembatalan walk-in dengan kategori dan konfirmasi penyewa jika diperlukan.']);
        }

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
            'contact_attempted' => ['nullable', 'boolean'],
        ]);

        $confirmation = $rental->booking_channel === Rental::CHANNEL_WALK_IN
            && $validated['cancellation_type'] === Rental::CANCEL_CUSTOMER
            && $rental->cancellation_requested_at === null
            ? WalkInConfirmation::fromInput($request->only('confirmation_method', 'confirmed_at'))
            : null;

        $financialService->cancelWithRefund(
            rental: $rental,
            admin: $request->user(),
            cancellationType: (string) $validated['cancellation_type'],
            reason: (string) $validated['cancellation_reason'],
            contactAttempted: (bool) ($validated['contact_attempted'] ?? false),
            confirmation: $confirmation,
        );

        return redirect()->route('admin.bookings.show', $rental)
            ->with('success', 'Pesanan dibatalkan dan hak pengembalian dana telah ditetapkan persis sesuai kebijakan.');
    }

    public function recordReviewContact(Request $request, Rental $rental): RedirectResponse
    {
        $validated = $request->validate([
            'confirmation_method' => ['required', 'in:in_person,phone,message'],
            'contacted_at' => ['required', 'date'],
            'contact_notes' => ['required', 'string', 'max:500'],
        ]);
        $contactedAt = $this->scheduleService->parsePickupAt($validated['contacted_at']);
        if ($contactedAt->isFuture()) {
            return back()->withErrors(['contacted_at' => 'Waktu kontak belum terjadi.']);
        }

        DB::transaction(function () use ($rental, $request, $validated, $contactedAt): void {
            $locked = Rental::query()->lockForUpdate()->findOrFail($rental->id);
            abort_unless($locked->review_followup_at !== null, 409);
            $contactKey = $contactedAt->toIso8601String().'|'.$validated['confirmation_method'];
            // Kunci pesanan mencegah dua pengiriman yang sama mencatat ulang
            // kontak manual pada waktu dan metode yang sama oleh petugas yang sama.
            if (! $locked->histories()->where('action', 'admin_review_contact')
                ->where('actor_id', $request->user()->id)->where('metadata->contact_key', $contactKey)->exists()) {
                $locked->recordHistory($request->user(), 'admin_review_contact', null, null, $validated['contact_notes'], [
                    'contact_key' => $contactKey,
                    'contacted_at' => $contactedAt->toIso8601String(),
                    'confirmation_method' => $validated['confirmation_method'],
                ]);
            }
        });

        return back()->with('success', 'Kontak pelanggan tercatat.');
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
