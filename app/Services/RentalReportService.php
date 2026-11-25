<?php

namespace App\Services;

use App\Models\Rental;
use App\Models\RentalChangeRequest;
use App\Models\RentalExtension;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class RentalReportService
{
    /**
     * Hitung ringkasan keuangan satu transaksi tanpa penghitungan ganda atas perpanjangan aktif (A-12).
     *
     * @return array{
     *     gross_receipts: float,
     *     processed_refunds: float,
     *     pending_refunds: float,
     *     net_receipts: float
     * }
     */
    public function summarizeRentalFinancials(Rental $rental): array
    {
        $rental->loadMissing('extensions');

        // Perpanjangan berstatus completed sudah diakumulasikan ke $rental->paid_amount saat diaktifkan.
        // Karena itu, hanya dana masuk pada perpanjangan non-completed (misalnya parsial lalu ditolak/kedaluwarsa)
        // yang ditambahkan agar total penerimaan kotor tidak terhitung dua kali.
        $nonCompletedExtensionPaid = (float) $rental->extensions
            ->where('status', '!=', RentalExtension::STATUS_COMPLETED)
            ->sum('paid_amount');

        $grossReceipts = round(
            (float) $rental->paid_amount
            + (float) $rental->additional_charge_paid_amount
            + $nonCompletedExtensionPaid,
            2
        );

        // max() menjaga akurasi kompatibilitas mundur apabila kolom refund_realized_amount bernilai nol
        // namun refund_status lama sudah berstatus processed dengan nominal di refund_amount.
        $mainProcessedRefund = max(
            (float) $rental->refund_realized_amount,
            $rental->refund_status === Rental::REFUND_PROCESSED ? (float) $rental->refund_amount : 0.0
        );
        $extensionProcessedRefund = (float) $rental->extensions
            ->where('refund_status', RentalExtension::REFUND_PROCESSED)
            ->sum('refund_amount');
        $processedRefunds = round($mainProcessedRefund + $extensionProcessedRefund, 2);

        $extensionPendingRefund = (float) $rental->extensions
            ->where('refund_status', RentalExtension::REFUND_PENDING)
            ->sum('refund_amount');
        $pendingRefunds = round($rental->pendingRefundBalance() + $extensionPendingRefund, 2);

        return [
            'gross_receipts' => $grossReceipts,
            'processed_refunds' => $processedRefunds,
            'pending_refunds' => $pendingRefunds,
            'net_receipts' => round($grossReceipts - $processedRefunds, 2),
        ];
    }

    /**
     * Bangun rekap transaksi, penerimaan kotor, refund, dan penerimaan bersih pada periode WIB tertentu (A-12).
     *
     * @return array{
     *     rows: Collection<int, array{rental: Rental, gross_receipts: float, processed_refunds: float, pending_refunds: float, net_receipts: float}>,
     *     summary: array{
     *         total_transactions: int,
     *         completed_transactions: int,
     *         cancelled_or_expired_transactions: int,
     *         gross_receipts: float,
     *         processed_refunds: float,
     *         pending_refunds: float,
     *         net_receipts: float
     *     }
     * }
     */
    public function buildPeriodReport(
        CarbonInterface $fromWib,
        CarbonInterface $toWib,
        ?string $rentalStatus = null,
        ?string $bookingChannel = null,
    ): array {
        $startAt = CarbonImmutable::instance($fromWib)->timezone(RentalScheduleService::TIMEZONE)->startOfDay();
        $endAt = CarbonImmutable::instance($toWib)->timezone(RentalScheduleService::TIMEZONE)->endOfDay();

        // Filter periode memakai pickup_at WIB karena seluruh durasi sewa dan jadwal transaksi diikat ke zona WIB.
        $rentals = Rental::query()
            ->with(['user', 'car', 'extensions'])
            ->whereBetween('pickup_at', [$startAt->format('Y-m-d H:i:s'), $endAt->format('Y-m-d H:i:s')])
            ->when($rentalStatus !== null && $rentalStatus !== '', fn ($q) => $q->where('rental_status', $rentalStatus))
            ->when(
                in_array($bookingChannel, [Rental::CHANNEL_ONLINE, Rental::CHANNEL_WALK_IN], true),
                fn ($q) => $q->where('booking_channel', $bookingChannel)
            )
            ->orderByDesc('pickup_at')
            ->orderByDesc('id')
            ->get();

        $rows = $rentals->map(function (Rental $rental): array {
            $financials = $this->summarizeRentalFinancials($rental);

            return [
                'rental' => $rental,
                'gross_receipts' => $financials['gross_receipts'],
                'processed_refunds' => $financials['processed_refunds'],
                'pending_refunds' => $financials['pending_refunds'],
                'net_receipts' => $financials['net_receipts'],
            ];
        });

        // Total ringkasan dihitung langsung dari $rows agar kartu ringkasan selalu konsisten dengan baris rincian.
        $summary = [
            'total_transactions' => $rows->count(),
            'completed_transactions' => $rentals->where('rental_status', Rental::STATUS_COMPLETED)->count(),
            'cancelled_or_expired_transactions' => $rentals
                ->whereIn('rental_status', [Rental::STATUS_CANCELLED, Rental::STATUS_EXPIRED])
                ->count(),
            'gross_receipts' => round((float) $rows->sum('gross_receipts'), 2),
            'processed_refunds' => round((float) $rows->sum('processed_refunds'), 2),
            'pending_refunds' => round((float) $rows->sum('pending_refunds'), 2),
            'net_receipts' => round((float) $rows->sum('net_receipts'), 2),
        ];

        return [
            'rows' => $rows,
            'summary' => $summary,
        ];
    }

    /**
     * Kumpulkan metrik antrean kerja dan rekap keuangan bulan berjalan untuk dasbor admin dari database aktual (A-01).
     *
     * @return array{
     *     pending_payments: int,
     *     pending_refunds_count: int,
     *     pending_extensions: int,
     *     pending_changes: int,
     *     month_summary: array{
     *         total_transactions: int,
     *         completed_transactions: int,
     *         cancelled_or_expired_transactions: int,
     *         gross_receipts: float,
     *         processed_refunds: float,
     *         pending_refunds: float,
     *         net_receipts: float
     *     },
     *     actionable_bookings: Collection<int, Rental>
     * }
     */
    public function adminDashboardMetrics(?CarbonInterface $nowWib = null): array
    {
        $reference = $nowWib
            ? CarbonImmutable::instance($nowWib)->timezone(RentalScheduleService::TIMEZONE)
            : CarbonImmutable::now(RentalScheduleService::TIMEZONE);

        $pendingMainPayments = Rental::query()
            ->where('rental_status', Rental::STATUS_PENDING)
            ->where('payment_status', Rental::PAYMENT_PENDING_APPROVAL)
            ->count();

        $pendingExtensionPayments = RentalExtension::query()
            ->where('status', RentalExtension::STATUS_PAYMENT_SUBMITTED)
            ->count();

        $pendingAdditionalPayments = Rental::query()
            ->where('rental_status', Rental::STATUS_RETURNED)
            ->where('additional_charge_status', Rental::ADDITIONAL_CHARGE_PENDING_APPROVAL)
            ->count();

        $pendingRefundsCount = Rental::query()->where('refund_status', Rental::REFUND_PENDING)->count()
            + RentalExtension::query()->where('refund_status', RentalExtension::REFUND_PENDING)->count();

        $pendingExtensions = RentalExtension::query()
            ->whereIn('status', [
                RentalExtension::STATUS_PENDING_APPROVAL,
                RentalExtension::STATUS_AWAITING_PAYMENT,
                RentalExtension::STATUS_PAYMENT_SUBMITTED,
            ])
            ->count();

        $pendingChanges = RentalChangeRequest::query()
            ->whereIn('status', [
                RentalChangeRequest::STATUS_PENDING_ADMIN,
                RentalChangeRequest::STATUS_PENDING_CUSTOMER,
            ])
            ->count();

        $monthReport = $this->buildPeriodReport($reference->startOfMonth(), $reference->endOfMonth());

        $actionableBookings = Rental::query()
            ->with(['user', 'car', 'extensions', 'changeRequests'])
            ->where(function ($query): void {
                $query->where(function ($pq): void {
                    $pq->where('rental_status', Rental::STATUS_PENDING)
                        ->where('payment_status', Rental::PAYMENT_PENDING_APPROVAL);
                })
                    ->orWhere('refund_status', Rental::REFUND_PENDING)
                    ->orWhere('rental_status', Rental::STATUS_RETURNED)
                    ->orWhere(function ($cq): void {
                        $cq->whereIn('rental_status', [Rental::STATUS_PENDING, Rental::STATUS_CONFIRMED])
                            ->whereNotNull('cancellation_requested_at');
                    })
                    ->orWhereHas('changeRequests', function ($crq): void {
                        $crq->where('status', RentalChangeRequest::STATUS_PENDING_ADMIN);
                    })
                    ->orWhereHas('extensions', function ($eq): void {
                        $eq->whereIn('status', [
                            RentalExtension::STATUS_PENDING_APPROVAL,
                            RentalExtension::STATUS_PAYMENT_SUBMITTED,
                        ])->orWhere('refund_status', RentalExtension::REFUND_PENDING);
                    });
            })
            ->orderBy('pickup_at')
            ->take(6)
            ->get();

        return [
            'pending_payments' => $pendingMainPayments + $pendingExtensionPayments + $pendingAdditionalPayments,
            'pending_refunds_count' => $pendingRefundsCount,
            'pending_extensions' => $pendingExtensions,
            'pending_changes' => $pendingChanges,
            'month_summary' => $monthReport['summary'],
            'actionable_bookings' => $actionableBookings,
        ];
    }

    /**
     * Hitung ringkasan status transaksi serta daftar pemberitahuan tindakan untuk dasbor pelanggan (C-01).
     *
     * @return array{
     *     active_count: int,
     *     completed_count: int,
     *     total_count: int,
     *     action_needed_count: int,
     *     action_items: Collection<int, array{rental: Rental, label: string}>
     * }
     */
    public function customerDashboardSummary(User $customer, ?CarbonInterface $nowWib = null): array
    {
        $reference = $nowWib
            ? CarbonImmutable::instance($nowWib)->timezone(RentalScheduleService::TIMEZONE)
            : CarbonImmutable::now(RentalScheduleService::TIMEZONE);

        $rentals = $customer->rentals()
            ->with(['car', 'extensions', 'changeRequests'])
            ->latest()
            ->get();

        $activeStatuses = [
            Rental::STATUS_PENDING,
            Rental::STATUS_CONFIRMED,
            Rental::STATUS_PICKED_UP,
            Rental::STATUS_RETURNED,
        ];

        $actionItems = collect();

        foreach ($rentals as $rental) {
            if ($rental->canUploadPaymentProof()) {
                $actionItems->push([
                    'rental' => $rental,
                    'label' => $rental->payment_status === Rental::PAYMENT_REJECTED
                        ? 'Perbaiki bukti atau selesaikan kekurangan pembayaran'
                        : 'Unggah bukti pembayaran sebelum tenggat berakhir',
                ]);

                continue;
            }

            if ($rental->changeRequests->contains(fn (RentalChangeRequest $cr): bool => $cr->status === RentalChangeRequest::STATUS_PENDING_CUSTOMER)) {
                $actionItems->push([
                    'rental' => $rental,
                    'label' => 'Usulan perubahan jadwal atau unit dari admin menunggu persetujuan Anda',
                ]);

                continue;
            }

            $payableExtension = $rental->extensions->first(
                fn (RentalExtension $ext): bool => $ext->status === RentalExtension::STATUS_AWAITING_PAYMENT && ! $ext->isPaymentExpired($reference)
            );
            if ($payableExtension !== null) {
                $actionItems->push([
                    'rental' => $rental,
                    'label' => 'Perpanjangan sewa disetujui, silakan unggah bukti pembayaran tambahan',
                ]);

                continue;
            }

            if (
                $rental->rental_status === Rental::STATUS_RETURNED
                && in_array($rental->additional_charge_status, [Rental::ADDITIONAL_CHARGE_UNPAID, Rental::ADDITIONAL_CHARGE_REJECTED], true)
            ) {
                $actionItems->push([
                    'rental' => $rental,
                    'label' => 'Tagihan tambahan pascapengembalian menunggu bukti transfer Anda',
                ]);
            }
        }

        return [
            'active_count' => $rentals->whereIn('rental_status', $activeStatuses)->count(),
            'completed_count' => $rentals->where('rental_status', Rental::STATUS_COMPLETED)->count(),
            'total_count' => $rentals->count(),
            'action_needed_count' => $actionItems->count(),
            'action_items' => $actionItems,
        ];
    }
}
