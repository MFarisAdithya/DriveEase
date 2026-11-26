<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rental;
use App\Services\RentalScheduleService;
use App\Services\RentalSettlementService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SettlementController extends Controller
{
    public function __construct(
        private readonly RentalSettlementService $settlementService,
    ) {}

    /**
     * Periksa denda otomatis dan tetapkan biaya BBM serta kerusakan oleh admin (A-09, SYS-11).
     */
    public function assessCharges(Request $request, Rental $rental): RedirectResponse
    {
        $validated = $request->validate([
            'fuel_fee_amount' => ['required', 'numeric', 'min:0'],
            'fuel_fee_reason' => ['nullable', 'string', 'max:1000'],
            'damage_fee_amount' => ['required', 'numeric', 'min:0'],
            'damage_fee_reason' => ['nullable', 'string', 'max:1000'],
            'damage_evidence' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'extensions:jpg,jpeg,png', 'max:4096'],
        ]);

        $this->settlementService->assessAdditionalCharges(
            rental: $rental,
            admin: $request->user(),
            fuelFee: (float) $validated['fuel_fee_amount'],
            fuelReason: $validated['fuel_fee_reason'] ?? null,
            damageFee: (float) $validated['damage_fee_amount'],
            damageReason: $validated['damage_fee_reason'] ?? null,
            damageEvidence: $request->file('damage_evidence'),
        );

        return redirect()
            ->route('admin.bookings.show', $rental)
            ->with('success', 'Pemeriksaan biaya tambahan pascapengembalian berhasil disimpan.');
    }

    /**
     * Unggah bukti pembayaran tagihan tambahan secara langsung dari portal admin (A-09, A-15).
     */
    public function storeChargeProof(Request $request, Rental $rental): RedirectResponse
    {
        $validated = $request->validate([
            'additional_charge_proof' => ['required', 'image', 'mimes:jpg,jpeg,png', 'extensions:jpg,jpeg,png', 'max:4096'],
        ]);

        $this->settlementService->submitAdditionalChargePayment(
            $rental,
            $request->user(),
            $validated['additional_charge_proof']
        );

        return redirect()
            ->route('admin.bookings.show', $rental)
            ->with('success', 'Bukti pembayaran tagihan tambahan berhasil diunggah dan siap diverifikasi.');
    }

    /**
     * Verifikasi pelunasan biaya tambahan beserta tanggal aktual dana diterima (WIB) dan referensi pembayaran (A-09, A-12).
     */
    public function approveChargePayment(Request $request, Rental $rental): RedirectResponse
    {
        $validated = $request->validate([
            'verified_amount' => ['required', 'numeric', 'min:1'],
            'payment_reference' => ['required', 'string', 'min:3', 'max:100'],
            'received_at' => ['required', 'date'],
        ], [
            'received_at.required' => 'Tanggal aktual dana tagihan tambahan diterima (WIB) wajib diisi.',
        ]);

        $this->settlementService->approveAdditionalChargePayment(
            $rental,
            $request->user(),
            (float) $validated['verified_amount'],
            (string) $validated['payment_reference'],
            CarbonImmutable::parse((string) $validated['received_at'], RentalScheduleService::TIMEZONE),
        );

        return redirect()
            ->route('admin.bookings.show', $rental)
            ->with('success', 'Pelunasan tagihan tambahan berhasil diverifikasi.');
    }

    /**
     * Tolak bukti pembayaran tagihan tambahan dengan alasan wajib dan catat dana masuk parsial beserta tanggal terimanya (A-09, A-12).
     */
    public function rejectChargePayment(Request $request, Rental $rental): RedirectResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5', 'max:500'],
            'received_amount' => ['nullable', 'numeric', 'min:0'],
            'received_at' => ['nullable', 'date'],
        ]);

        $receivedAmount = isset($validated['received_amount']) && $validated['received_amount'] !== ''
            ? (float) $validated['received_amount']
            : 0.0;
        $receivedAt = isset($validated['received_at']) && trim((string) $validated['received_at']) !== ''
            ? CarbonImmutable::parse((string) $validated['received_at'], RentalScheduleService::TIMEZONE)
            : null;

        $this->settlementService->rejectAdditionalChargePayment(
            $rental,
            $request->user(),
            (string) $validated['rejection_reason'],
            $receivedAmount,
            $receivedAt,
        );

        return redirect()
            ->route('admin.bookings.show', $rental)
            ->with('success', 'Bukti pembayaran tagihan tambahan ditolak dengan alasan tercatat.');
    }

    /**
     * Tutup transaksi sewa menjadi selesai setelah inspeksi dan kewajiban tuntas (SYS-12).
     */
    public function complete(Request $request, Rental $rental): RedirectResponse
    {
        $validated = $request->validate([
            'completion_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->settlementService->completeRental($rental, $request->user(), $validated['completion_notes'] ?? null);

        return redirect()
            ->route('admin.bookings.show', $rental)
            ->with('success', 'Transaksi penyewaan resmi dinyatakan selesai.');
    }
}
