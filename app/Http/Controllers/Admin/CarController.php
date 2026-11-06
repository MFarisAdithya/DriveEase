<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\CarType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CarController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $query = Car::with('carType');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('car_type_id')) {
            $query->where('car_type_id', $request->car_type_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('brand', 'ilike', "%{$search}%")
                    ->orWhere('plate_number', 'ilike', "%{$search}%");
            });
        }

        $cars = $query->latest()->paginate(10)->withQueryString();
        $carTypes = CarType::all();

        return view('admin.cars.index', compact('cars', 'carTypes'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $carTypes = CarType::orderBy('name')->get();

        return view('admin.cars.create', compact('carTypes'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $currentYear = (int) date('Y');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'car_type_id' => ['required', 'exists:car_types,id'],
            'brand' => ['required', 'string', 'max:50'],
            'plate_number' => ['required', 'string', 'max:20', 'unique:cars,plate_number'],
            'model_year' => ['required', 'integer', 'min:1990', 'max:' . ($currentYear + 1)],
            'transmission' => ['required', 'in:manual,automatic'],
            'capacity' => ['required', 'integer', 'min:1', 'max:50'],
            'price_per_day' => ['required', 'numeric', 'min:1'],
            'status' => ['required', 'in:available,rented,maintenance'],
            'description' => ['nullable', 'string'],
        ], [
            'name.required' => 'Nama kendaraan wajib diisi.',
            'car_type_id.required' => 'Kategori kendaraan wajib dipilih.',
            'car_type_id.exists' => 'Kategori kendaraan tidak valid.',
            'brand.required' => 'Merek kendaraan wajib diisi.',
            'plate_number.required' => 'Nomor plat polisi wajib diisi.',
            'plate_number.unique' => 'Nomor plat polisi sudah terdaftar.',
            'model_year.required' => 'Tahun pembuatan wajib diisi.',
            'model_year.min' => 'Tahun pembuatan minimal tahun 1990.',
            'model_year.max' => 'Tahun pembuatan tidak valid.',
            'transmission.required' => 'Transmisi wajib dipilih.',
            'transmission.in' => 'Pilihan transmisi tidak valid.',
            'capacity.required' => 'Kapasitas penumpang wajib diisi.',
            'capacity.min' => 'Kapasitas minimal 1 penumpang.',
            'price_per_day.required' => 'Tarif sewa per hari wajib diisi.',
            'price_per_day.min' => 'Tarif sewa harus lebih dari 0.',
            'status.required' => 'Status kendaraan wajib dipilih.',
            'status.in' => 'Status kendaraan harus salah satu dari: available, rented, maintenance.',
        ]);

        Car::create($validated);

        return redirect()->route('admin.cars.index')
            ->with('success', 'Kendaraan "' . $validated['name'] . '" berhasil ditambahkan.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Car $car): View
    {
        $car->load('carType');

        return view('admin.cars.show', compact('car'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Car $car): View
    {
        $carTypes = CarType::orderBy('name')->get();

        return view('admin.cars.edit', compact('car', 'carTypes'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Car $car): RedirectResponse
    {
        $currentYear = (int) date('Y');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'car_type_id' => ['required', 'exists:car_types,id'],
            'brand' => ['required', 'string', 'max:50'],
            'plate_number' => [
                'required',
                'string',
                'max:20',
                Rule::unique('cars', 'plate_number')->ignore($car->id),
            ],
            'model_year' => ['required', 'integer', 'min:1990', 'max:' . ($currentYear + 1)],
            'transmission' => ['required', 'in:manual,automatic'],
            'capacity' => ['required', 'integer', 'min:1', 'max:50'],
            'price_per_day' => ['required', 'numeric', 'min:1'],
            'status' => ['required', 'in:available,rented,maintenance'],
            'description' => ['nullable', 'string'],
        ], [
            'name.required' => 'Nama kendaraan wajib diisi.',
            'car_type_id.required' => 'Kategori kendaraan wajib dipilih.',
            'car_type_id.exists' => 'Kategori kendaraan tidak valid.',
            'brand.required' => 'Merek kendaraan wajib diisi.',
            'plate_number.required' => 'Nomor plat polisi wajib diisi.',
            'plate_number.unique' => 'Nomor plat polisi sudah terdaftar pada armada lain.',
            'model_year.required' => 'Tahun pembuatan wajib diisi.',
            'model_year.min' => 'Tahun pembuatan minimal tahun 1990.',
            'model_year.max' => 'Tahun pembuatan tidak valid.',
            'transmission.required' => 'Transmisi wajib dipilih.',
            'transmission.in' => 'Pilihan transmisi tidak valid.',
            'capacity.required' => 'Kapasitas penumpang wajib diisi.',
            'capacity.min' => 'Kapasitas minimal 1 penumpang.',
            'price_per_day.required' => 'Tarif sewa per hari wajib diisi.',
            'price_per_day.min' => 'Tarif sewa harus lebih dari 0.',
            'status.required' => 'Status kendaraan wajib dipilih.',
            'status.in' => 'Status kendaraan harus salah satu dari: available, rented, maintenance.',
        ]);

        $car->update($validated);

        return redirect()->route('admin.cars.index')
            ->with('success', 'Data kendaraan "' . $car->name . '" berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Car $car): RedirectResponse
    {
        $name = $car->name;
        $car->delete();

        return redirect()->route('admin.cars.index')
            ->with('success', 'Kendaraan "' . $name . '" berhasil dihapus dari sistem.');
    }
}
