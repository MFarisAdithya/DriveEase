@extends('layouts.admin')

@section('title', 'Ubah Data Kendaraan - DriveEase')
@section('page_title', 'Ubah Data Armada')

@section('content')
<div class="max-w-4xl">
    <div class="mb-6 flex items-center justify-between">
        <a href="{{ route('admin.cars.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-slate-800 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Kembali ke Daftar Armada
        </a>
        <a href="{{ route('admin.cars.show', $car) }}" class="text-xs font-semibold text-teal-700 hover:underline">
            Lihat Detail Armada &rarr;
        </a>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-8">
        <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-6">
            <div>
                <h2 class="text-base font-bold text-slate-900">Perbarui Informasi Armada</h2>
                <p class="text-xs text-slate-500 mt-0.5">Mengubah data untuk {{ $car->name }} ({{ $car->plate_number }})</p>
            </div>
            <span class="font-mono text-xs font-bold px-2.5 py-1 bg-slate-100 rounded text-slate-700 border border-slate-200">
                ID #{{ $car->id }}
            </span>
        </div>

        <form action="{{ route('admin.cars.update', $car) }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')

            <!-- Grid 1: Nama & Merek -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Name -->
                <div>
                    <label for="name" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                        Nama Model Kendaraan <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" 
                           id="name" 
                           name="name" 
                           value="{{ old('name', $car->name) }}" 
                           required
                           class="w-full px-3.5 py-2.5 rounded-lg border {{ $errors->has('name') ? 'border-rose-400 focus:ring-rose-200' : 'border-slate-300 focus:border-teal-600 focus:ring-teal-100' }} text-sm focus:outline-none focus:ring-2 transition-colors">
                    @error('name')
                        <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Brand -->
                <div>
                    <label for="brand" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                        Merek Pabrikan <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" 
                           id="brand" 
                           name="brand" 
                           value="{{ old('brand', $car->brand) }}" 
                           required
                           class="w-full px-3.5 py-2.5 rounded-lg border {{ $errors->has('brand') ? 'border-rose-400 focus:ring-rose-200' : 'border-slate-300 focus:border-teal-600 focus:ring-teal-100' }} text-sm focus:outline-none focus:ring-2 transition-colors">
                    @error('brand')
                        <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Grid 2: Kategori & Plat Nomor -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Car Type -->
                <div>
                    <label for="car_type_id" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                        Kategori Kendaraan <span class="text-rose-500">*</span>
                    </label>
                    <select id="car_type_id" 
                            name="car_type_id" 
                            required
                            class="w-full px-3.5 py-2.5 rounded-lg border {{ $errors->has('car_type_id') ? 'border-rose-400 focus:ring-rose-200' : 'border-slate-300 focus:border-teal-600 focus:ring-teal-100' }} text-sm focus:outline-none focus:ring-2 transition-colors">
                        @foreach ($carTypes as $type)
                            <option value="{{ $type->id }}" {{ old('car_type_id', $car->car_type_id) == $type->id ? 'selected' : '' }}>
                                {{ $type->name }} ({{ $type->description }})
                            </option>
                        @endforeach
                    </select>
                    @error('car_type_id')
                        <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Plate Number -->
                <div>
                    <label for="plate_number" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                        Nomor Plat Polisi <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" 
                           id="plate_number" 
                           name="plate_number" 
                           value="{{ old('plate_number', $car->plate_number) }}" 
                           required
                           class="w-full font-mono uppercase px-3.5 py-2.5 rounded-lg border {{ $errors->has('plate_number') ? 'border-rose-400 focus:ring-rose-200' : 'border-slate-300 focus:border-teal-600 focus:ring-teal-100' }} text-sm focus:outline-none focus:ring-2 transition-colors">
                    @error('plate_number')
                        <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Grid 3: Tahun, Transmisi, Kapasitas -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Model Year -->
                <div>
                    <label for="model_year" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                        Tahun Pembuatan <span class="text-rose-500">*</span>
                    </label>
                    <input type="number" 
                           id="model_year" 
                           name="model_year" 
                           value="{{ old('model_year', $car->model_year) }}" 
                           min="1990" 
                           max="{{ date('Y') + 1 }}" 
                           required
                           class="w-full px-3.5 py-2.5 rounded-lg border {{ $errors->has('model_year') ? 'border-rose-400 focus:ring-rose-200' : 'border-slate-300 focus:border-teal-600 focus:ring-teal-100' }} text-sm focus:outline-none focus:ring-2 transition-colors">
                    @error('model_year')
                        <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Transmission -->
                <div>
                    <label for="transmission" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                        Transmisi <span class="text-rose-500">*</span>
                    </label>
                    <select id="transmission" 
                            name="transmission" 
                            required
                            class="w-full px-3.5 py-2.5 rounded-lg border {{ $errors->has('transmission') ? 'border-rose-400 focus:ring-rose-200' : 'border-slate-300 focus:border-teal-600 focus:ring-teal-100' }} text-sm focus:outline-none focus:ring-2 transition-colors">
                        <option value="automatic" {{ old('transmission', $car->transmission) === 'automatic' ? 'selected' : '' }}>Otomatis (Automatic)</option>
                        <option value="manual" {{ old('transmission', $car->transmission) === 'manual' ? 'selected' : '' }}>Manual</option>
                    </select>
                    @error('transmission')
                        <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Capacity -->
                <div>
                    <label for="capacity" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                        Kapasitas Penumpang <span class="text-rose-500">*</span>
                    </label>
                    <input type="number" 
                           id="capacity" 
                           name="capacity" 
                           value="{{ old('capacity', $car->capacity) }}" 
                           min="1" 
                           max="50" 
                           required
                           class="w-full px-3.5 py-2.5 rounded-lg border {{ $errors->has('capacity') ? 'border-rose-400 focus:ring-rose-200' : 'border-slate-300 focus:border-teal-600 focus:ring-teal-100' }} text-sm focus:outline-none focus:ring-2 transition-colors">
                    @error('capacity')
                        <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Grid 4: Tarif Sewa & Status -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Price per Day -->
                <div>
                    <label for="price_per_day" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                        Tarif Sewa Harian (Rupiah) <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute left-3.5 top-2.5 text-xs font-bold text-slate-400">Rp</span>
                        <input type="number" 
                               id="price_per_day" 
                               name="price_per_day" 
                               value="{{ old('price_per_day', (int) $car->price_per_day) }}" 
                               min="10000" 
                               step="10000" 
                               required
                               class="w-full pl-10 pr-3.5 py-2.5 rounded-lg border {{ $errors->has('price_per_day') ? 'border-rose-400 focus:ring-rose-200' : 'border-slate-300 focus:border-teal-600 focus:ring-teal-100' }} text-sm focus:outline-none focus:ring-2 transition-colors font-medium">
                    </div>
                    @error('price_per_day')
                        <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Status -->
                <div>
                    <label for="status" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                        Status Ketersediaan <span class="text-rose-500">*</span>
                    </label>
                    <select id="status" 
                            name="status" 
                            required
                            class="w-full px-3.5 py-2.5 rounded-lg border {{ $errors->has('status') ? 'border-rose-400 focus:ring-rose-200' : 'border-slate-300 focus:border-teal-600 focus:ring-teal-100' }} text-sm focus:outline-none focus:ring-2 transition-colors">
                        <option value="available" {{ old('status', $car->status) === 'available' ? 'selected' : '' }}>Tersedia (Siap Disewa)</option>
                        <option value="rented" {{ old('status', $car->status) === 'rented' ? 'selected' : '' }}>Sedang Disewa Pelanggan</option>
                        <option value="maintenance" {{ old('status', $car->status) === 'maintenance' ? 'selected' : '' }}>Perawatan / Bengkel</option>
                    </select>
                    @error('status')
                        <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Description -->
            <div>
                <label for="description" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                    Catatan & Deskripsi Kondisi
                </label>
                <textarea id="description" 
                          name="description" 
                          rows="3" 
                          class="w-full px-3.5 py-2.5 rounded-lg border {{ $errors->has('description') ? 'border-rose-400 focus:ring-rose-200' : 'border-slate-300 focus:border-teal-600 focus:ring-teal-100' }} text-sm focus:outline-none focus:ring-2 transition-colors">{{ old('description', $car->description) }}</textarea>
                @error('description')
                    <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p>
                @enderror
            </div>

            <!-- Submit Buttons -->
            <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                <a href="{{ route('admin.cars.index') }}" class="px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-100 rounded-lg transition-colors">
                    Batal
                </a>
                <button type="submit" class="px-6 py-2.5 bg-teal-600 hover:bg-teal-700 text-white text-xs font-semibold rounded-lg shadow-sm transition-colors">
                    Perbarui Data Kendaraan
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
