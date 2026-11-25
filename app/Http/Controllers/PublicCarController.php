<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Models\CarType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PublicCarController extends Controller
{
    /**
     * Display the public landing page showcase.
     */
    public function landing(): View
    {
        $featuredCars = Car::activeInventory()->with('carType')
            ->orderByRaw("CASE WHEN status = 'available' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->get()
            ->unique(fn (Car $car): string => strtolower(trim($car->name)))
            ->take(3)
            ->values();

        // select() membatasi data yang diteruskan ke Blade pada informasi publik.
        // Rincian rekening hanya digunakan admin dan alur pembayaran.
        $rentalInfo = DB::table('rental_settings')
            ->select('business_name', 'services', 'address', 'contact', 'operating_hours', 'terms')
            ->find(1);

        return view('landing', compact('featuredCars', 'rentalInfo'));
    }

    /**
     * Display a listing of the vehicles for public / customers.
     */
    public function index(Request $request): View
    {
        $query = Car::activeInventory()->with('carType');

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
    public function show(Request $request, Car $car): View
    {
        abort_if($car->archived_at !== null, 404);
        $car->load('carType');

        // Other recommendations in same category
        $relatedCars = Car::activeInventory()->with('carType')
            ->where('car_type_id', $car->car_type_id)
            ->where('id', '!=', $car->id)
            ->take(3)
            ->get();

        $catalogFilters = collect($request->only(['search', 'car_type_id', 'transmission', 'page']))
            ->filter(fn ($value) => is_string($value) && strlen($value) <= 255)->all();

        return view('cars.show', compact('car', 'relatedCars', 'catalogFilters'));
    }
}
