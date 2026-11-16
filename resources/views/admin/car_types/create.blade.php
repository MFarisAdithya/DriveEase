@extends('layouts.admin')

@section('title', 'Tambah Kategori Kendaraan - DriveEase Admin')
@section('page_title', 'Tambah Kategori Kendaraan')

@section('content')
<div class="max-w-2xl">
    <div class="mb-6">
        <a href="{{ route('admin.car-types.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-muted hover:text-ink transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Kembali ke Daftar Kategori
        </a>
    </div>

    <div class="bg-surface rounded-xl border border-line shadow-sm p-8">
        <h2 class="text-base font-bold text-ink border-b border-line pb-4 mb-6">
            Formulir Tambah Kategori
        </h2>

        <form action="{{ route('admin.car-types.store') }}" method="POST" class="space-y-6">
            @csrf

            <!-- Name Field -->
            <div>
                <label for="name" class="block text-xs font-semibold text-ink uppercase tracking-wider mb-1.5">
                    Nama Kategori Kendaraan <span class="text-danger">*</span>
                </label>
                <input type="text" 
                       id="name" 
                       name="name" 
                       value="{{ old('name') }}" 
                       required
                       placeholder="Contoh: Hatchback, Minivan, Crossover" 
                       class="bg-surface-raised w-full px-3.5 py-2.5 rounded-lg border {{ $errors->has('name') ? 'border-danger-line focus:ring-danger-line' : 'border-field-line focus:border-focus focus:ring-focus' }} text-sm focus:outline-none focus:ring-2 transition-colors">
                @error('name')
                    <p class="mt-1.5 text-xs text-danger font-medium">{{ $message }}</p>
                @enderror
            </div>

            <!-- Description Field -->
            <div>
                <label for="description" class="block text-xs font-semibold text-ink uppercase tracking-wider mb-1.5">
                    Deskripsi Kategori (Opsional)
                </label>
                <textarea id="description" 
                          name="description" 
                          rows="4" 
                          placeholder="Jelaskan karakteristik atau peruntukan jenis kendaraan ini..." 
                          class="bg-surface-raised w-full px-3.5 py-2.5 rounded-lg border {{ $errors->has('description') ? 'border-danger-line focus:ring-danger-line' : 'border-field-line focus:border-focus focus:ring-focus' }} text-sm focus:outline-none focus:ring-2 transition-colors">{{ old('description') }}</textarea>
                @error('description')
                    <p class="mt-1.5 text-xs text-danger font-medium">{{ $message }}</p>
                @enderror
            </div>

            <!-- Submit Buttons -->
            <div class="pt-4 border-t border-line flex items-center justify-end gap-3">
                <a href="{{ route('admin.car-types.index') }}" class="px-4 py-2.5 text-xs font-semibold text-ink hover:bg-surface-hover rounded-lg transition-colors">
                    Batal
                </a>
                <button type="submit" class="px-6 py-2.5 bg-action hover:bg-action-hover text-ink text-xs font-semibold rounded-lg shadow-sm transition-colors">
                    Simpan Kategori Baru
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
