<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Rental;
use App\Services\RentalSettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdditionalChargeController extends Controller
{
    public function __construct(
        private readonly RentalSettlementService $settlementService,
    ) {}

    /**
     * Kirim bukti pembayaran tagihan tambahan (denda, BBM, atau kerusakan) oleh pelanggan pemilik pesanan (C-10).
     */
    public function storeProof(Request $request, Rental $rental): RedirectResponse
    {
        abort_unless($rental->user_id === $request->user()->id, 403, 'Anda tidak berhak mengirim bukti tagihan tambahan untuk pesanan ini.');

        $validated = $request->validate([
            'additional_charge_proof' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $this->settlementService->submitAdditionalChargePayment(
            $rental,
            $request->user(),
            $validated['additional_charge_proof']
        );

        return redirect()
            ->route('customer.bookings.show', $rental)
            ->with('success', 'Bukti pembayaran tagihan tambahan berhasil dikirim dan menunggu verifikasi admin.');
    }
}
