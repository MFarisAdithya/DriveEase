@extends('layouts.portal')

@section('title', 'Katalog Armada Kendaraan - DriveEase')

@section('content')
<div class="space-y-8">
    <!-- Header Hero Banner -->
    <div class="bg-slate-900 rounded-2xl p-8 text-white relative overflow-hidden shadow-lg border border-slate-800">
        <div class="relative z-10 max-w-2xl">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-teal-500/20 text-teal-300 border border-teal-500/30 mb-3">
                Katalog Resmi DriveEase
            </span>
            <h1 class="text-3xl font-extrabold tracking-tight sm:text-4xl">
                Temukan Armada Terbaik untuk Perjalanan Anda
            </h1>
            <p class="mt-2 text-sm text-slate-300 leading-relaxed">
                Pilihan kendaraan sewa terawat dengan harga transparan, transmisi manual dan otomatis, serta kapasitas sesuai kebutuhan Anda.
            </p>
        </div>
        <div class="absolute -right-12 -bottom-12 w-64 h-64 bg-teal-600/10 rounded-full blur-3xl pointer-events-none"></div>
    </div>

    <!-- Search & Filter Bar -->
    <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
        <form action="{{ route('cars.index') }}" method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3">
            <!-- Search -->
            <div class="lg:col-span-5 relative">
                <input type="text" 
                       name="search" 
                       value="{{ request('search') }}" 
                       placeholder="Cari nama mobil atau merek (misal: Innova, Honda)..." 
                       class="w-full pl-9 pr-3 py-2.5 text-xs rounded-lg border border-slate-300 focus:outline-none focus:border-teal-600 focus:ring-1 focus:ring-teal-500">
                <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>

            <!-- Category -->
            <div class="lg:col-span-3">
                <select name="car_type_id" class="w-full text-xs py-2.5 px-3 rounded-lg border border-slate-300 focus:outline-none focus:border-teal-600">
                    <option value="">Semua Kategori</option>
                    @foreach ($carTypes as $type)
                        <option value="{{ $type->id }}" {{ request('car_type_id') == $type->id ? 'selected' : '' }}>
                            {{ $type->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Transmission -->
            <div class="lg:col-span-2">
                <select name="transmission" class="w-full text-xs py-2.5 px-3 rounded-lg border border-slate-300 focus:outline-none focus:border-teal-600">
                    <option value="">Semua Transmisi</option>
                    <option value="automatic" {{ request('transmission') === 'automatic' ? 'selected' : '' }}>Automatic</option>
                    <option value="manual" {{ request('transmission') === 'manual' ? 'selected' : '' }}>Manual</option>
                </select>
            </div>

            <!-- Submit Button -->
            <div class="lg:col-span-2 flex items-center gap-2">
                <button type="submit" class="w-full py-2.5 px-4 bg-teal-600 hover:bg-teal-700 text-white text-xs font-semibold rounded-lg transition-colors shadow-sm text-center">
                    Terapkan
                </button>
                @if(request()->hasAny(['search', 'car_type_id', 'transmission']))
                    <a href="{{ route('cars.index') }}" class="p-2.5 text-xs text-rose-600 hover:bg-rose-50 rounded-lg border border-rose-200 transition-colors shrink-0" title="Hapus Filter">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Vehicle Cards Grid -->
    <div>
        <div class="flex items-center justify-between mb-4">
            <p class="text-xs text-slate-500 font-medium">
                Menampilkan <strong class="text-slate-900">{{ $cars->total() }}</strong> unit armada terdaftar
            </p>
            <span class="text-[11px] text-slate-400">
                Status ketersediaan menggambarkan kesiapan operasional unit
            </span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse ($cars as $car)
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden flex flex-col hover:shadow-md hover:border-slate-300 transition-all">
                    <!-- Top Badge & Header -->
                    <div class="p-5 border-b border-slate-100 flex items-start justify-between">
                        <div>
                            <span class="text-xs font-bold uppercase tracking-wider text-teal-700">
                                {{ $car->brand }} &bull; {{ $car->carType?->name ?? 'Mobil' }}
                            </span>
                            <h3 class="text-base font-bold text-slate-900 mt-0.5">
                                <a href="{{ route('cars.show', $car) }}" class="hover:text-teal-700 transition-colors">
                                    {{ $car->name }}
                                </a>
                            </h3>
                        </div>

                        <!-- Operational Status Badge -->
                        @if ($car->status === 'available')
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800">
                                Tersedia
                            </span>
                        @elseif ($car->status === 'rented')
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-800">
                                Sedang Disewa
                            </span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-rose-100 text-rose-800">
                                Perawatan
                            </span>
                        @endif
                    </div>

                    <!-- Technical Specs -->
                    <div class="p-5 flex-1 space-y-4">
                        <div class="grid grid-cols-3 gap-2 text-center">
                            <div class="p-2.5 rounded-lg bg-slate-50 border border-slate-100">
                                <span class="text-[10px] text-slate-400 uppercase font-semibold block">Tahun</span>
                                <span class="text-xs font-bold text-slate-700 block mt-0.5">{{ $car->model_year }}</span>
                            </div>
                            <div class="p-2.5 rounded-lg bg-slate-50 border border-slate-100">
                                <span class="text-[10px] text-slate-400 uppercase font-semibold block">Transmisi</span>
                                <span class="text-xs font-bold text-slate-700 block mt-0.5 capitalize">{{ $car->transmission }}</span>
                            </div>
                            <div class="p-2.5 rounded-lg bg-slate-50 border border-slate-100">
                                <span class="text-[10px] text-slate-400 uppercase font-semibold block">Kapasitas</span>
                                <span class="text-xs font-bold text-slate-700 block mt-0.5">{{ $car->capacity }} Seat</span>
                            </div>
                        </div>

                        @if ($car->description)
                            <p class="text-xs text-slate-600 line-clamp-2 leading-relaxed">
                                {{ $car->description }}
                            </p>
                        @endif
                    </div>

                    <!-- Footer Pricing & Link to Detail -->
                    <div class="p-5 bg-slate-50/70 border-t border-slate-100 flex items-center justify-between">
                        <div>
                            <span class="text-[10px] text-slate-400 uppercase font-semibold block">Tarif Sewa Harian</span>
                            <div class="flex items-baseline gap-1">
                                <span class="text-lg font-extrabold text-slate-900">
                                    Rp {{ number_format($car->price_per_day, 0, ',', '.') }}
                                </span>
                                <span class="text-xs text-slate-500">/ hari</span>
                            </div>
                        </div>

                        <a href="{{ route('cars.show', $car) }}" class="inline-flex items-center gap-1.5 px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold rounded-lg transition-colors shadow-sm">
                            Lihat Detail
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                    </div>
                </div>
            @empty
                <div class="col-span-full bg-white rounded-xl p-12 text-center border border-slate-200 text-slate-500">
                    <svg class="w-12 h-12 text-slate-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 19H5V8h14m-3-5H8L5 6v13a2 2 0 002 2h10a2 2 0 002-2V6l-3-3z"/>
                    </svg>
                    <p class="font-bold text-slate-800">Tidak ada armada yang sesuai dengan pencarian Anda.</p>
                    <p class="text-xs text-slate-400 mt-1">Coba gunakan kata kunci lain atau hapus filter kategori.</p>
                    <a href="{{ route('cars.index') }}" class="mt-4 inline-flex items-center px-4 py-2 text-xs font-semibold text-teal-700 bg-teal-50 border border-teal-200 rounded-lg hover:bg-teal-100 transition-colors">
                        Reset Semua Filter
                    </a>
                </div>
            @endforelse
        </div>

        <!-- Pagination -->
        @if ($cars->hasPages())
            <div class="mt-8 bg-white rounded-xl p-4 border border-slate-200 shadow-sm">
                {{ $cars->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
