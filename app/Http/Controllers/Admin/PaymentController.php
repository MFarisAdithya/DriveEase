<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rental;
use App\Models\RentalPayment;
use App\Services\RentalPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class PaymentController extends Controller
{
    public function __construct(
        private readonly RentalPaymentService $paymentService,
    ) {}

    /**
     * Upload transfer proof on behalf of a walk-in booking without bypassing verification (A-15).
     */
    public function storeWalkInProof(Request $request, Rental $rental): RedirectResponse
    {
        $validated = $request->validate([
            'payment_proof' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'claimed_amount' => ['nullable', 'numeric', 'min:1'],
        ]);

        try {
            $this->paymentService->submitProof(
                rental: $rental,
                actor: $request->user(),
                proofFile: $request->file('payment_proof'),
                claimedAmount: isset($validated['claimed_amount']) ? (float) $validated['claimed_amount'] : null,
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'Bukti pembayaran belum dapat disimpan. Silakan coba kembali.');
        }

        return redirect()->route('admin.bookings.show', $rental)
            ->with('success', 'Bukti transfer pesanan berhasil diunggah dan siap diverifikasi.');
    }

    /**
     * Approve a submitted payment proof and confirm the booking (A-07).
     */
    public function approve(Request $request, RentalPayment $payment): RedirectResponse
    {
        $validated = $request->validate([
            'verified_amount' => ['required', 'numeric', 'min:1'],
        ]);

        $rental = $this->paymentService->approvePayment(
            payment: $payment,
            admin: $request->user(),
            verifiedAmount: (float) $validated['verified_amount'],
        );

        return redirect()->route('admin.bookings.show', $rental)
            ->with('success', "Pembayaran pesanan {$rental->rental_code} disetujui dan status pesanan dikonfirmasi.");
    }

    /**
     * Reject a submitted payment proof with mandatory reason and optional partial received amount (A-07).
     */
    public function reject(Request $request, RentalPayment $payment): RedirectResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5', 'max:1000'],
            'received_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $rental = $this->paymentService->rejectPayment(
            payment: $payment,
            admin: $request->user(),
            reason: (string) $validated['rejection_reason'],
            receivedAmount: isset($validated['received_amount']) ? (float) $validated['received_amount'] : 0.0,
        );

        return redirect()->route('admin.bookings.show', $rental)
            ->with('success', "Bukti pembayaran pesanan {$rental->rental_code} ditolak dengan alasan.");
    }
}
