@extends('layouts.admin')

@section('title', 'Detail Kendaraan - DriveEase')
@section('page_title', 'Detail Armada Kendaraan')

@section('content')
<div class="max-w-4xl space-y-6">
    <div class="flex items-center justify-between">
        <a href="{{ route('admin.cars.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-muted hover:text-ink transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Kembali ke Daftar Armada
        </a>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.cars.edit', $car) }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold bg-action hover:bg-action-hover text-ink rounded-lg shadow-sm transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Ubah Data
            </a>
            <form action="{{ route('admin.cars.destroy', $car) }}" 
                  method="POST" 
                  onsubmit="return confirm('Apakah Anda yakin ingin menghapus armada {{ $car->name }}?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold bg-danger-surface text-danger hover:bg-danger-surface border border-danger-line rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    Hapus
                </button>
            </form>
        </div>
    </div>

    <!-- Main Detail Card -->
    <div class="bg-surface rounded-xl border border-line shadow-sm overflow-hidden">
        <div class="p-8 border-b border-line flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <h2 class="text-2xl font-bold text-ink">{{ $car->name }}</h2>
                    @if ($car->archived_at)
<span class="text-xs font-semibold text-subtle">Diarsipkan</span>
@elseif ($car->status === 'available')
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-success-surface text-success">
                            Tersedia
                        </span>
                    @elseif ($car->status === 'rented')
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-warning-surface text-warning">
                            Sedang Disewa
                        </span>
                    @else
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-danger-surface text-danger">
                            Perawatan / Bengkel
                        </span>
                    @endif
                </div>
                <p class="text-sm text-muted mt-1">{{ $car->brand }} &bull; {{ $car->carType?->name ?? 'Kategori Umum' }}</p>
            </div>
            <div class="text-right">
                <span class="text-xs uppercase tracking-wider text-muted font-semibold block">Tarif Sewa Harian</span>
                <span class="text-2xl font-extrabold text-ink">
                    Rp {{ number_format($car->price_per_day, 0, ',', '.') }}
                </span>
                <span class="text-xs text-muted">/ 24 jam</span>
            </div>
        </div>

        <div class="p-8">
            <h3 class="text-xs font-semibold text-muted uppercase tracking-wider mb-4">Spesifikasi Teknis</h3>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
                <div class="p-4 rounded-lg bg-surface-raised border border-line">
                    <span class="text-[11px] font-medium text-muted uppercase tracking-wider block">Nomor Plat</span>
                    <span class="font-mono text-sm font-bold text-ink mt-1 block">{{ $car->plate_number }}</span>
                </div>
                <div class="p-4 rounded-lg bg-surface-raised border border-line">
                    <span class="text-[11px] font-medium text-muted uppercase tracking-wider block">Tahun Rakit</span>
                    <span class="text-sm font-bold text-ink mt-1 block">{{ $car->model_year }}</span>
                </div>
                <div class="p-4 rounded-lg bg-surface-raised border border-line">
                    <span class="text-[11px] font-medium text-muted uppercase tracking-wider block">Transmisi</span>
                    <span class="text-sm font-bold text-ink mt-1 block capitalize">{{ $car->transmission === 'automatic' ? 'Otomatis' : 'Manual' }}</span>
                </div>
                <div class="p-4 rounded-lg bg-surface-raised border border-line">
                    <span class="text-[11px] font-medium text-muted uppercase tracking-wider block">Kapasitas</span>
                    <span class="text-sm font-bold text-ink mt-1 block">{{ $car->capacity }} Penumpang</span>
                </div>
            </div>

            <h3 class="text-xs font-semibold text-muted uppercase tracking-wider mb-2">Deskripsi & Catatan Armada</h3>
            <div class="p-4 rounded-lg bg-surface-raised border border-line text-sm text-ink leading-relaxed">
                {{ $car->description ?: 'Tidak ada catatan tambahan untuk armada ini.' }}
            </div>

            <div class="mt-8 pt-6 border-t border-line text-xs text-muted flex items-center justify-between">
                <span>Didaftarkan: {{ $car->created_at?->translatedFormat('d F Y H:i') ?? '-' }}</span>
                <span>Terakhir diperbarui: {{ $car->updated_at?->translatedFormat('d F Y H:i') ?? '-' }}</span>
            </div>
        </div>
    </div>
</div>
@if ($car->maintenance_reason)
    <p class="mt-4 text-sm">Alasan perawatan: {{ $car->maintenance_reason }}</p>
@endif
@endsection
