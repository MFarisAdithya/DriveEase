<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function admin(): View
    {
        $stats = [
            'total' => Car::count(),
            'available' => Car::where('status', 'available')->count(),
            'rented' => Car::where('status', 'rented')->count(),
            'maintenance' => Car::where('status', 'maintenance')->count(),
            'pending_verifications' => User::where('verification_status', 'pending')
                ->whereHas('role', fn($q) => $q->where('name', 'customer'))
                ->count(),
        ];

        $recentCars = Car::with('carType')->latest()->take(5)->get();

        return view('dashboard.admin', compact('stats', 'recentCars'));
    }

    public function staff(): View
    {
        $totalCars = Car::count();
        $availableCars = Car::where('status', 'available')->count();
        $cars = Car::with('carType')->latest()->get();

        return view('dashboard.staff', compact('totalCars', 'availableCars', 'cars'));
    }

    public function customer(): View
    {
        $user = auth()->user();
        $availableCars = Car::with('carType')
            ->where('status', 'available')
            ->orderBy('price_per_day', 'asc')
            ->get();

        return view('dashboard.customer', compact('availableCars', 'user'));
    }
}
