@extends('layouts.admin')

@section('title', 'Ubah Kategori Kendaraan - DriveEase Admin')
@section('page_title', 'Ubah Kategori Kendaraan')

@section('content')
<div class="max-w-2xl">
    <div class="mb-6">
        <a href="{{ route('admin.car-types.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#CCBDAC] hover:text-[#E8DFD1] transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Kembali ke Daftar Kategori
        </a>
    </div>

    <div class="bg-[#1E1712] rounded-xl border border-[#49392D] shadow-sm p-8">
        <div class="flex items-center justify-between border-b border-[#49392D] pb-4 mb-6">
            <div>
                <h2 class="text-base font-bold text-[#E8DFD1]">Ubah Data Kategori</h2>
                <p class="text-xs text-[#CCBDAC] mt-0.5">Memperbarui informasi kategori "{{ $carType->name }}"</p>
            </div>
            <span class="font-mono text-xs font-bold px-2.5 py-1 bg-[#34271D] rounded text-[#E8DFD1] border border-[#49392D]">
                ID #{{ $carType->id }}
            </span>
        </div>

        <form action="{{ route('admin.car-types.update', $carType) }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')

            <!-- Name Field -->
            <div>
                <label for="name" class="block text-xs font-semibold text-[#E8DFD1] uppercase tracking-wider mb-1.5">
                    Nama Kategori Kendaraan <span class="text-rose-300">*</span>
                </label>
                <input type="text" 
                       id="name" 
                       name="name" 
                       value="{{ old('name', $carType->name) }}" 
                       required
                       class="w-full px-3.5 py-2.5 rounded-lg border {{ $errors->has('name') ? 'border-rose-700 focus:ring-rose-800' : 'border-[#776553] focus:border-[#C47F4E] focus:ring-[#C47F4E]' }} text-sm focus:outline-none focus:ring-2 transition-colors">
                @error('name')
                    <p class="mt-1.5 text-xs text-rose-300 font-medium">{{ $message }}</p>
                @enderror
            </div>

            <!-- Description Field -->
            <div>
                <label for="description" class="block text-xs font-semibold text-[#E8DFD1] uppercase tracking-wider mb-1.5">
                    Deskripsi Kategori (Opsional)
                </label>
                <textarea id="description" 
                          name="description" 
                          rows="4" 
                          class="bg-[#241C16] w-full px-3.5 py-2.5 rounded-lg border {{ $errors->has('description') ? 'border-rose-700 focus:ring-rose-800' : 'border-[#776553] focus:border-[#C47F4E] focus:ring-[#C47F4E]' }} text-sm focus:outline-none focus:ring-2 transition-colors">{{ old('description', $carType->description) }}</textarea>
                @error('description')
                    <p class="mt-1.5 text-xs text-rose-300 font-medium">{{ $message }}</p>
                @enderror
            </div>

            <!-- Submit Buttons -->
            <div class="pt-4 border-t border-[#49392D] flex items-center justify-end gap-3">
                <a href="{{ route('admin.car-types.index') }}" class="px-4 py-2.5 text-xs font-semibold text-[#E8DFD1] hover:bg-[#34271D] rounded-lg transition-colors">
                    Batal
                </a>
                <button type="submit" class="px-6 py-2.5 bg-[#935831] hover:bg-[#844F2C] text-white text-xs font-semibold rounded-lg shadow-sm transition-colors">
                    Perbarui Kategori
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
