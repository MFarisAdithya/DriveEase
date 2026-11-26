<?php

namespace App\Services;

use App\Models\Rental;
use App\Models\RentalCashFlow;
use App\Models\RentalChangeRequest;
use App\Models\RentalExtension;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class RentalReportService
{
    /**
     * Bangun laporan keuangan berbasis kas (WIB) berdasarkan tanggal aktual dana diterima dan realisasi refund (A-01, A-12).
     *
     * @return array{
     *     rows: Collection<int, array{
     *         cash_flow: RentalCashFlow,
     *         rental: Rental|null,
     *         occurred_at: CarbonInterface|null,
     *         direction: string,
     *         category: string,
     *         category_label: string,
     *         inflow_amount: float,
     *         outflow_amount: float,
     *         reference: string|null
     *     }>,
     *     pending_refund_items: Collection<int, array{
     *         rental: Rental,
     *         source_label: string,
     *         amount: float,
     *         updated_at: CarbonInterface|null
     *     }>,
     *     summary: array{
     *         total_cash_entries: int,
     *         total_transactions: int,
     *         inflow_count: int,
     *         outflow_count: int,
     *         gross_receipts: float,
     *         processed_refunds: float,
     *         pending_refunds: float,
     *         pending_refunds_liability: float,
     *         net_receipts: float,
     *         unrecorded_legacy_count: int,
     *         unrecorded_legacy_amount: float
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

        $normalizedStatus = is_string($rentalStatus) && $rentalStatus !== '' ? $rentalStatus : null;
        $normalizedChannel = in_array($bookingChannel, [Rental::CHANNEL_ONLINE, Rental::CHANNEL_WALK_IN], true)
            ? $bookingChannel
            : null;

        // Kebijakan SKPL A-12 (berbasis kas):
        // Seluruh penerimaan maupun realisasi refund disaring berdasarkan rental_cash_flows.occurred_at (WIB),
        // yakni tanggal aktual dana diterima atau ditransfer kembali sesuai konfirmasi admin.
        $cashFlows = RentalCashFlow::query()
            ->with(['rental.user', 'rental.car', 'recorder', 'payment', 'extension'])
            ->whereBetween('occurred_at', [$startAt->format('Y-m-d H:i:s'), $endAt->format('Y-m-d H:i:s')])
            ->when($normalizedStatus !== null || $normalizedChannel !== null, function ($query) use ($normalizedStatus, $normalizedChannel): void {
                $query->whereHas('rental', function ($rq) use ($normalizedStatus, $normalizedChannel): void {
                    if ($normalizedStatus !== null) {
                        $rq->where('rental_status', $normalizedStatus);
                    }
                    if ($normalizedChannel !== null) {
                        $rq->where('booking_channel', $normalizedChannel);
                    }
                });
            })
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->get();

        $rows = $cashFlows->map(function (RentalCashFlow $flow): array {
            $amount = round((float) $flow->amount, 2);
            $isInflow = $flow->direction === RentalCashFlow::DIRECTION_INFLOW;

            return [
                'cash_flow' => $flow,
                'rental' => $flow->rental,
                'occurred_at' => $flow->occurred_at,
                'direction' => $flow->direction,
                'category' => $flow->category,
                'category_label' => $flow->categoryLabel(),
                'inflow_amount' => $isInflow ? $amount : 0.0,
                'outflow_amount' => ! $isInflow ? $amount : 0.0,
                'reference' => $flow->reference,
            ];
        });

        $grossReceipts = round((float) $rows->sum('inflow_amount'), 2);
        $processedRefunds = round((float) $rows->sum('outflow_amount'), 2);
        $netReceipts = round($grossReceipts - $processedRefunds, 2);

        // Refund tertunda ditampilkan terpisah sebagai kewajiban dan tidak mengurangi kas sampai benar-benar ditransfer.
        $pendingRentals = Rental::query()
            ->with(['user', 'car'])
            ->where('refund_status', Rental::REFUND_PENDING)
            ->when($normalizedStatus !== null, fn ($q) => $q->where('rental_status', $normalizedStatus))
            ->when($normalizedChannel !== null, fn ($q) => $q->where('booking_channel', $normalizedChannel))
            ->orderByDesc('updated_at')
            ->get();

        $pendingExtensions = RentalExtension::query()
            ->with(['rental.user', 'rental.car'])
            ->where('refund_status', RentalExtension::REFUND_PENDING)
            ->when($normalizedStatus !== null || $normalizedChannel !== null, function ($query) use ($normalizedStatus, $normalizedChannel): void {
                $query->whereHas('rental', function ($rq) use ($normalizedStatus, $normalizedChannel): void {
                    if ($normalizedStatus !== null) {
                        $rq->where('rental_status', $normalizedStatus);
                    }
                    if ($normalizedChannel !== null) {
                        $rq->where('booking_channel', $normalizedChannel);
                    }
                });
            })
            ->orderByDesc('updated_at')
            ->get();

        $pendingRefundItems = collect();

        foreach ($pendingRentals as $pendingRental) {
            $pendingBalance = $pendingRental->pendingRefundBalance();
            if ($pendingBalance > 0) {
                $pendingRefundItems->push([
                    'rental' => $pendingRental,
                    'source_label' => 'Refund Pesanan / Kelebihan Bayar',
                    'amount' => $pendingBalance,
                    'updated_at' => $pendingRental->updated_at,
                ]);
            }
        }

        foreach ($pendingExtensions as $pendingExtension) {
            $extAmount = max(0.0, round((float) $pendingExtension->refund_amount, 2));
            if ($extAmount > 0 && $pendingExtension->rental !== null) {
                $pendingRefundItems->push([
                    'rental' => $pendingExtension->rental,
                    'source_label' => 'Refund Perpanjangan (#'.$pendingExtension->id.')',
                    'amount' => $extAmount,
                    'updated_at' => $pendingExtension->updated_at,
                ]);
            }
        }

        $pendingLiability = round((float) $pendingRefundItems->sum('amount'), 2);

        // Deteksi transaksi historis pra-ledger yang belum memiliki catatan rental_cash_flows tanpa mengarang tanggalnya.
        $legacyRentals = Rental::query()
            ->with('extensions')
            ->whereDoesntHave('cashFlows')
            ->where(function ($query): void {
                $query->where('paid_amount', '>', 0)
                    ->orWhere('additional_charge_paid_amount', '>', 0)
                    ->orWhere('refund_realized_amount', '>', 0);
            })
            ->when($normalizedStatus !== null, fn ($q) => $q->where('rental_status', $normalizedStatus))
            ->when($normalizedChannel !== null, fn ($q) => $q->where('booking_channel', $normalizedChannel))
            ->get();

        $unrecordedLegacyAmount = round((float) $legacyRentals->sum(function (Rental $legacy): float {
            $nonCompletedExtPaid = (float) $legacy->extensions
                ->where('status', '!=', RentalExtension::STATUS_COMPLETED)
                ->sum('paid_amount');

            return (float) $legacy->paid_amount + (float) $legacy->additional_charge_paid_amount + $nonCompletedExtPaid;
        }), 2);

        return [
            'rows' => $rows,
            'pending_refund_items' => $pendingRefundItems,
            'summary' => [
                'total_cash_entries' => $rows->count(),
                'total_transactions' => $cashFlows->pluck('rental_id')->unique()->count(),
                'inflow_count' => $cashFlows->where('direction', RentalCashFlow::DIRECTION_INFLOW)->count(),
                'outflow_count' => $cashFlows->where('direction', RentalCashFlow::DIRECTION_OUTFLOW)->count(),
                'gross_receipts' => $grossReceipts,
                'processed_refunds' => $processedRefunds,
                'pending_refunds' => $pendingLiability,
                'pending_refunds_liability' => $pendingLiability,
                'net_receipts' => $netReceipts,
                'unrecorded_legacy_count' => $legacyRentals->count(),
                'unrecorded_legacy_amount' => $unrecordedLegacyAmount,
            ],
        ];
    }

    /**
     * Kumpulkan metrik antrean kerja dan rekap kas bulan berjalan untuk dasbor admin dari database aktual (A-01, A-12).
     *
     * @return array{
     *     pending_payments: int,
     *     pending_refunds_count: int,
     *     pending_extensions: int,
     *     pending_changes: int,
     *     month_summary: array{
     *         total_cash_entries: int,
     *         total_transactions: int,
     *         inflow_count: int,
     *         outflow_count: int,
     *         gross_receipts: float,
     *         processed_refunds: float,
     *         pending_refunds: float,
     *         pending_refunds_liability: float,
     *         net_receipts: float,
     *         unrecorded_legacy_count: int,
     *         unrecorded_legacy_amount: float
     *     },
     *     actionable_bookings: Collection<int, Rental>
     * }
     */
    public function adminDashboardMetrics(?CarbonInterface $nowWib = null): array
    {
        $reference = $nowWib
            ? CarbonImmutable::instance($nowWib)->timezone(RentalScheduleService::TIMEZONE)
            : CarbonImmutable::now(RentalScheduleService::TIMEZONE);

        // Bukti pada pesanan terminal masih perlu diperiksa sebelum refund.
        $reviewableStatuses = [Rental::STATUS_PENDING, Rental::STATUS_CANCELLED, Rental::STATUS_EXPIRED];
        $pendingMainPayments = Rental::query()
            ->whereIn('rental_status', $reviewableStatuses)
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
            ->where(function ($query) use ($reviewableStatuses): void {
                $query->where(function ($pq) use ($reviewableStatuses): void {
                    $pq->whereIn('rental_status', $reviewableStatuses)
                        ->where('payment_status', Rental::PAYMENT_PENDING_APPROVAL);
                })
                    ->orWhere(fn ($review) => $review->where('rental_status', Rental::STATUS_PENDING)->whereNotNull('review_followup_at'))
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
