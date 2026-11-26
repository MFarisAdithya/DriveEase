<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Models\Rental;
use App\Services\RentalExtensionService;
use App\Services\RentalOperationService;
use App\Services\RentalReportService;
use App\Services\RentalScheduleService;
use App\Services\VerificationQueue;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function admin(
        RentalScheduleService $scheduleService,
        RentalExtensionService $extensionService,
        RentalOperationService $operationService,
        RentalReportService $reportService
    ): View {
        $scheduleService->releaseExpiredHolds();
        $extensionService->releaseExpiredExtensions();

        $verificationCounts = app(VerificationQueue::class)->counts();
        $impactedNextBookings = $operationService->detectImpactedNextBookings();
        $overdueTripsCount = Rental::query()
            ->where('rental_status', Rental::STATUS_PICKED_UP)
            ->where('return_at', '<', now(RentalScheduleService::TIMEZONE)->format('Y-m-d H:i:s'))
            ->count();

        $reportMetrics = $reportService->adminDashboardMetrics();
        $monthSummary = $reportMetrics['month_summary'];

        // Seluruh statistik dasbor admin dihitung langsung dari database aktual tanpa nilai statis.
        $stats = [
            'total' => Car::activeInventory()->count(),
            'available' => Car::activeInventory()->where('status', 'available')->count(),
            'rented' => Car::activeInventory()->where('status', 'rented')->count(),
            'maintenance' => Car::activeInventory()->where('status', 'maintenance')->count(),
            'pending_verifications' => $verificationCounts['ready'],
            'incomplete_verifications' => $verificationCounts['incomplete'],
            'active_bookings' => Rental::holdingSchedule()->count(),
            'overdue_trips' => $overdueTripsCount,
            'impacted_bookings' => $impactedNextBookings->count(),
            'review_followups' => Rental::query()->needsReviewFollowup()->count(),
            'pending_payments' => $reportMetrics['pending_payments'],
            'pending_refunds_count' => $reportMetrics['pending_refunds_count'],
            'pending_extensions' => $reportMetrics['pending_extensions'],
            'pending_changes' => $reportMetrics['pending_changes'],
            'month_transactions' => $monthSummary['total_transactions'],
            'month_gross_receipts' => $monthSummary['gross_receipts'],
            'month_processed_refunds' => $monthSummary['processed_refunds'],
            'month_pending_refunds' => $monthSummary['pending_refunds'],
            'month_net_receipts' => $monthSummary['net_receipts'],
        ];

        $recentCars = Car::activeInventory()->with('carType')->latest()->take(5)->get();
        $actionableBookings = $reportMetrics['actionable_bookings'];

        return view('dashboard.admin', compact(
            'stats',
            'recentCars',
            'impactedNextBookings',
            'actionableBookings',
            'monthSummary'
        ));
    }

    public function staff(
        RentalScheduleService $scheduleService,
        RentalOperationService $operationService
    ): View {
        $scheduleService->releaseExpiredHolds();

        $totalCars = Car::activeInventory()->count();
        $availableCars = Car::readyForHandover()->count();
        $cars = Car::activeInventory()->with('carType')->latest()->get();

        // Daftar pekerjaan lapangan staf: penjemputan terkonfirmasi, pengembalian aktif,
        // keterlambatan kembali, dan unit yang membutuhkan persiapan atau perawatan (S-01).
        $upcomingPickups = Rental::query()
            ->with(['user:id,name,verification_status', 'car:id,name,plate_number,status,readiness_status'])
            ->where('rental_status', Rental::STATUS_CONFIRMED)
            ->orderBy('pickup_at')
            ->take(10)
            ->get();

        $activeReturns = Rental::query()
            ->with(['user:id,name,verification_status', 'car:id,name,plate_number,status,readiness_status'])
            ->where('rental_status', Rental::STATUS_PICKED_UP)
            ->orderBy('return_at')
            ->take(10)
            ->get();

        $overdueTrips = $activeReturns->filter(fn (Rental $trip): bool => $trip->isReturnOverdue());

        $carsNeedingAttention = Car::activeInventory()
            ->whereIn('readiness_status', [Car::READINESS_NEEDS_PREPARATION, Car::READINESS_NEEDS_MAINTENANCE])
            ->with('carType')
            ->get();

        $impactedNextBookings = $operationService->detectImpactedNextBookings();

        return view('dashboard.staff', compact(
            'totalCars',
            'availableCars',
            'cars',
            'upcomingPickups',
            'activeReturns',
            'overdueTrips',
            'carsNeedingAttention',
            'impactedNextBookings'
        ));
    }

    public function customer(
        RentalScheduleService $scheduleService,
        RentalExtensionService $extensionService,
        RentalReportService $reportService
    ): View {
        $scheduleService->releaseExpiredHolds();
        $extensionService->releaseExpiredExtensions();

        $user = auth()->user();
        $availableCars = Car::activeInventory()->with('carType')
            ->where('status', 'available')
            ->orderBy('price_per_day', 'asc')
            ->get();
        $recentBookings = $user->rentals()->with('car.carType')->latest()->take(5)->get();
        $customerSummary = $reportService->customerDashboardSummary($user);

        return view('dashboard.customer', compact('availableCars', 'user', 'recentBookings', 'customerSummary'));
    }
}
