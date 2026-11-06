@extends('layouts.admin')

@section('title', 'Kelola Armada Kendaraan - DriveEase')
@section('page_title', 'Daftar Armada Kendaraan')

@section('content')
<div class="space-y-6">
    <!-- Action Bar & Filters -->
    <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <!-- Search & Filter Form -->
        <form action="{{ route('admin.cars.index') }}" method="GET" class="flex flex-wrap items-center gap-3 flex-1">
            <!-- Search input -->
            <div class="relative min-w-[200px] flex-1">
                <input type="text" 
                       name="search" 
                       value="{{ request('search') }}" 
                       placeholder="Cari nama, merek, atau plat..." 
                       class="w-full pl-9 pr-3 py-2 text-xs rounded-lg border border-slate-300 focus:outline-none focus:border-teal-600 focus:ring-1 focus:ring-teal-500">
                <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>

            <!-- Category filter -->
            <select name="car_type_id" class="text-xs py-2 px-3 rounded-lg border border-slate-300 focus:outline-none focus:border-teal-600">
                <option value="">Semua Kategori</option>
                @foreach ($carTypes as $type)
                    <option value="{{ $type->id }}" {{ request('car_type_id') == $type->id ? 'selected' : '' }}>
                        {{ $type->name }}
                    </option>
                @endforeach
            </select>

            <!-- Status filter -->
            <select name="status" class="text-xs py-2 px-3 rounded-lg border border-slate-300 focus:outline-none focus:border-teal-600">
                <option value="">Semua Status</option>
                <option value="available" {{ request('status') == 'available' ? 'selected' : '' }}>Tersedia</option>
                <option value="rented" {{ request('status') == 'rented' ? 'selected' : '' }}>Sedang Disewa</option>
                <option value="maintenance" {{ request('status') == 'maintenance' ? 'selected' : '' }}>Perawatan</option>
            </select>

            <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold rounded-lg transition-colors">
                Terapkan Filter
            </button>

            @if(request()->hasAny(['search', 'car_type_id', 'status']))
                <a href="{{ route('admin.cars.index') }}" class="text-xs text-rose-600 hover:underline">Reset</a>
            @endif
        </form>

        <!-- Add Button -->
        <a href="{{ route('admin.cars.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white text-xs font-semibold rounded-lg shadow-sm transition-colors shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Tambah Kendaraan
        </a>
    </div>

    <!-- Vehicles Table -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 text-[11px] font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200">
                        <th class="py-3 px-5">Nama Kendaraan</th>
                        <th class="py-3 px-5">Kategori</th>
                        <th class="py-3 px-5">Plat Nomor</th>
                        <th class="py-3 px-5">Spesifikasi</th>
                        <th class="py-3 px-5">Tarif Sewa / Hari</th>
                        <th class="py-3 px-5">Status</th>
                        <th class="py-3 px-5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    @forelse ($cars as $car)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="py-3.5 px-5">
                                <span class="font-bold text-slate-900 block">{{ $car->name }}</span>
                                <span class="text-xs text-slate-500">{{ $car->brand }}</span>
                            </td>
                            <td class="py-3.5 px-5 text-slate-600">
                                <span class="px-2.5 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-700">
                                    {{ $car->carType?->name ?? '-' }}
                                </span>
                            </td>
                            <td class="py-3.5 px-5">
                                <span class="font-mono text-xs font-bold px-2 py-1 bg-slate-100 rounded text-slate-800 border border-slate-200">
                                    {{ $car->plate_number }}
                                </span>
                            </td>
                            <td class="py-3.5 px-5 text-xs text-slate-600">
                                <p>{{ $car->model_year }} &bull; {{ ucfirst($car->transmission) }}</p>
                                <p class="text-slate-400">{{ $car->capacity }} Penumpang</p>
                            </td>
                            <td class="py-3.5 px-5 font-bold text-slate-900">
                                Rp {{ number_format($car->price_per_day, 0, ',', '.') }}
                            </td>
                            <td class="py-3.5 px-5">
                                @if ($car->status === 'available')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                                        Tersedia
                                    </span>
                                @elseif ($car->status === 'rented')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                                        Sedang Disewa
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-rose-100 text-rose-800">
                                        Perawatan
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-5 text-right">
                                <div class="inline-flex items-center gap-1.5">
                                    <a href="{{ route('admin.cars.show', $car) }}" 
                                       class="p-1.5 text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded transition-colors"
                                       title="Lihat Detail">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </a>
                                    <a href="{{ route('admin.cars.edit', $car) }}" 
                                       class="p-1.5 text-teal-600 hover:text-teal-900 hover:bg-teal-50 rounded transition-colors"
                                       title="Ubah Data">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </a>
                                    <form action="{{ route('admin.cars.destroy', $car) }}" 
                                          method="POST" 
                                          class="inline-block" 
                                          onsubmit="return confirm('Apakah Anda yakin ingin menghapus armada {{ $car->name }} ({{ $car->plate_number }})? Tindakan ini tidak dapat dibatalkan.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" 
                                                class="p-1.5 text-rose-600 hover:text-rose-900 hover:bg-rose-50 rounded transition-colors"
                                                title="Hapus Kendaraan">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-10 text-center text-slate-500">
                                <p class="font-medium">Tidak ada kendaraan yang sesuai dengan kriteria pencarian.</p>
                                <a href="{{ route('admin.cars.index') }}" class="text-xs text-teal-600 hover:underline mt-1 inline-block">Reset filter</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($cars->hasPages())
            <div class="p-4 border-t border-slate-200">
                {{ $cars->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
