<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Models\Rental;
use App\Services\RentalScheduleService;
use App\Services\VerificationQueue;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function admin(RentalScheduleService $scheduleService): View
    {
        $scheduleService->releaseExpiredHolds();
        $verificationCounts = app(VerificationQueue::class)->counts();
        $stats = [
            'total' => Car::activeInventory()->count(),
            'available' => Car::activeInventory()->where('status', 'available')->count(),
            'rented' => Car::activeInventory()->where('status', 'rented')->count(),
            'maintenance' => Car::activeInventory()->where('status', 'maintenance')->count(),
            'pending_verifications' => $verificationCounts['ready'],
            'incomplete_verifications' => $verificationCounts['incomplete'],
            'active_bookings' => Rental::holdingSchedule()->count(),
        ];

        $recentCars = Car::activeInventory()->with('carType')->latest()->take(5)->get();

        return view('dashboard.admin', compact('stats', 'recentCars'));
    }

    public function staff(): View
    {
        $totalCars = Car::activeInventory()->count();
        $availableCars = Car::activeInventory()->where('status', 'available')->count();
        $cars = Car::activeInventory()->with('carType')->latest()->get();

        return view('dashboard.staff', compact('totalCars', 'availableCars', 'cars'));
    }

    public function customer(RentalScheduleService $scheduleService): View
    {
        $scheduleService->releaseExpiredHolds();
        $user = auth()->user();
        $availableCars = Car::activeInventory()->with('carType')
            ->where('status', 'available')
            ->orderBy('price_per_day', 'asc')
            ->get();
        $recentBookings = $user->rentals()->with('car.carType')->latest()->take(5)->get();

        return view('dashboard.customer', compact('availableCars', 'user', 'recentBookings'));
    }
}
