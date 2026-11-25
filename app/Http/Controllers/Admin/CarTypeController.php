<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CarType;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CarTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        // withCount('cars') menambahkan kolom cars_count untuk menampilkan jumlah armada
        // sekaligus menentukan apakah tombol hapus kategori boleh ditampilkan di view.
        $query = CarType::withCount('cars');

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%");
            });
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
        $validated = $this->validateCarType($request);

        CarType::create($validated);

        return redirect()->route('admin.car-types.index')
            ->with('success', 'Kategori kendaraan "'.$validated['name'].'" berhasil ditambahkan.');
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
        $validated = $this->validateCarType($request, $carType);

        $carType->update($validated);

        return redirect()->route('admin.car-types.index')
            ->with('success', 'Kategori kendaraan "'.$carType->name.'" berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage when no vehicles reference it.
     */
    public function destroy(CarType $carType): RedirectResponse
    {
        // DB::transaction() dan lockForUpdate() mengunci baris kategori saat memeriksa
        // jumlah relasi cars() (termasuk unit arsip karena FK cars.car_type_id bersifat RESTRICT),
        // sehingga penghapusan aman terhadap operasi bersamaan dari admin lain.
        $deletedName = DB::transaction(function () use ($carType): ?string {
            $lockedType = CarType::query()->lockForUpdate()->findOrFail($carType->id);
            if ($lockedType->cars()->count() > 0) {
                return null;
            }

            $name = $lockedType->name;
            $lockedType->delete();

            return $name;
        });

        if ($deletedName === null) {
            $carCount = $carType->cars()->count();

            return redirect()->route('admin.car-types.index')
                ->with('error', 'Kategori "'.$carType->name.'" tidak dapat dihapus karena masih digunakan oleh '.$carCount.' armada kendaraan.');
        }

        return redirect()->route('admin.car-types.index')
            ->with('success', 'Kategori kendaraan "'.$deletedName.'" berhasil dihapus.');
    }

    /**
     * Validate category fields with case-insensitive uniqueness on PostgreSQL.
     *
     * @return array<string, mixed>
     */
    private function validateCarType(Request $request, ?CarType $carType = null): array
    {
        if (is_string($request->input('name'))) {
            $request->merge(['name' => trim($request->input('name'))]);
        }

        // PostgreSQL membedakan huruf besar/kecil pada Rule::unique() standar.
        // Closure validasi tambahan memeriksa LOWER(name) = ? memakai parameter binding
        // supaya kategori seperti 'suv' ditolak bila 'SUV' sudah terdaftar.
        $duplicateMessage = $carType === null
            ? 'Nama kategori kendaraan sudah terdaftar.'
            : 'Nama kategori sudah digunakan oleh kategori lain.';

        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('car_types', 'name')->ignore($carType?->id),
                function (string $attribute, mixed $value, Closure $fail) use ($carType, $duplicateMessage): void {
                    if (! is_string($value)) {
                        return;
                    }

                    $exists = CarType::query()
                        ->whereRaw('LOWER(name) = ?', [Str::lower($value)])
                        ->when($carType !== null, fn ($q) => $q->where('id', '!=', $carType->id))
                        ->exists();

                    if ($exists) {
                        $fail($duplicateMessage);
                    }
                },
            ],
            'description' => ['nullable', 'string', 'max:500'],
        ], [
            'name.required' => 'Nama kategori kendaraan wajib diisi.',
            'name.unique' => $duplicateMessage,
            'name.max' => 'Nama kategori maksimal 50 karakter.',
            'description.max' => 'Deskripsi maksimal 500 karakter.',
        ]);
    }
}
