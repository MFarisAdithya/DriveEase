<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Rental;
use App\Models\RentalChangeRequest;
use App\Models\RentalExtension;
use App\Services\RentalChangeService;
use App\Services\RentalExtensionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BookingChangeController extends Controller
{
    public function __construct(
        private readonly RentalChangeService $changeService,
        private readonly RentalExtensionService $extensionService,
    ) {}

    /**
     * Ajukan perubahan jadwal penyewaan dari sisi pelanggan sebelum pengambilan (C-07).
     */
    public function storeChangeRequest(Request $request, Rental $rental): RedirectResponse
    {
        // abort_unless() memastikan pelanggan hanya dapat mengajukan perubahan untuk pesanannya sendiri.
        abort_unless($rental->user_id === $request->user()->id, 403, 'Anda tidak berhak mengubah pesanan ini.');

        $validated = $request->validate([
            'proposed_pickup_at' => ['required', 'date'],
            'proposed_total_days' => ['required', 'integer', 'min:1', 'max:365'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $this->changeService->createCustomerRequest($rental, $request->user(), $validated);

        return redirect()
            ->route('customer.bookings.show', $rental)
            ->with('success', 'Permohonan perubahan jadwal berhasil dikirim dan sedang menunggu persetujuan admin.');
    }

    /**
     * Setujui atau tolak usulan perubahan jadwal/unit yang diajukan oleh admin (C-07, A-06).
     */
    public function respondToProposal(Request $request, RentalChangeRequest $changeRequest): RedirectResponse
    {
        $changeRequest->loadMissing('rental');
        abort_unless($changeRequest->rental?->user_id === $request->user()->id, 403, 'Anda tidak berhak menanggapi usulan ini.');

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
            ? 'Usulan penyesuaian pesanan dari admin telah Anda setujui dan kini berlaku.'
            : 'Usulan penyesuaian pesanan ditolak; jadwal serta kendaraan sebelumnya tetap berlaku.';

        return redirect()
            ->route('customer.bookings.show', $changeRequest->rental_id)
            ->with('success', $message);
    }

    /**
     * Ajukan perpanjangan masa sewa oleh pelanggan pada pesanan terkonfirmasi/berjalan (C-09).
     */
    public function requestExtension(Request $request, Rental $rental): RedirectResponse
    {
        abort_unless($rental->user_id === $request->user()->id, 403, 'Anda tidak berhak memperpanjang pesanan ini.');

        $validated = $request->validate([
            'additional_days' => ['required', 'integer', 'min:1', 'max:30'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->extensionService->requestExtension(
            $rental,
            $request->user(),
            (int) $validated['additional_days'],
            $validated['notes'] ?? null
        );

        return redirect()
            ->route('customer.bookings.show', $rental)
            ->with('success', 'Pengajuan perpanjangan masa sewa telah dikirim dan menunggu persetujuan admin.');
    }

    /**
     * Unggah bukti pembayaran biaya tambahan perpanjangan sewa secara privat (C-09, NFR-01).
     */
    public function submitExtensionPayment(Request $request, RentalExtension $extension): RedirectResponse
    {
        $extension->loadMissing('rental');
        abort_unless($extension->rental?->user_id === $request->user()->id, 403, 'Anda tidak berhak mengunggah bukti pembayaran untuk pesanan ini.');

        $validated = $request->validate([
            'payment_proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $this->extensionService->submitExtensionPayment(
            $extension,
            $request->user(),
            $validated['payment_proof']
        );

        return redirect()
            ->route('customer.bookings.show', $extension->rental_id)
            ->with('success', 'Bukti pembayaran perpanjangan berhasil diunggah dan menunggu verifikasi admin.');
    }
}
