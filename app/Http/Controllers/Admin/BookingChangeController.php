<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rental;
use App\Models\RentalChangeRequest;
use App\Models\RentalExtension;
use App\Services\RentalChangeService;
use App\Services\RentalExtensionService;
use App\Services\RentalScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BookingChangeController extends Controller
{
    public function __construct(
        private readonly RentalChangeService $changeService,
        private readonly RentalExtensionService $extensionService,
    ) {}

    /**
     * Ajukan usulan perubahan jadwal atau penggantian kendaraan oleh admin (A-06).
     */
    public function proposeChange(Request $request, Rental $rental): RedirectResponse
    {
        $validated = $request->validate([
            'request_type' => ['required', 'in:reschedule,car_replacement'],
            'proposed_car_id' => ['nullable', 'required_if:request_type,car_replacement', 'integer', 'exists:cars,id'],
            'proposed_pickup_at' => ['nullable', 'date'],
            'proposed_total_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'waive_price_difference' => ['nullable', 'boolean'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $this->changeService->proposeByAdmin($rental, $request->user(), $validated);

        return redirect()
            ->route('admin.bookings.show', $rental)
            ->with('success', 'Usulan penyesuaian pesanan berhasil dikirim kepada pelanggan untuk dimintai persetujuan.');
    }

    /**
     * Setujui atau tolak permohonan perubahan jadwal yang diajukan oleh pelanggan (A-06, C-07).
     */
    public function respondToRequest(Request $request, RentalChangeRequest $changeRequest): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'response_notes' => ['nullable', 'required_if:decision,reject', 'string', 'max:500'],
        ]);

        $this->changeService->respond(
            $changeRequest,
            $request->user(),
            $validated['decision'],
            $validated['response_notes'] ?? null
        );

        $message = $validated['decision'] === 'approve'
            ? 'Permohonan perubahan pesanan pelanggan berhasil disetujui dan diterapkan.'
            : 'Permohonan perubahan pesanan pelanggan ditolak.';

        return redirect()
            ->route('admin.bookings.show', $changeRequest->rental_id)
            ->with('success', $message);
    }

    /**
     * Setujui pengajuan perpanjangan sewa setelah pengecekan ketersediaan tahap 1 (A-08, SYS-09).
     */
    public function approveExtension(Request $request, RentalExtension $extension): RedirectResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->extensionService->approveExtension($extension, $request->user(), $validated['notes'] ?? null);

        return redirect()
            ->route('admin.bookings.show', $extension->rental_id)
            ->with('success', 'Pengajuan perpanjangan disetujui. Jadwal kembali baru aktif setelah pembayaran tambahan diverifikasi.');
    }

    /**
     * Tolak pengajuan perpanjangan sewa dengan alasan wajib (A-08).
     */
    public function rejectExtension(Request $request, RentalExtension $extension): RedirectResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:500'],
        ]);

        $this->extensionService->rejectExtension($extension, $request->user(), $validated['rejection_reason']);

        return redirect()
            ->route('admin.bookings.show', $extension->rental_id)
            ->with('success', 'Pengajuan perpanjangan sewa berhasil ditolak.');
    }

    /**
     * Verifikasi pelunasan perpanjangan sewa berdasarkan nominal aktual diterima dan aktifkan batas kembali baru (A-08, SYS-09).
     */
    public function approveExtensionPayment(Request $request, RentalExtension $extension): RedirectResponse
    {
        $validated = $request->validate([
            'verified_amount' => ['required', 'numeric', 'min:1'],
            'received_at' => ['required', 'date'],
        ], [
            'received_at.required' => 'Tanggal aktual dana perpanjangan diterima (WIB) wajib diisi.',
        ]);

        $this->extensionService->approveExtensionPayment(
            $extension,
            $request->user(),
            (float) $validated['verified_amount'],
            CarbonImmutable::parse((string) $validated['received_at'], RentalScheduleService::TIMEZONE),
        );

        return redirect()
            ->route('admin.bookings.show', $extension->rental_id)
            ->with('success', 'Pembayaran perpanjangan diverifikasi dan batas waktu pengembalian baru kini aktif.');
    }

    /**
     * Tolak bukti pembayaran perpanjangan atau batalkan perpanjangan dengan pencatatan refund dana masuk (A-08, A2, A-12).
     */
    public function rejectExtensionPayment(Request $request, RentalExtension $extension): RedirectResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:500'],
            'received_amount' => ['nullable', 'numeric', 'min:0'],
            'received_at' => ['nullable', 'date'],
            'cancel_extension' => ['nullable', 'boolean'],
        ]);

        $cancelExtension = (bool) ($validated['cancel_extension'] ?? false);
        $receivedAmount = isset($validated['received_amount']) && $validated['received_amount'] !== ''
            ? (float) $validated['received_amount']
            : 0.0;
        $receivedAt = isset($validated['received_at']) && trim((string) $validated['received_at']) !== ''
            ? CarbonImmutable::parse((string) $validated['received_at'], RentalScheduleService::TIMEZONE)
            : null;

        $this->extensionService->rejectExtensionPayment(
            $extension,
            $request->user(),
            $validated['rejection_reason'],
            $receivedAmount,
            $cancelExtension,
            $receivedAt,
        );

        $message = $cancelExtension
            ? 'Perpanjangan dibatalkan dan rekonsiliasi dana masuk telah dicatat.'
            : 'Bukti pembayaran perpanjangan ditolak dengan alasan tercatat.';

        return redirect()
            ->route('admin.bookings.show', $extension->rental_id)
            ->with('success', $message);
    }

    /**
     * Catat realisasi pengembalian dana perpanjangan yang batal diaktifkan setelah transfer diterima (A2, SYS-12).
     */
    public function realizeExtensionRefund(Request $request, RentalExtension $extension): RedirectResponse
    {
        $validated = $request->validate([
            'transfer_amount' => ['required', 'numeric', 'min:1'],
            'transferred_at' => ['required', 'date'],
            'refund_reference' => ['required', 'string', 'max:100'],
            'refund_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->extensionService->realizeExtensionRefund(
            $extension,
            $request->user(),
            (float) $validated['transfer_amount'],
            CarbonImmutable::parse((string) $validated['transferred_at'], 'Asia/Jakarta'),
            (string) $validated['refund_reference'],
            $validated['refund_notes'] ?? null,
        );

        return redirect()
            ->route('admin.bookings.show', $extension->rental_id)
            ->with('success', 'Realisasi pengembalian dana perpanjangan berhasil dicatat.');
    }
}
