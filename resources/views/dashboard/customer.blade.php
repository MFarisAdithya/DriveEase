@extends('layouts.portal')

@section('title', 'Katalog Armada Tersedia - DriveEase')

@section('content')
<div class="space-y-6">
    <!-- Welcome Banner -->
    <div class="bg-white rounded-xl p-6 border border-slate-200 shadow-sm">
        <h2 class="text-xl font-bold text-slate-900">Selamat Datang, {{ auth()->user()->name }}!</h2>
        <p class="text-sm text-slate-500 mt-1">
            Pilihan armada kendaraan rental prima yang siap menemani perjalanan dinas, keluarga, atau liburan Anda.
        </p>
    </div>

    <!-- Section Header -->
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-base font-bold text-slate-900">Armada Siap Disewa Hari Ini</h3>
            <p class="text-xs text-slate-500 mt-0.5">Semua unit bergaransi kondisi bersih, terawat, dan siap jalan.</p>
        </div>
        <span class="text-xs font-semibold px-3 py-1 bg-emerald-50 text-emerald-700 rounded-full border border-emerald-200">
            {{ $availableCars->count() }} Unit Tersedia
        </span>
    </div>

    <!-- Available Cars Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse ($availableCars as $car)
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden flex flex-col hover:border-slate-300 transition-colors">
                <!-- Card Header -->
                <div class="p-5 border-b border-slate-100 flex items-start justify-between">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-teal-700">
                            {{ $car->brand }} &bull; {{ $car->carType?->name ?? 'Mobil' }}
                        </span>
                        <h4 class="text-base font-bold text-slate-900 mt-0.5">{{ $car->name }}</h4>
                    </div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800">
                        Tersedia
                    </span>
                </div>

                <!-- Specs -->
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

                <!-- Card Footer (Pricing & Note) -->
                <div class="p-5 bg-slate-50/70 border-t border-slate-100 flex items-center justify-between">
                    <div>
                        <span class="text-[10px] text-slate-400 uppercase font-semibold block">Tarif Sewa</span>
                        <div class="flex items-baseline gap-1">
                            <span class="text-base font-extrabold text-slate-900">
                                Rp {{ number_format($car->price_per_day, 0, ',', '.') }}
                            </span>
                            <span class="text-[11px] text-slate-500">/ hari</span>
                        </div>
                    </div>
                    <span class="text-[11px] font-medium text-slate-500 bg-white px-2.5 py-1 rounded border border-slate-200">
                        Plat: {{ $car->plate_number }}
                    </span>
                </div>
            </div>
        @empty
            <div class="col-span-full bg-white rounded-xl p-12 text-center border border-slate-200 text-slate-500">
                <svg class="w-12 h-12 text-slate-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 19H5V8h14m-3-5H8L5 6v13a2 2 0 002 2h10a2 2 0 002-2V6l-3-3z"/>
                </svg>
                <p class="font-semibold text-slate-700">Saat ini belum ada armada yang tersedia.</p>
                <p class="text-xs text-slate-400 mt-1">Silakan periksa kembali beberapa saat lagi.</p>
            </div>
        @endforelse
    </div>
</div>
@endsection
