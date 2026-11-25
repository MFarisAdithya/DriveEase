<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rental;
use App\Services\RentalExtensionService;
use App\Services\RentalReportService;
use App\Services\RentalScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(
        private readonly RentalScheduleService $scheduleService,
        private readonly RentalExtensionService $extensionService,
        private readonly RentalReportService $reportService,
    ) {}

    /**
     * Tampilkan rekap transaksi, penerimaan pembayaran, refund, dan penerimaan bersih berdasarkan periode WIB (A-12).
     */
    public function index(Request $request): View
    {
        $nowWib = CarbonImmutable::now(RentalScheduleService::TIMEZONE);
        $this->scheduleService->releaseExpiredHolds(null, $nowWib);
        $this->extensionService->releaseExpiredExtensions(null, $nowWib);

        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'rental_status' => ['nullable', 'in:pending,confirmed,picked_up,returned,completed,cancelled,expired'],
            'booking_channel' => ['nullable', 'in:online,walk_in'],
        ], [
            'date_to.after_or_equal' => 'Tanggal akhir laporan tidak boleh lebih awal dari tanggal mulai.',
        ]);

        // Jika admin belum memilih rentang tanggal, tampilkan laporan bulan berjalan di zona waktu WIB.
        $fromWib = isset($validated['date_from'])
            ? CarbonImmutable::parse((string) $validated['date_from'], RentalScheduleService::TIMEZONE)->startOfDay()
            : $nowWib->startOfMonth();

        $toWib = isset($validated['date_to'])
            ? CarbonImmutable::parse((string) $validated['date_to'], RentalScheduleService::TIMEZONE)->endOfDay()
            : $nowWib->endOfMonth();

        $rentalStatus = $validated['rental_status'] ?? null;
        $bookingChannel = $validated['booking_channel'] ?? null;

        $report = $this->reportService->buildPeriodReport($fromWib, $toWib, $rentalStatus, $bookingChannel);

        return view('admin.reports.index', [
            'rows' => $report['rows'],
            'summary' => $report['summary'],
            'dateFrom' => $fromWib->toDateString(),
            'dateTo' => $toWib->toDateString(),
            'rentalStatus' => (string) ($rentalStatus ?? ''),
            'bookingChannel' => (string) ($bookingChannel ?? ''),
            'statusLabels' => [
                Rental::STATUS_PENDING => 'Menunggu Pembayaran',
                Rental::STATUS_CONFIRMED => 'Terkonfirmasi',
                Rental::STATUS_PICKED_UP => 'Sedang Berjalan',
                Rental::STATUS_RETURNED => 'Sudah Kembali',
                Rental::STATUS_COMPLETED => 'Selesai',
                Rental::STATUS_CANCELLED => 'Dibatalkan',
                Rental::STATUS_EXPIRED => 'Kedaluwarsa',
            ],
        ]);
    }
}
