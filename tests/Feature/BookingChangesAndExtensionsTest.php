<?php

namespace Tests\Feature;

use App\Models\Car;
use App\Models\CarType;
use App\Models\Rental;
use App\Models\RentalChangeRequest;
use App\Models\RentalExtension;
use App\Models\Role;
use App\Models\User;
use App\Services\RentalBookingService;
use App\Services\RentalExtensionService;
use App\Services\RentalPaymentService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class BookingChangesAndExtensionsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('rental_settings')->upsert([
            'id' => 1,
            'business_name' => 'DriveEase Uji Perubahan',
            'services' => 'Sewa mobil lepas kunci',
            'address' => 'Jl. Uji No. 1',
            'contact' => '081234567890',
            'operating_hours' => '08.00-21.00 WIB',
            'terms' => 'Ketentuan uji resmi.',
            'bank_name' => 'Bank Uji DriveEase',
            'account_number' => '123456789012',
            'account_holder' => 'PT DriveEase Uji',
        ], ['id']);
    }

    public function test_customer_reschedule_requires_admin_approval_and_blocks_conflicts(): void
    {
        $bookingService = app(RentalBookingService::class);
        $admin = $this->createUserWithRole('admin');
        $customer = $this->createVerifiedCustomer();
        $otherCustomer = $this->createVerifiedCustomer();
        $car = $this->createBookableCar(500000);

        $pickupDay1 = CarbonImmutable::now('Asia/Jakarta')->addDays(3)->setTime(9, 0);
        $rental = $bookingService->createBooking($customer, $car->id, $pickupDay1, 2, true);

        // Pesanan lain menempati hari ke-7 sampai hari ke-8 agar bentrok jika jadwal digeser ke sana.
        $blockingPickup = $pickupDay1->addDays(4);
        $bookingService->createBooking($otherCustomer, $car->id, $blockingPickup, 1, true);

        // Pengajuan ke slot bentrok harus ditolak sejak awal.
        $this->actingAs($customer)->post(route('customer.bookings.change-requests.store', $rental), [
            'proposed_pickup_at' => $blockingPickup->format('Y-m-d\TH:i'),
            'proposed_total_days' => 1,
            'reason' => 'Ingin memindah tanggal yang ternyata penuh',
        ])->assertSessionHasErrors('proposed_pickup_at');

        // Pengajuan sah ke hari ke-10 selama 3 hari -> status pending_admin, jadwal pesanan belum berubah.
        $newPickup = $pickupDay1->addDays(7)->setTime(10, 0);
        $this->actingAs($customer)->post(route('customer.bookings.change-requests.store', $rental), [
            'proposed_pickup_at' => $newPickup->format('Y-m-d\TH:i'),
            'proposed_total_days' => 3,
            'reason' => 'Acara keluarga diundur',
        ])->assertRedirect(route('customer.bookings.show', $rental));

        $changeRequest = RentalChangeRequest::where('rental_id', $rental->id)->firstOrFail();
        $this->assertSame(RentalChangeRequest::STATUS_PENDING_ADMIN, $changeRequest->status);

        // Non-admin ditolak (403) saat mencoba menyetujui permohonan pelanggan.
        $this->actingAs($otherCustomer)->patch(route('admin.change-requests.respond', $changeRequest), [
            'decision' => 'approve',
        ])->assertForbidden();

        // Admin menyetujui permohonan -> jadwal & total tarif pesanan ikut terbarukan, histori tercatat.
        $this->actingAs($admin)->patch(route('admin.change-requests.respond', $changeRequest), [
            'decision' => 'approve',
            'response_notes' => 'Jadwal baru tersedia dan disetujui.',
        ])->assertRedirect(route('admin.bookings.show', $rental));

        $rental->refresh();
        $changeRequest->refresh();
        $this->assertSame(RentalChangeRequest::STATUS_APPROVED, $changeRequest->status);
        $this->assertTrue($rental->pickup_at->timezone('Asia/Jakarta')->equalTo($newPickup));
        $this->assertSame(3, $rental->total_days);
        $this->assertEquals(1500000.0, (float) $rental->total_amount);
        $this->assertDatabaseHas('rental_histories', [
            'rental_id' => $rental->id,
            'action' => 'change_approved',
            'actor_id' => $admin->id,
        ]);
    }

    public function test_admin_car_replacement_proposal_supports_waive_fee_and_customer_consent(): void
    {
        $bookingService = app(RentalBookingService::class);
        $paymentService = app(RentalPaymentService::class);
        $admin = $this->createUserWithRole('admin');
        $customer = $this->createVerifiedCustomer();
        $otherCustomer = $this->createVerifiedCustomer();

        $originalCar = $this->createBookableCar(450000);
        $upgradeCar = $this->createBookableCar(700000);

        $pickupAt = CarbonImmutable::now('Asia/Jakarta')->addDays(4)->setTime(9, 0);
        $rental = $bookingService->createBooking($customer, $originalCar->id, $pickupAt, 2, true);
        $payment = $paymentService->submitProof($rental, $customer, UploadedFile::fake()->image('lunas.jpg'));
        $paymentService->approvePayment($payment, $admin, (float) $rental->total_amount);
        $rental->refresh();

        // Admin mengusulkan penggantian kendaraan lebih mahal dengan selisih biaya 0 (A-06).
        $this->actingAs($admin)->post(route('admin.bookings.change-requests.propose', $rental), [
            'request_type' => RentalChangeRequest::TYPE_CAR_REPLACEMENT,
            'proposed_car_id' => $upgradeCar->id,
            'waive_price_difference' => 1,
            'reason' => 'Unit awal masuk perawatan mendadak; diberikan unit pengganti tanpa biaya tambahan.',
        ])->assertRedirect(route('admin.bookings.show', $rental));

        $proposal = RentalChangeRequest::where('rental_id', $rental->id)->firstOrFail();
        $this->assertSame(RentalChangeRequest::STATUS_PENDING_CUSTOMER, $proposal->status);
        $this->assertTrue($proposal->waive_price_difference);
        $this->assertEquals(900000.0, (float) $proposal->proposed_total_amount);

        // Pelanggan lain tidak boleh menanggapi usulan admin.
        $this->actingAs($otherCustomer)->patch(route('customer.change-requests.respond', $proposal), [
            'decision' => 'approve',
        ])->assertForbidden();

        // Jika pelanggan menolak tanpa alasan wajib -> error validasi.
        $this->actingAs($customer)->patch(route('customer.change-requests.respond', $proposal), [
            'decision' => 'reject',
        ])->assertSessionHasErrors('response_notes');

        // Pelanggan menyetujui usulan -> mobil terganti ke upgradeCar dan tagihan tetap 900.000.
        $this->actingAs($customer)->patch(route('customer.change-requests.respond', $proposal), [
            'decision' => 'approve',
        ])->assertRedirect(route('customer.bookings.show', $rental));

        $rental->refresh();
        $this->assertSame($upgradeCar->id, $rental->car_id);
        $this->assertEquals(900000.0, (float) $rental->total_amount);
        $this->assertEquals(900000.0, (float) $rental->paid_amount);

        // Usulan penjadwalan ulang yang ditolak pelanggan mempertahankan kondisi pesanan saat ini.
        $laterPickup = $pickupAt->addDays(2);
        $this->actingAs($admin)->post(route('admin.bookings.change-requests.propose', $rental), [
            'request_type' => RentalChangeRequest::TYPE_RESCHEDULE,
            'proposed_pickup_at' => $laterPickup->format('Y-m-d\TH:i'),
            'proposed_total_days' => 2,
            'reason' => 'Penawaran geser hari',
        ])->assertRedirect(route('admin.bookings.show', $rental));

        $secondProposal = RentalChangeRequest::where('rental_id', $rental->id)->latest('id')->firstOrFail();
        $this->actingAs($customer)->patch(route('customer.change-requests.respond', $secondProposal), [
            'decision' => 'reject',
            'response_notes' => 'Jadwal awal tetap dibutuhkan.',
        ])->assertRedirect(route('customer.bookings.show', $rental));

        $rental->refresh();
        $this->assertTrue($rental->pickup_at->timezone('Asia/Jakarta')->equalTo($pickupAt));
        $this->assertSame(RentalChangeRequest::STATUS_REJECTED, $secondProposal->fresh()->status);

        // Penambahan hari berbayar pada pesanan lunas tidak bisa melewati reschedule tanpa pembebasan biaya.
        $this->actingAs($customer)->post(route('customer.bookings.change-requests.store', $rental), [
            'proposed_pickup_at' => $pickupAt->format('Y-m-d\TH:i'),
            'proposed_total_days' => 4,
            'reason' => 'Mencoba tambah hari tanpa fitur perpanjangan',
        ])->assertSessionHasErrors('proposed_total_days');
    }

    public function test_extension_flow_enforces_double_availability_check_and_private_proof_access(): void
    {
        $bookingService = app(RentalBookingService::class);
        $paymentService = app(RentalPaymentService::class);
        $admin = $this->createUserWithRole('admin');
        $staff = $this->createUserWithRole('staff');
        $customer = $this->createVerifiedCustomer();
        $otherCustomer = $this->createVerifiedCustomer();
        $car = $this->createBookableCar(500000);

        $pickupAt = CarbonImmutable::now('Asia/Jakarta')->addDays(2)->setTime(9, 0);
        $rental = $bookingService->createBooking($customer, $car->id, $pickupAt, 2, true);
        $payment = $paymentService->submitProof($rental, $customer, UploadedFile::fake()->image('lunas.jpg'));
        $paymentService->approvePayment($payment, $admin, (float) $rental->total_amount);
        $rental->refresh();

        $originalReturnAt = $rental->return_at->copy()->timezone('Asia/Jakarta');

        // Tahap 1: Pelanggan mengajukan tambahan 2 hari.
        $this->actingAs($customer)->post(route('customer.bookings.extensions.store', $rental), [
            'additional_days' => 2,
            'notes' => 'Perlu tambahan waktu di luar kota.',
        ])->assertRedirect(route('customer.bookings.show', $rental));

        $extension = RentalExtension::where('rental_id', $rental->id)->firstOrFail();
        $this->assertSame(RentalExtension::STATUS_PENDING_APPROVAL, $extension->status);
        $this->assertEquals(1000000.0, (float) $extension->additional_amount);

        // Admin menyetujui tahap 1 -> status menjadi awaiting_payment, tetapi return_at pesanan BELUM berubah.
        $this->actingAs($admin)->patch(route('admin.extensions.approve', $extension))
            ->assertRedirect(route('admin.bookings.show', $rental));

        $rental->refresh();
        $extension->refresh();
        $this->assertSame(RentalExtension::STATUS_AWAITING_PAYMENT, $extension->status);
        $this->assertTrue($rental->return_at->timezone('Asia/Jakarta')->equalTo($originalReturnAt));
        $this->assertSame(2, $rental->total_days);

        // Pelanggan mengunggah bukti bayar perpanjangan secara privat.
        $this->actingAs($customer)->post(route('customer.extensions.payment.store', $extension), [
            'payment_proof' => UploadedFile::fake()->image('ext-proof.jpg'),
        ])->assertRedirect(route('customer.bookings.show', $rental));

        $extension->refresh();
        $this->assertSame(RentalExtension::STATUS_PAYMENT_SUBMITTED, $extension->status);
        $this->assertNotNull($extension->payment_proof);

        // Hak akses dokumen privat bukti perpanjangan: pemilik & admin 200 no-store, staf & pelanggan lain 403.
        $proofUrl = route('documents.extension-payment.show', $extension);
        $ownerResponse = $this->actingAs($customer)->get($proofUrl);
        $ownerResponse->assertOk();
        $this->assertStringContainsString('no-store', (string) $ownerResponse->headers->get('Cache-Control'));

        $this->actingAs($admin)->get($proofUrl)->assertOk();
        $this->actingAs($staff)->get($proofUrl)->assertForbidden();
        $this->actingAs($otherCustomer)->get($proofUrl)->assertForbidden();

        // Verifikasi dengan nominal aktual kurang dari tagihan perpanjangan harus ditolak.
        $this->actingAs($admin)->patch(route('admin.extensions.payment.approve', $extension), [
            'verified_amount' => 500000,
        ])->assertSessionHasErrors('verified_amount');

        // Admin memverifikasi bukti pembayaran dengan nominal aktual penuh -> return_at, total_days, total_amount, paid_amount diperbarui.
        $this->actingAs($admin)->patch(route('admin.extensions.payment.approve', $extension), [
            'verified_amount' => 1000000,
        ])->assertRedirect(route('admin.bookings.show', $rental));

        $rental->refresh();
        $extension->refresh();
        $this->assertSame(RentalExtension::STATUS_COMPLETED, $extension->status);
        $this->assertSame(4, $rental->total_days);
        $this->assertEquals(2000000.0, (float) $rental->total_amount);
        $this->assertEquals(2000000.0, (float) $rental->paid_amount);
        $this->assertEquals(1000000.0, (float) $extension->verified_amount);
        $this->assertTrue($rental->return_at->timezone('Asia/Jakarta')->equalTo($originalReturnAt->addDays(2)));
        $this->assertDatabaseHas('rental_histories', [
            'rental_id' => $rental->id,
            'action' => 'extension_activated',
        ]);
    }

    public function test_extension_rejects_when_next_booking_conflicts_before_or_after_approval_and_cleans_orphans(): void
    {
        $bookingService = app(RentalBookingService::class);
        $paymentService = app(RentalPaymentService::class);
        $admin = $this->createUserWithRole('admin');
        $customer = $this->createVerifiedCustomer();
        $otherCustomer = $this->createVerifiedCustomer();
        $car = $this->createBookableCar(400000);

        $pickupAt = CarbonImmutable::now('Asia/Jakarta')->addDays(2)->setTime(9, 0);
        $rental = $bookingService->createBooking($customer, $car->id, $pickupAt, 2, true);
        $payment = $paymentService->submitProof($rental, $customer, UploadedFile::fake()->image('lunas1.jpg'));
        $paymentService->approvePayment($payment, $admin, (float) $rental->total_amount);
        $rental->refresh();

        // Pelanggan mengajukan perpanjangan 2 hari saat jadwal tambahan masih kosong.
        $this->actingAs($customer)->post(route('customer.bookings.extensions.store', $rental), [
            'additional_days' => 2,
        ])->assertRedirect(route('customer.bookings.show', $rental));

        $extension = RentalExtension::where('rental_id', $rental->id)->firstOrFail();

        // Admin menyetujui tahap 1 (awaiting_payment).
        $this->actingAs($admin)->patch(route('admin.extensions.approve', $extension))
            ->assertRedirect(route('admin.bookings.show', $rental));

        // Pelanggan mengunggah bukti bayar perpanjangan.
        $this->actingAs($customer)->post(route('customer.extensions.payment.store', $extension), [
            'payment_proof' => UploadedFile::fake()->image('ext-proof.jpg'),
        ])->assertRedirect(route('customer.bookings.show', $rental));

        // Sebelum admin memverifikasi bayar perpanjangan, pelanggan lain memesan slot setelah jadwal awal (jeda 2 jam aman).
        $nextPickup = $rental->return_at->copy()->timezone('Asia/Jakarta')->addHours(2);
        $bookingService->createBooking($otherCustomer, $car->id, $nextPickup, 1, true);

        // Pemeriksaan ulang tahap 2 (SYS-09) harus menolak aktivasi jadwal perpanjangan karena kini bentrok!
        $this->actingAs($admin)->patch(route('admin.extensions.payment.approve', $extension), [
            'verified_amount' => 800000,
        ])->assertSessionHasErrors('extension_id');

        $rental->refresh();
        $this->assertSame(2, $rental->total_days);
        $this->assertSame(RentalExtension::STATUS_PAYMENT_SUBMITTED, $extension->fresh()->status);

        // Penolakan bukti pembayaran perpanjangan mewajibkan alasan & tidak mengaktifkan jadwal baru.
        $this->actingAs($admin)->patch(route('admin.extensions.payment.reject', $extension), [])
            ->assertSessionHasErrors('rejection_reason');

        $this->actingAs($admin)->patch(route('admin.extensions.payment.reject', $extension), [
            'rejection_reason' => 'Nominal transfer belum sesuai tagihan perpanjangan.',
        ])->assertRedirect(route('admin.bookings.show', $rental));

        $extension->refresh();
        $this->assertSame(RentalExtension::STATUS_AWAITING_PAYMENT, $extension->status);

        // Simulasikan gagal DB saat unggah ulang bukti pembayaran -> berkas yatim di disk privat harus terhapus (SYS-02, NFR-04).
        $existingFilesBefore = Storage::disk('local')->allFiles('payments/extensions');
        $shouldFailQuery = true;
        DB::listen(static function ($query) use (&$shouldFailQuery): void {
            if ($shouldFailQuery && str_contains($query->sql, 'insert into "rental_histories"')) {
                throw new RuntimeException('Simulasi kegagalan penyimpanan audit perpanjangan.');
            }
        });

        try {
            app(RentalExtensionService::class)->submitExtensionPayment(
                $extension,
                $customer,
                UploadedFile::fake()->image('should-cleanup.jpg')
            );
            $this->fail('Seharusnya melempar RuntimeException simulasi kegagalan DB.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Simulasi kegagalan', $exception->getMessage());
        } finally {
            $shouldFailQuery = false;
        }

        $existingFilesAfter = Storage::disk('local')->allFiles('payments/extensions');
        $this->assertCount(count($existingFilesBefore), $existingFilesAfter);

        // Jika perpanjangan batal diaktifkan setelah uang perpanjangan terverifikasi masuk, admin membatalkan
        // dan menetapkan refund 100% lalu merealisasikan pengembalian dana dengan referensi transfer (A2).
        $this->actingAs($admin)->patch(route('admin.extensions.payment.reject', $extension), [
            'rejection_reason' => 'Jadwal tambahan sudah ditempati pesanan berikutnya; dana perpanjangan dikembalikan penuh.',
            'received_amount' => 800000,
            'cancel_extension' => 1,
        ])->assertRedirect(route('admin.bookings.show', $rental));

        $extension->refresh();
        $this->assertSame(RentalExtension::STATUS_REJECTED, $extension->status);
        $this->assertEquals(800000.0, (float) $extension->paid_amount);
        $this->assertEquals(800000.0, (float) $extension->refund_amount);
        $this->assertSame(RentalExtension::REFUND_PENDING, $extension->refund_status);

        // Nominal refund menyimpang dari dana diterima ditolak.
        $this->actingAs($admin)->patch(route('admin.extensions.refund', $extension), [
            'transfer_amount' => 500000,
            'transferred_at' => now('Asia/Jakarta')->format('Y-m-d\TH:i'),
            'refund_reference' => 'REF-EXT-BAD',
        ])->assertSessionHasErrors('transfer_amount');

        $this->actingAs($admin)->patch(route('admin.extensions.refund', $extension), [
            'transfer_amount' => 800000,
            'transferred_at' => now('Asia/Jakarta')->format('Y-m-d\TH:i'),
            'refund_reference' => 'REF-EXT-800',
        ])->assertRedirect(route('admin.bookings.show', $rental));

        $extension->refresh();
        $this->assertSame(RentalExtension::REFUND_PROCESSED, $extension->refund_status);
        $this->assertSame('REF-EXT-800', $extension->refund_reference);
    }

    public function test_extension_rejection_and_admin_booking_filters_work_correctly(): void
    {
        $bookingService = app(RentalBookingService::class);
        $paymentService = app(RentalPaymentService::class);
        $admin = $this->createUserWithRole('admin');
        $customer = $this->createVerifiedCustomer();
        $otherCustomer = $this->createVerifiedCustomer();
        $car = $this->createBookableCar(350000);

        $pickupAt = CarbonImmutable::now('Asia/Jakarta')->addDays(2)->setTime(9, 0);
        $rental = $bookingService->createBooking($customer, $car->id, $pickupAt, 2, true);
        $payment = $paymentService->submitProof($rental, $customer, UploadedFile::fake()->image('lunas2.jpg'));
        $paymentService->approvePayment($payment, $admin, (float) $rental->total_amount);

        // Pesanan pelanggan lain terpasang tepat 90 menit setelah batas kembali awal -> memperpanjang 1 hari bentrok di tahap 1.
        $nextPickup = $rental->return_at->copy()->timezone('Asia/Jakarta')->addMinutes(90);
        $rentalOther = $bookingService->createBooking($otherCustomer, $car->id, $nextPickup, 1, true);

        // Pemeriksaan tahap 1 menolak pengajuan pelanggan ketika slot berikutnya sudah ditempati.
        $this->actingAs($customer)->post(route('customer.bookings.extensions.store', $rental), [
            'additional_days' => 1,
        ])->assertSessionHasErrors('additional_days');

        // Jika slot berikutnya dilepas, pengajuan tersimpan lalu admin dapat menolak dengan alasan wajib.
        $rentalOther->transitionRentalStatus(Rental::STATUS_CANCELLED, 'Slot dilepas.');
        $rentalOther->save();

        $this->actingAs($customer)->post(route('customer.bookings.extensions.store', $rental), [
            'additional_days' => 1,
        ])->assertRedirect(route('customer.bookings.show', $rental));

        $extension = RentalExtension::where('rental_id', $rental->id)->firstOrFail();

        // Filter daftar pemesanan admin: pending_extensions & active_rentals menampilkan kode pesanan terkait.
        $this->actingAs($admin)->get(route('admin.bookings.index', ['filter' => 'pending_extensions']))
            ->assertOk()
            ->assertSee($rental->rental_code);

        $this->actingAs($admin)->get(route('admin.bookings.index', ['filter' => 'active_rentals']))
            ->assertOk()
            ->assertSee($rental->rental_code);

        // Tolak pengajuan perpanjangan wajib menyertakan alasan.
        $this->actingAs($admin)->patch(route('admin.extensions.reject', $extension), [])
            ->assertSessionHasErrors('rejection_reason');

        $this->actingAs($admin)->patch(route('admin.extensions.reject', $extension), [
            'rejection_reason' => 'Unit dijadwalkan untuk perawatan berkala.',
        ])->assertRedirect(route('admin.bookings.show', $rental));

        $this->assertSame(RentalExtension::STATUS_REJECTED, $extension->fresh()->status);

        $this->assertInstanceOf(ValidationException::class, ValidationException::withMessages(['ok' => 'ok']));
    }

    private function createUserWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName)]);

        return User::create([
            'role_id' => $role->id,
            'name' => ucfirst($roleName).' Change '.uniqid(),
            'email' => $roleName.'_chg_'.uniqid().'@driveease.test',
            'phone' => '081234567890',
            'password' => Hash::make('password123'),
        ]);
    }

    private function createVerifiedCustomer(): User
    {
        $role = Role::firstOrCreate(['name' => 'customer'], ['display_name' => 'Pelanggan']);

        return User::create([
            'role_id' => $role->id,
            'name' => 'Customer Change '.uniqid(),
            'email' => 'cust_chg_'.uniqid().'@driveease.test',
            'phone' => '081234567890',
            'password' => Hash::make('password123'),
            'ktp_number' => '3174012345678901',
            'sim_number' => '12345678901234',
            'verification_status' => 'verified',
            'verified_at' => now(),
        ]);
    }

    private function createBookableCar(int $pricePerDay): Car
    {
        $carType = CarType::firstOrCreate(['name' => 'SUV Uji'], ['description' => 'Tipe uji perubahan']);

        return Car::create([
            'car_type_id' => $carType->id,
            'name' => 'Toyota Rush Uji '.substr(uniqid(), -5),
            'plate_number' => 'B '.random_int(1000, 9999).' UI',
            'brand' => 'Toyota',
            'model_year' => 2024,
            'transmission' => 'automatic',
            'capacity' => 7,
            'price_per_day' => $pricePerDay,
            'status' => 'available',
        ]);
    }
}
