@extends('layouts.portal')

@section('title', 'Katalog Armada Kendaraan - DriveEase')

@section('content')
<div class="space-y-6 bg-[#F2EDE4] p-4 text-[#30252A] sm:p-8">
    <header class="border-l-4 border-[#AB9671] pl-4 py-2">
        <h1 class="font-serif text-4xl font-normal tracking-tight text-[#30252A]">Katalog kendaraan</h1>
        <p class="mt-2 text-sm text-slate-600">Bandingkan kapasitas, transmisi, dan tarif sewa harian.</p>
    </header>

    <!-- Search & Filter Bar -->
    <div class="pb-4 border-b border-slate-200">
        <form action="{{ route('cars.index') }}" method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3">
            <!-- Search -->
            <div class="lg:col-span-5 relative">
                <input type="text" 
                       aria-label="Cari kendaraan" name="search" 
                       value="{{ request('search') }}" 
                       placeholder="Cari nama mobil atau merek (misal: Innova, Honda)..." 
                       class="w-full pl-9 pr-3 py-2.5 text-xs rounded-lg border border-slate-300 focus:outline-none focus:border-[#552C3A] focus:ring-1 focus:ring-[#552C3A]">
                <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>

            <!-- Category -->
            <div class="lg:col-span-3">
                <select aria-label="Kategori kendaraan" name="car_type_id" class="w-full text-xs py-2.5 px-3 rounded-lg border border-slate-300 focus:outline-none focus:border-[#552C3A]">
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
                <select aria-label="Transmisi" name="transmission" class="w-full text-xs py-2.5 px-3 rounded-lg border border-slate-300 focus:outline-none focus:border-[#552C3A]">
                    <option value="">Semua Transmisi</option>
                    <option value="automatic" {{ request('transmission') === 'automatic' ? 'selected' : '' }}>Otomatis</option>
                    <option value="manual" {{ request('transmission') === 'manual' ? 'selected' : '' }}>Manual</option>
                </select>
            </div>

            <!-- Submit Button -->
            <div class="lg:col-span-2 flex items-center gap-2">
                <button type="submit" class="w-full py-2.5 px-4 bg-[#552C3A] hover:bg-[#3C202B] text-white text-xs font-semibold rounded-lg transition-colors shadow-sm text-center">
                    Terapkan
                </button>
                @if(request()->hasAny(['search', 'car_type_id', 'transmission']))
                    <a href="{{ route('cars.index') }}" class="p-2.5 text-xs text-rose-600 hover:bg-rose-50 rounded-lg border border-rose-200 transition-colors shrink-0" title="Hapus Filter">
                        Hapus
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Vehicle Cards Grid -->
    <div>
        <div class="flex items-center justify-between mb-4">
            <p class="text-xs text-slate-500 font-medium">
                Menampilkan <strong class="text-[#30252A]">{{ $cars->total() }}</strong> unit armada terdaftar
            </p>
        </div>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($cars as $car)
                <article class="flex flex-col overflow-hidden border border-[#D4C8B7] bg-[#FFFDF9]">
                    <div class="aspect-[16/9] overflow-hidden bg-[#E6DED0]">
                        @if ($car->image)
                            <img src="{{ Storage::disk('public')->url($car->image) }}" alt="{{ $car->name }}" loading="lazy" class="h-full w-full object-cover">
                        @else
                            <div class="flex h-full items-center justify-center text-sm italic text-[#665B51]">Foto belum tersedia</div>
                        @endif
                    </div>
                    <div class="p-5">
                        <p class="text-xs font-medium text-[#552C3A]">{{ $car->carType?->name ?? 'Mobil' }} &middot; {{ $car->model_year }}</p>
                        <h2 class="mt-1 font-serif text-2xl font-normal tracking-tight">
                            <a class="hover:underline" href="{{ route('cars.show', $car) }}">{{ $car->name }}</a>
                        </h2>
                        <p class="mt-1 text-sm text-slate-600">{{ $car->brand }} &middot; {{ $car->transmission === 'automatic' ? 'Otomatis' : 'Manual' }} &middot; {{ $car->capacity }} kursi</p>
                        @if ($car->description)
                            <p class="mt-2 max-w-xl text-sm text-slate-600">{{ $car->description }}</p>
                        @endif
                    </div>
                    <div class="mt-auto border-t border-[#D4C8B7] px-5 py-4">
                        <p class="text-sm text-slate-600">{{ ['available' => 'Tersedia', 'rented' => 'Sedang disewa', 'maintenance' => 'Perawatan'][$car->status] ?? 'Belum diketahui' }}</p>
                        <p class="mt-1"><strong class="text-xl tabular-nums">Rp {{ number_format($car->price_per_day, 0, ',', '.') }}</strong> / hari</p>
                        <a class="mt-2 inline-block text-sm font-medium text-[#552C3A] underline underline-offset-4" href="{{ route('cars.show', $car) }}">Lihat detail</a>
                    </div>
                </article>
            @empty
                <div class="col-span-full bg-white rounded-xl p-12 text-center border border-slate-200 text-slate-500">
                    <svg class="w-12 h-12 text-slate-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 19H5V8h14m-3-5H8L5 6v13a2 2 0 002 2h10a2 2 0 002-2V6l-3-3z"/>
                    </svg>
                    <p class="font-bold text-slate-800">Tidak ada armada yang sesuai dengan pencarian Anda.</p>
                    <p class="text-xs text-slate-400 mt-1">Coba gunakan kata kunci lain atau hapus filter kategori.</p>
                    <a href="{{ route('cars.index') }}" class="mt-4 inline-flex items-center px-4 py-2 text-xs font-semibold text-teal-700 bg-teal-50 border border-teal-200 rounded-lg hover:bg-teal-100 transition-colors">
                        Hapus semua filter
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
