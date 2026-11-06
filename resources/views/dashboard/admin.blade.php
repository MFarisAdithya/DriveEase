@extends('layouts.admin')

@section('title', 'Dashboard Administrator - DriveEase')
@section('page_title', 'Dashboard Ringkasan Operasional')

@section('content')
<div class="space-y-8">
    <!-- Top Greeting & Shortcut -->
    <div class="bg-white rounded-xl p-6 border border-slate-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-slate-900">Halo, {{ auth()->user()->name }}!</h2>
            <p class="text-sm text-slate-500 mt-1">Selamat datang di konsol utama manajemen rental kendaraan DriveEase.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.cars.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-teal-600 hover:bg-teal-700 text-white text-sm font-semibold rounded-lg shadow-sm transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Tambah Kendaraan Baru
            </a>
            <a href="{{ route('admin.cars.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white text-sm font-semibold rounded-lg shadow-sm transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                </svg>
                Semua Armada
            </a>
        </div>
    </div>

    <!-- Statistics Grid (All from DB) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-5">
        <!-- Total Vehicles -->
        <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Kendaraan</span>
                <span class="p-2 rounded-lg bg-slate-100 text-slate-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 19H5V8h14m-3-5H8L5 6v13a2 2 0 002 2h10a2 2 0 002-2V6l-3-3z"/>
                    </svg>
                </span>
            </div>
            <p class="text-3xl font-bold text-slate-900 mt-3">{{ $stats['total'] }}</p>
            <p class="text-xs text-slate-500 mt-1">Armada terdaftar</p>
        </div>

        <!-- Available Vehicles -->
        <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-emerald-600">Unit Tersedia</span>
                <span class="p-2 rounded-lg bg-emerald-50 text-emerald-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </span>
            </div>
            <p class="text-3xl font-bold text-emerald-700 mt-3">{{ $stats['available'] }}</p>
            <p class="text-xs text-slate-500 mt-1">Siap disewa pelanggan</p>
        </div>

        <!-- Rented Vehicles -->
        <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-amber-600">Sedang Disewa</span>
                <span class="p-2 rounded-lg bg-amber-50 text-amber-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </span>
            </div>
            <p class="text-3xl font-bold text-amber-700 mt-3">{{ $stats['rented'] }}</p>
            <p class="text-xs text-slate-500 mt-1">Berada di tangan pelanggan</p>
        </div>

        <!-- Maintenance Vehicles -->
        <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-rose-600">Perawatan / Servis</span>
                <span class="p-2 rounded-lg bg-rose-50 text-rose-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </span>
            </div>
            <p class="text-3xl font-bold text-rose-700 mt-3">{{ $stats['maintenance'] }}</p>
            <p class="text-xs text-slate-500 mt-1">Dalam perbaikan bengkel</p>
        </div>

        <!-- Pending Verifications (Clickable to filtered list) -->
        <a href="{{ route('admin.verifications.index', ['status' => 'pending']) }}" class="bg-white rounded-xl p-5 border {{ $stats['pending_verifications'] > 0 ? 'border-amber-300 ring-2 ring-amber-100' : 'border-slate-200' }} shadow-sm hover:border-amber-400 transition-all block group">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-amber-700 group-hover:text-amber-800">Verifikasi Tertunda</span>
                <span class="p-2 rounded-lg bg-amber-50 text-amber-600 group-hover:bg-amber-100 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                </span>
            </div>
            <p class="text-3xl font-bold text-amber-600 mt-3">{{ $stats['pending_verifications'] }}</p>
            <p class="text-xs text-amber-600/80 mt-1 flex items-center gap-1 font-medium">
                Customer menunggu persetujuan &rarr;
            </p>
        </a>
            <p class="text-xs text-slate-500 mt-1">Dalam masa perbaikan/bengkel</p>
        </div>
    </div>

    <!-- Recent Vehicles Table -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-6 border-b border-slate-200 flex items-center justify-between">
            <div>
                <h3 class="text-base font-bold text-slate-900">Kendaraan Terbaru Ditambahkan</h3>
                <p class="text-xs text-slate-500 mt-0.5">5 unit terakhir yang tercatat di dalam sistem database.</p>
            </div>
            <a href="{{ route('admin.cars.index') }}" class="text-xs font-semibold text-teal-700 hover:text-teal-800">
                Lihat Semua &rarr;
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 text-[11px] font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200">
                        <th class="py-3 px-6">Armada</th>
                        <th class="py-3 px-6">Kategori</th>
                        <th class="py-3 px-6">Plat Nomor</th>
                        <th class="py-3 px-6">Tahun</th>
                        <th class="py-3 px-6">Tarif / Hari</th>
                        <th class="py-3 px-6">Status</th>
                        <th class="py-3 px-6 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    @forelse ($recentCars as $car)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="py-3.5 px-6">
                                <span class="font-semibold text-slate-900 block">{{ $car->name }}</span>
                                <span class="text-xs text-slate-500">{{ $car->brand }}</span>
                            </td>
                            <td class="py-3.5 px-6 text-slate-600">
                                <span class="px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-700">
                                    {{ $car->carType?->name ?? 'Umum' }}
                                </span>
                            </td>
                            <td class="py-3.5 px-6 font-mono text-xs font-semibold text-slate-700">
                                {{ $car->plate_number }}
                            </td>
                            <td class="py-3.5 px-6 text-slate-600">{{ $car->model_year }}</td>
                            <td class="py-3.5 px-6 font-semibold text-slate-900">
                                Rp {{ number_format($car->price_per_day, 0, ',', '.') }}
                            </td>
                            <td class="py-3.5 px-6">
                                @if ($car->status === 'available')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                                        Tersedia
                                    </span>
                                @elseif ($car->status === 'rented')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                                        Disewa
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-rose-100 text-rose-800">
                                        Perawatan
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-6 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <a href="{{ route('admin.cars.show', $car) }}" class="text-xs font-medium text-slate-600 hover:text-teal-700">Detail</a>
                                    <span class="text-slate-300">|</span>
                                    <a href="{{ route('admin.cars.edit', $car) }}" class="text-xs font-medium text-teal-700 hover:text-teal-800">Ubah</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-500">
                                Belum ada data kendaraan di database.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
