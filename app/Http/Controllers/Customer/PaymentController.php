<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Rental;
use App\Services\RentalPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class PaymentController extends Controller
{
    /**
     * Upload a payment transfer proof for the customer's own booking (C-06).
     */
    public function store(Request $request, Rental $rental, RentalPaymentService $paymentService): RedirectResponse
    {
        // Tolak pelanggan lain yang mencoba mengirim bukti ke pesanan yang bukan miliknya.
        abort_unless($rental->user_id === $request->user()->id, 403, 'Anda tidak berhak mengunggah pembayaran untuk pesanan ini.');

        $request->validate([
            'payment_proof' => ['required', 'image', 'mimes:jpg,jpeg,png', 'extensions:jpg,jpeg,png', 'max:2048'],
        ]);

        try {
            $paymentService->submitProof(
                rental: $rental,
                actor: $request->user(),
                proofFile: $request->file('payment_proof'),
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'Bukti pembayaran belum dapat disimpan. Silakan coba kembali.');
        }

        return redirect()->route('customer.bookings.show', $rental)
            ->with('success', 'Bukti pembayaran berhasil dikirim dan sedang menunggu verifikasi admin.');
    }
}
