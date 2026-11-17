@extends('layouts.portal')

@section('title', 'Dasbor Staf Operasional - DriveEase')

@section('content')
<div class="space-y-6">
    <!-- Welcome Banner -->
    <div class="bg-surface rounded-xl p-6 border border-line shadow-sm">
        <h2 class="text-xl font-bold text-ink">Selamat Bertugas, {{ auth()->user()->name }}!</h2>
        <p class="text-sm text-muted mt-1">
            Anda masuk sebagai <strong class="text-ink">Staf Operasional Lapangan</strong>. Berikut adalah data ketersediaan armada rental hari ini.
        </p>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div class="bg-surface rounded-xl p-5 border border-line shadow-sm">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted">Total Armada</span>
            <p class="text-3xl font-bold text-ink mt-2">{{ $totalCars }}</p>
            <p class="text-xs text-muted mt-1">Seluruh unit terdaftar</p>
        </div>
        <div class="bg-surface rounded-xl p-5 border border-line shadow-sm">
            <span class="text-xs font-semibold uppercase tracking-wider text-success">Unit Siap Sewa</span>
            <p class="text-3xl font-bold text-success mt-2">{{ $availableCars }}</p>
            <p class="text-xs text-muted mt-1">Tersedia di garasi</p>
        </div>
    </div>

    <!-- Read-Only Vehicle Table -->
    <div class="bg-surface rounded-xl border border-line shadow-sm overflow-hidden">
        <div class="p-5 border-b border-line">
            <h3 class="text-base font-bold text-ink">Daftar Status Armada Kendaraan (Mode Baca)</h3>
            <p class="text-xs text-muted mt-0.5">Staf operasional hanya memiliki hak memantau status armada tanpa wewenang mengubah data.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-raised text-[11px] font-semibold text-muted uppercase tracking-wider border-b border-line">
                        <th class="py-3 px-5">Nama Kendaraan</th>
                        <th class="py-3 px-5">Kategori</th>
                        <th class="py-3 px-5">Plat Nomor</th>
                        <th class="py-3 px-5">Transmisi</th>
                        <th class="py-3 px-5">Kapasitas</th>
                        <th class="py-3 px-5">Tarif / Hari</th>
                        <th class="py-3 px-5">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line text-sm">
                    @forelse ($cars as $car)
                        <tr class="hover:bg-surface-raised/80 transition-colors">
                            <td class="py-3 px-5">
                                <span class="font-bold text-ink block">{{ $car->name }}</span>
                                <span class="text-xs text-muted">{{ $car->brand }} &bull; {{ $car->model_year }}</span>
                            </td>
                            <td class="py-3 px-5 text-muted">
                                <span class="px-2 py-0.5 rounded text-xs font-medium bg-surface-hover text-ink">
                                    {{ $car->carType?->name ?? '-' }}
                                </span>
                            </td>
                            <td class="py-3 px-5 font-mono text-xs font-semibold text-ink">
                                {{ $car->plate_number }}
                            </td>
                            <td class="py-3 px-5 text-xs text-muted capitalize">{{ $car->transmission === 'automatic' ? 'Otomatis' : 'Manual' }}</td>
                            <td class="py-3 px-5 text-xs text-muted">{{ $car->capacity }} Kursi</td>
                            <td class="py-3 px-5 font-semibold text-ink text-xs">
                                Rp {{ number_format($car->price_per_day, 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-5">
                                @if ($car->status === 'available')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-success-surface text-success">
                                        Tersedia
                                    </span>
                                @elseif ($car->status === 'rented')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-warning-surface text-warning">
                                        Sedang Disewa
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-danger-surface text-danger">
                                        Perawatan
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-muted">
                                Belum ada data kendaraan terdaftar.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
