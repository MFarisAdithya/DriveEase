<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Models\CarType;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicCarController extends Controller
{
    /**
     * Display a listing of the vehicles for public / customers.
     */
    public function index(Request $request): View
    {
        $query = Car::with('carType');

        // Search by name or brand
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('brand', 'ilike', "%{$search}%");
            });
        }

        // Filter by category
        if ($request->filled('car_type_id') && is_numeric($request->car_type_id)) {
            $query->where('car_type_id', (int) $request->car_type_id);
        }

        // Filter by transmission
        if ($request->filled('transmission') && in_array($request->transmission, ['manual', 'automatic'], true)) {
            $query->where('transmission', $request->transmission);
        }

        // Order by availability first, then newest
        $cars = $query->orderByRaw("CASE WHEN status = 'available' THEN 1 ELSE 2 END")
            ->latest()
            ->paginate(9)
            ->withQueryString();

        $carTypes = CarType::orderBy('name')->get();

        return view('cars.index', compact('cars', 'carTypes'));
    }

    /**
     * Display the specified vehicle details.
     */
    public function show(Car $car): View
    {
        $car->load('carType');

        // Other recommendations in same category
        $relatedCars = Car::with('carType')
            ->where('car_type_id', $car->car_type_id)
            ->where('id', '!=', $car->id)
            ->take(3)
            ->get();

        return view('cars.show', compact('car', 'relatedCars'));
    }
}
