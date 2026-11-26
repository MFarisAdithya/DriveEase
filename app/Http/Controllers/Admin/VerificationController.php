<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\IdentityDocuments;
use App\Services\RentalIdentityService;
use App\Services\RentalScheduleService;
use App\Services\VerificationQueue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class VerificationController extends Controller
{
    /**
     * Display list of customer verifications.
     */
    public function index(Request $request): View
    {
        $status = $request->query('status');
        $search = is_string($request->query('search')) ? trim($request->query('search')) : '';

        // VerificationQueue::queries() menyiapkan builder Eloquent terpisah untuk setiap tab
        // status antrean verifikasi agar angka badge dan isi tabel selalu konsisten.
        $queues = app(VerificationQueue::class)->queries();
        $status = is_string($status) && isset($queues[$status]) ? $status : 'all';
        $counts = array_map(fn ($query) => $query->count(), $queues);

        $activeQuery = clone $queues[$status];
        // Jika kata kunci pencarian diisi, bungkus filter ilike pada name/email dalam closure
        // supaya kondisi OR tetap terisolasi di dalam filter status antrean yang dipilih.
        if ($search !== '') {
            $activeQuery->where(function ($query) use ($search): void {
                $query->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        $customers = $activeQuery->latest('updated_at')->orderByDesc('id')->paginate(15)->withQueryString();

        $reviewCutoff = RentalScheduleService::reviewCutoff(now(RentalScheduleService::TIMEZONE));

        return view('admin.verifications.index', compact('customers', 'counts', 'status', 'search', 'reviewCutoff'));
    }

    /**
     * Display customer identity detail.
     */
    public function show(User $user): View
    {
        abort_unless($user->isCustomer(), 404, 'Pelanggan tidak ditemukan.');

        $user->load(['verifier', 'role']);

        // join() menggabungkan tabel rentals dengan cars agar nama mobil ikut terbaca
        // meskipun unit kendaraan lama tersebut sudah berstatus diarsipkan.
        // where() membatasi riwayat hanya milik pelanggan yang sedang ditinjau.
        $rentals = DB::table('rentals')
            ->join('cars', 'cars.id', '=', 'rentals.car_id')
            ->where('rentals.user_id', $user->id)
            ->select(
                'rentals.rental_code',
                'rentals.start_date',
                'rentals.end_date',
                'rentals.total_amount',
                'rentals.rental_status',
                'rentals.payment_status',
                'cars.name as car_name'
            )
            ->orderByDesc('rentals.id')
            ->paginate(10);

        return view('admin.verifications.show', compact('user', 'rentals'));
    }

    /**
     * Approve customer verification.
     */
    public function approve(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->isCustomer(), 404, 'Pelanggan tidak ditemukan.');

        $approved = app(RentalIdentityService::class)->approve($user, $request->user());
        if (! $approved) {
            return back()->with('error', 'Dokumen pelanggan belum lengkap atau berkas tidak ditemukan.');
        }

        return redirect()->route('admin.verifications.show', $user)
            ->with('success', "Identitas pelanggan {$user->name} berhasil diverifikasi dan disetujui.");
    }

    /**
     * Reject customer verification with reason.
     */
    public function reject(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->isCustomer(), 404, 'Pelanggan tidak ditemukan.');

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'rejection_reason.required' => 'Alasan penolakan wajib diisi agar pelanggan dapat memperbaiki dokumennya.',
            'rejection_reason.min' => 'Alasan penolakan minimal 5 karakter.',
            'rejection_reason.max' => 'Alasan penolakan maksimal 500 karakter.',
        ]);

        app(RentalIdentityService::class)->reject($user, $request->user(), $validated['rejection_reason']);

        return redirect()->route('admin.verifications.show', $user)
            ->with('success', "Verifikasi pelanggan {$user->name} berhasil ditolak.");
    }

    public function correctWalkIn(Request $request, User $user, IdentityDocuments $documents): RedirectResponse
    {
        // Profil tanpa email dibuat dari jalur walk-in. Akun portal biasa
        // tetap mengurus penggantian dokumen melalui halaman pelanggan sendiri.
        abort_unless($user->isCustomer() && $user->email === null, 404);
        $validator = Validator::make($request->all(), [
            'ktp_number' => ['nullable', 'digits:16'],
            'sim_number' => ['nullable', 'digits:14'],
            'ktp_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'extensions:jpg,jpeg,png', 'max:2048'],
            'sim_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'extensions:jpg,jpeg,png', 'max:2048'],
        ]);
        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        $validated = $validator->validated();
        if (! $request->filled(['ktp_number']) && ! $request->filled(['sim_number'])
            && ! $request->hasFile('ktp_image') && ! $request->hasFile('sim_image')) {
            return back()->withErrors(['identity' => 'Isi nomor atau pilih foto yang ingin diperbaiki.']);
        }

        try {
            $documents->submit($user, $validated + [
                'ktp_number' => null, 'sim_number' => null,
            ], $request->user(), $request->allFiles());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        } catch (RuntimeException) {
            return back()->withErrors(['identity' => 'Perbaikan dokumen tidak dapat disimpan.']);
        }

        return back()->with('success', 'Perbaikan identitas tersimpan, menunggu pemeriksaan.');
    }
}
