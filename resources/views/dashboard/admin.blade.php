@extends('layouts.admin')

@section('title', 'Dashboard Administrator - DriveEase')
@section('page_title', 'Dashboard Ringkasan Operasional')

@section('content')
<div class="space-y-8">
    <!-- Top Greeting & Shortcut -->
    <div class="bg-[#1E1712] rounded-xl p-6 border border-[#49392D] shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-[#E8DFD1]">Halo, {{ auth()->user()->name }}!</h2>
            <p class="text-sm text-[#CCBDAC] mt-1">Selamat datang di konsol utama manajemen rental kendaraan DriveEase.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.cars.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#935831] hover:bg-[#844F2C] text-white text-sm font-semibold rounded-lg shadow-sm transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Tambah Kendaraan Baru
            </a>
            <a href="{{ route('admin.cars.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#49392D] hover:bg-[#34271D] text-white text-sm font-semibold rounded-lg shadow-sm transition-colors">
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
        <div class="bg-[#1E1712] rounded-xl p-5 border border-[#49392D] shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-[#CCBDAC]">Total Kendaraan</span>
                <span class="p-2 rounded-lg bg-[#34271D] text-[#E8DFD1]">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 19H5V8h14m-3-5H8L5 6v13a2 2 0 002 2h10a2 2 0 002-2V6l-3-3z"/>
                    </svg>
                </span>
            </div>
            <p class="text-3xl font-bold text-[#E8DFD1] mt-3">{{ $stats['total'] }}</p>
            <p class="text-xs text-[#CCBDAC] mt-1">Armada terdaftar</p>
        </div>

        <!-- Available Vehicles -->
        <div class="bg-[#1E1712] rounded-xl p-5 border border-[#49392D] shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-emerald-300">Unit Tersedia</span>
                <span class="p-2 rounded-lg bg-emerald-950 text-emerald-300">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </span>
            </div>
            <p class="text-3xl font-bold text-emerald-300 mt-3">{{ $stats['available'] }}</p>
            <p class="text-xs text-[#CCBDAC] mt-1">Siap disewa pelanggan</p>
        </div>

        <!-- Rented Vehicles -->
        <div class="bg-[#1E1712] rounded-xl p-5 border border-[#49392D] shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-amber-300">Sedang Disewa</span>
                <span class="p-2 rounded-lg bg-amber-950 text-amber-300">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </span>
            </div>
            <p class="text-3xl font-bold text-amber-300 mt-3">{{ $stats['rented'] }}</p>
            <p class="text-xs text-[#CCBDAC] mt-1">Berada di tangan pelanggan</p>
        </div>

        <!-- Maintenance Vehicles -->
        <div class="bg-[#1E1712] rounded-xl p-5 border border-[#49392D] shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-rose-300">Perawatan / Servis</span>
                <span class="p-2 rounded-lg bg-rose-950 text-rose-300">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </span>
            </div>
            <p class="text-3xl font-bold text-rose-300 mt-3">{{ $stats['maintenance'] }}</p>
            <p class="text-xs text-[#CCBDAC] mt-1">Dalam perbaikan bengkel</p>
        </div>

        <!-- Pending Verifications (Clickable to filtered list) -->
        <a href="{{ route('admin.verifications.index', ['status' => 'pending']) }}" class="bg-[#1E1712] rounded-xl p-5 border {{ $stats['pending_verifications'] > 0 ? 'border-amber-700 ring-2 ring-amber-800' : 'border-[#49392D]' }} shadow-sm hover:border-amber-700 transition-all block group">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-amber-300 group-hover:text-amber-300">Verifikasi Tertunda</span>
                <span class="p-2 rounded-lg bg-amber-950 text-amber-300 group-hover:bg-amber-950 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                </span>
            </div>
            <p class="text-3xl font-bold text-amber-300 mt-3">{{ $stats['pending_verifications'] }}</p>
            <p class="text-xs text-amber-300/80 mt-1 flex items-center gap-1 font-medium">
                Customer menunggu persetujuan &rarr;
            </p>
        </a>
            <p class="text-xs text-[#CCBDAC] mt-1">Dalam masa perbaikan/bengkel</p>
        </div>
    </div>

    <!-- Recent Vehicles Table -->
    <div class="bg-[#1E1712] rounded-xl border border-[#49392D] shadow-sm overflow-hidden">
        <div class="p-6 border-b border-[#49392D] flex items-center justify-between">
            <div>
                <h3 class="text-base font-bold text-[#E8DFD1]">Kendaraan Terbaru Ditambahkan</h3>
                <p class="text-xs text-[#CCBDAC] mt-0.5">5 unit terakhir yang tercatat di dalam sistem database.</p>
            </div>
            <a href="{{ route('admin.cars.index') }}" class="text-xs font-semibold text-[#E8AC80] hover:text-[#E8AC80]">
                Lihat Semua &rarr;
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-[#241C16] text-[11px] font-semibold text-[#CCBDAC] uppercase tracking-wider border-b border-[#49392D]">
                        <th class="py-3 px-6">Armada</th>
                        <th class="py-3 px-6">Kategori</th>
                        <th class="py-3 px-6">Plat Nomor</th>
                        <th class="py-3 px-6">Tahun</th>
                        <th class="py-3 px-6">Tarif / Hari</th>
                        <th class="py-3 px-6">Status</th>
                        <th class="py-3 px-6 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#49392D] text-sm">
                    @forelse ($recentCars as $car)
                        <tr class="hover:bg-[#241C16]/80 transition-colors">
                            <td class="py-3.5 px-6">
                                <span class="font-semibold text-[#E8DFD1] block">{{ $car->name }}</span>
                                <span class="text-xs text-[#CCBDAC]">{{ $car->brand }}</span>
                            </td>
                            <td class="py-3.5 px-6 text-[#CCBDAC]">
                                <span class="px-2 py-0.5 rounded text-xs font-medium bg-[#34271D] text-[#E8DFD1]">
                                    {{ $car->carType?->name ?? 'Umum' }}
                                </span>
                            </td>
                            <td class="py-3.5 px-6 font-mono text-xs font-semibold text-[#E8DFD1]">
                                {{ $car->plate_number }}
                            </td>
                            <td class="py-3.5 px-6 text-[#CCBDAC]">{{ $car->model_year }}</td>
                            <td class="py-3.5 px-6 font-semibold text-[#E8DFD1]">
                                Rp {{ number_format($car->price_per_day, 0, ',', '.') }}
                            </td>
                            <td class="py-3.5 px-6">
                                @if ($car->status === 'available')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-950 text-emerald-300">
                                        Tersedia
                                    </span>
                                @elseif ($car->status === 'rented')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-950 text-amber-300">
                                        Disewa
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-rose-950 text-rose-300">
                                        Perawatan
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-6 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <a href="{{ route('admin.cars.show', $car) }}" class="text-xs font-medium text-[#CCBDAC] hover:text-[#E8AC80]">Detail</a>
                                    <span class="text-[#CCBDAC]">|</span>
                                    <a href="{{ route('admin.cars.edit', $car) }}" class="text-xs font-medium text-[#E8AC80] hover:text-[#E8AC80]">Ubah</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-[#CCBDAC]">
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
