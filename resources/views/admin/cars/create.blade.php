@extends('layouts.admin')

@section('title', 'Tambah Kendaraan Baru - DriveEase')
@section('page_title', 'Tambah Armada Kendaraan')

@section('content')
<div class="max-w-4xl">
    <div class="mb-6">
        <a href="{{ route('admin.cars.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#CCBDAC] hover:text-[#E8DFD1] transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Kembali ke Daftar Armada
        </a>
    </div>

    <div class="bg-[#1E1712] rounded-xl border border-[#49392D] shadow-sm p-8">
        <h2 class="text-base font-bold text-[#E8DFD1] border-b border-[#49392D] pb-4 mb-6">
            Formulir Pendaftaran Armada Baru
        </h2>

        <form action="{{ route('admin.cars.store') }}" method="POST" class="space-y-6">
            @csrf

            <!-- Grid 1: Nama & Merek -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Name -->
                <div>
                    <label for="name" class="block text-xs font-semibold text-[#E8DFD1] uppercase tracking-wider mb-1.5">
                        Nama Model Kendaraan <span class="text-rose-300">*</span>
                    </label>
                    <input type="text" 
                           id="name" 
                           name="name" 
                           value="{{ old('name') }}" 
                           placeholder="Contoh: Toyota Avanza 1.5 G" 
                           class="bg-[#241C16] w-full px-3.5 py-2.5 rounded-lg border {{ $errors->has('name') ? 'border-rose-700 focus:ring-rose-800' : 'border-[#776553] focus:border-[#C47F4E] focus:ring-[#C47F4E]' }} text-sm focus:outline-none focus:ring-2 transition-colors">
                    @error('name')
                        <p class="mt-1.5 text-xs text-rose-300 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Brand -->
                <div>
                    <label for="brand" class="block text-xs font-semibold text-[#E8DFD1] uppercase tracking-wider mb-1.5">
                        Merek Pabrikan <span class="text-rose-300">*</span>
                    </label>
                    <input type="text" 
                           id="brand" 
                           name="brand" 
                           value="{{ old('brand') }}" 
                           placeholder="Contoh: Toyota, Honda, Mitsubishi" 
                           class="bg-[#241C16] w-full px-3.5 py-2.5 rounded-lg border {{ $errors->has('brand') ? 'border-rose-700 focus:ring-rose-800' : 'border-[#776553] focus:border-[#C47F4E] focus:ring-[#C47F4E]' }} text-sm focus:outline-none focus:ring-2 transition-colors">
                    @error('brand')
                        <p class="mt-1.5 text-xs text-rose-300 font-medium">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Grid 2: Kategori & Plat Nomor -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Car Type -->
                <div>
                    <label for="car_type_id" class="block text-xs font-semibold text-[#E8DFD1] uppercase tracking-wider mb-1.5">
                        Kategori Kendaraan <span class="text-rose-300">*</span>
                    </label>
                    <select id="car_type_id" 
                            name="car_type_id" 
                            class="bg-[#241C16] w-full px-3.5 py-2.5 rounded-lg border {{ $errors->has('car_type_id') ? 'border-rose-700 focus:ring-rose-800' : 'border-[#776553] focus:border-[#C47F4E] focus:ring-[#C47F4E]' }} text-sm focus:outline-none focus:ring-2 transition-colors">
                        <option value="">-- Pilih Kategori --</option>
                        @foreach ($carTypes as $type)
                            <option value="{{ $type->id }}" {{ old('car_type_id') == $type->id ? 'selected' : '' }}>
                                {{ $type->name }} ({{ $type->description }})
                            </option>
                        @endforeach
                    </select>
                    @error('car_type_id')
                        <p class="mt-1.5 text-xs text-rose-300 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Plate Number -->
                <div>
                    <label for="plate_number" class="block text-xs font-semibold text-[#E8DFD1] uppercase tracking-wider mb-1.5">
                        Nomor Plat Polisi <span class="text-rose-300">*</span>
                    </label>
                    <input type="text" 
                           id="plate_number" 
                           name="plate_number" 
                           value="{{ old('plate_number') }}" 
                           placeholder="Contoh: B 1234 XYZ" 
                           class="bg-[#241C16] w-full font-mono uppercase px-3.5 py-2.5 rounded-lg border {{ $errors->has('plate_number') ? 'border-rose-700 focus:ring-rose-800' : 'border-[#776553] focus:border-[#C47F4E] focus:ring-[#C47F4E]' }} text-sm focus:outline-none focus:ring-2 transition-colors">
                    @error('plate_number')
                        <p class="mt-1.5 text-xs text-rose-300 font-medium">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Grid 3: Tahun, Transmisi, Kapasitas -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Model Year -->
                <div>
                    <label for="model_year" class="block text-xs font-semibold text-[#E8DFD1] uppercase tracking-wider mb-1.5">
                        Tahun Pembuatan <span class="text-rose-300">*</span>
                    </label>
                    <input type="number" 
                           id="model_year" 
                           name="model_year" 
                           value="{{ old('model_year', date('Y')) }}" 
                           min="1990" 
                           max="{{ date('Y') + 1 }}" 
                           class="bg-[#241C16] w-full px-3.5 py-2.5 rounded-lg border {{ $errors->has('model_year') ? 'border-rose-700 focus:ring-rose-800' : 'border-[#776553] focus:border-[#C47F4E] focus:ring-[#C47F4E]' }} text-sm focus:outline-none focus:ring-2 transition-colors">
                    @error('model_year')
                        <p class="mt-1.5 text-xs text-rose-300 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Transmission -->
                <div>
                    <label for="transmission" class="block text-xs font-semibold text-[#E8DFD1] uppercase tracking-wider mb-1.5">
                        Transmisi <span class="text-rose-300">*</span>
                    </label>
                    <select id="transmission" 
                            name="transmission" 
                            class="bg-[#241C16] w-full px-3.5 py-2.5 rounded-lg border {{ $errors->has('transmission') ? 'border-rose-700 focus:ring-rose-800' : 'border-[#776553] focus:border-[#C47F4E] focus:ring-[#C47F4E]' }} text-sm focus:outline-none focus:ring-2 transition-colors">
                        <option value="automatic" {{ old('transmission') === 'automatic' ? 'selected' : '' }}>Otomatis (Automatic)</option>
                        <option value="manual" {{ old('transmission') === 'manual' ? 'selected' : '' }}>Manual</option>
                    </select>
                    @error('transmission')
                        <p class="mt-1.5 text-xs text-rose-300 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Capacity -->
                <div>
                    <label for="capacity" class="block text-xs font-semibold text-[#E8DFD1] uppercase tracking-wider mb-1.5">
                        Kapasitas Penumpang <span class="text-rose-300">*</span>
                    </label>
                    <input type="number" 
                           id="capacity" 
                           name="capacity" 
                           value="{{ old('capacity', 5) }}" 
                           min="1" 
                           max="50" 
                           class="bg-[#241C16] w-full px-3.5 py-2.5 rounded-lg border {{ $errors->has('capacity') ? 'border-rose-700 focus:ring-rose-800' : 'border-[#776553] focus:border-[#C47F4E] focus:ring-[#C47F4E]' }} text-sm focus:outline-none focus:ring-2 transition-colors">
                    @error('capacity')
                        <p class="mt-1.5 text-xs text-rose-300 font-medium">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Grid 4: Tarif Sewa & Status -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Price per Day -->
                <div>
                    <label for="price_per_day" class="block text-xs font-semibold text-[#E8DFD1] uppercase tracking-wider mb-1.5">
                        Tarif Sewa Harian (Rupiah) <span class="text-rose-300">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute left-3.5 top-2.5 text-xs font-bold text-[#CCBDAC]">Rp</span>
                        <input type="number" 
                               id="price_per_day" 
                               name="price_per_day" 
                               value="{{ old('price_per_day') }}" 
                               min="10000" 
                               step="10000" 
                               placeholder="Contoh: 450000" 
                               class="bg-[#241C16] w-full pl-10 pr-3.5 py-2.5 rounded-lg border {{ $errors->has('price_per_day') ? 'border-rose-700 focus:ring-rose-800' : 'border-[#776553] focus:border-[#C47F4E] focus:ring-[#C47F4E]' }} text-sm focus:outline-none focus:ring-2 transition-colors font-medium">
                    </div>
                    @error('price_per_day')
                        <p class="mt-1.5 text-xs text-rose-300 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Status -->
                <div>
                    <label for="status" class="block text-xs font-semibold text-[#E8DFD1] uppercase tracking-wider mb-1.5">
                        Status Ketersediaan <span class="text-rose-300">*</span>
                    </label>
                    <select id="status" 
                            name="status" 
                            class="bg-[#241C16] w-full px-3.5 py-2.5 rounded-lg border {{ $errors->has('status') ? 'border-rose-700 focus:ring-rose-800' : 'border-[#776553] focus:border-[#C47F4E] focus:ring-[#C47F4E]' }} text-sm focus:outline-none focus:ring-2 transition-colors">
                        <option value="available" {{ old('status', 'available') === 'available' ? 'selected' : '' }}>Tersedia (Siap Disewa)</option>
                        <option value="rented" {{ old('status') === 'rented' ? 'selected' : '' }}>Sedang Disewa Pelanggan</option>
                        <option value="maintenance" {{ old('status') === 'maintenance' ? 'selected' : '' }}>Perawatan / Bengkel</option>
                    </select>
                    @error('status')
                        <p class="mt-1.5 text-xs text-rose-300 font-medium">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Description -->
            <div>
                <label for="description" class="block text-xs font-semibold text-[#E8DFD1] uppercase tracking-wider mb-1.5">
                    Catatan & Deskripsi Kondisi
                </label>
                <textarea id="description" 
                          name="description" 
                          rows="3" 
                          placeholder="Tuliskan catatan kondisi fisik, kelengkapan surat, atau fasilitas khusus mobil..." 
                          class="bg-[#241C16] w-full px-3.5 py-2.5 rounded-lg border {{ $errors->has('description') ? 'border-rose-700 focus:ring-rose-800' : 'border-[#776553] focus:border-[#C47F4E] focus:ring-[#C47F4E]' }} text-sm focus:outline-none focus:ring-2 transition-colors">{{ old('description') }}</textarea>
                @error('description')
                    <p class="mt-1.5 text-xs text-rose-300 font-medium">{{ $message }}</p>
                @enderror
            </div>

            <!-- Submit Buttons -->
            <div class="pt-4 border-t border-[#49392D] flex items-center justify-end gap-3">
                <a href="{{ route('admin.cars.index') }}" class="px-4 py-2.5 text-xs font-semibold text-[#E8DFD1] hover:bg-[#34271D] rounded-lg transition-colors">
                    Batal
                </a>
                <button type="submit" class="px-6 py-2.5 bg-[#935831] hover:bg-[#844F2C] text-white text-xs font-semibold rounded-lg shadow-sm transition-colors">
                    Simpan Kendaraan Baru
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
