<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CarType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CarTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $query = CarType::withCount('cars');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('name', 'ilike', "%{$search}%")
                ->orWhere('description', 'ilike', "%{$search}%");
        }

        $carTypes = $query->orderBy('name')->paginate(10)->withQueryString();

        return view('admin.car_types.index', compact('carTypes'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('admin.car_types.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', 'unique:car_types,name'],
            'description' => ['nullable', 'string', 'max:500'],
        ], [
            'name.required' => 'Nama kategori kendaraan wajib diisi.',
            'name.unique' => 'Nama kategori kendaraan sudah terdaftar.',
            'name.max' => 'Nama kategori maksimal 50 karakter.',
            'description.max' => 'Deskripsi maksimal 500 karakter.',
        ]);

        CarType::create($validated);

        return redirect()->route('admin.car-types.index')
            ->with('success', 'Kategori kendaraan "' . $validated['name'] . '" berhasil ditambahkan.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(CarType $carType): View
    {
        return view('admin.car_types.edit', compact('carType'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, CarType $carType): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('car_types', 'name')->ignore($carType->id),
            ],
            'description' => ['nullable', 'string', 'max:500'],
        ], [
            'name.required' => 'Nama kategori kendaraan wajib diisi.',
            'name.unique' => 'Nama kategori sudah digunakan oleh kategori lain.',
            'name.max' => 'Nama kategori maksimal 50 karakter.',
            'description.max' => 'Deskripsi maksimal 500 karakter.',
        ]);

        $carType->update($validated);

        return redirect()->route('admin.car-types.index')
            ->with('success', 'Kategori kendaraan "' . $carType->name . '" berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CarType $carType): RedirectResponse
    {
        $carCount = $carType->cars()->count();

        if ($carCount > 0) {
            return redirect()->route('admin.car-types.index')
                ->with('error', 'Kategori "' . $carType->name . '" tidak dapat dihapus karena masih digunakan oleh ' . $carCount . ' armada kendaraan.');
        }

        $name = $carType->name;
        $carType->delete();

        return redirect()->route('admin.car-types.index')
            ->with('success', 'Kategori kendaraan "' . $name . '" berhasil dihapus.');
    }
}
