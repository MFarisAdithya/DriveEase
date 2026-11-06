@extends('layouts.portal')

@section('title', 'Dashboard Staf Operasional - DriveEase')

@section('content')
<div class="space-y-6">
    <!-- Welcome Banner -->
    <div class="bg-white rounded-xl p-6 border border-slate-200 shadow-sm">
        <h2 class="text-xl font-bold text-slate-900">Selamat Bertugas, {{ auth()->user()->name }}!</h2>
        <p class="text-sm text-slate-500 mt-1">
            Anda masuk sebagai <strong class="text-slate-800">Staf Operasional Lapangan</strong>. Berikut adalah data ketersediaan armada rental hari ini.
        </p>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Armada</span>
            <p class="text-3xl font-bold text-slate-900 mt-2">{{ $totalCars }}</p>
            <p class="text-xs text-slate-500 mt-1">Seluruh unit terdaftar</p>
        </div>
        <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
            <span class="text-xs font-semibold uppercase tracking-wider text-emerald-600">Unit Siap Sewa</span>
            <p class="text-3xl font-bold text-emerald-700 mt-2">{{ $availableCars }}</p>
            <p class="text-xs text-slate-500 mt-1">Tersedia di garasi</p>
        </div>
    </div>

    <!-- Read-Only Vehicle Table -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-200">
            <h3 class="text-base font-bold text-slate-900">Daftar Status Armada Kendaraan (Mode Baca)</h3>
            <p class="text-xs text-slate-500 mt-0.5">Staf operasional hanya memiliki hak memantau status armada tanpa wewenang mengubah data.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 text-[11px] font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200">
                        <th class="py-3 px-5">Nama Kendaraan</th>
                        <th class="py-3 px-5">Kategori</th>
                        <th class="py-3 px-5">Plat Nomor</th>
                        <th class="py-3 px-5">Transmisi</th>
                        <th class="py-3 px-5">Kapasitas</th>
                        <th class="py-3 px-5">Tarif / Hari</th>
                        <th class="py-3 px-5">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    @forelse ($cars as $car)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="py-3 px-5">
                                <span class="font-bold text-slate-900 block">{{ $car->name }}</span>
                                <span class="text-xs text-slate-500">{{ $car->brand }} &bull; {{ $car->model_year }}</span>
                            </td>
                            <td class="py-3 px-5 text-slate-600">
                                <span class="px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-700">
                                    {{ $car->carType?->name ?? '-' }}
                                </span>
                            </td>
                            <td class="py-3 px-5 font-mono text-xs font-semibold text-slate-800">
                                {{ $car->plate_number }}
                            </td>
                            <td class="py-3 px-5 text-xs text-slate-600 capitalize">{{ $car->transmission }}</td>
                            <td class="py-3 px-5 text-xs text-slate-600">{{ $car->capacity }} Kursi</td>
                            <td class="py-3 px-5 font-semibold text-slate-900 text-xs">
                                Rp {{ number_format($car->price_per_day, 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-5">
                                @if ($car->status === 'available')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                                        Tersedia
                                    </span>
                                @elseif ($car->status === 'rented')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                                        Sedang Disewa
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-rose-100 text-rose-800">
                                        Perawatan
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-500">
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
