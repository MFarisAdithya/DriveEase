@extends('layouts.portal')

@section('title', $car->name . ' - Rincian Armada | DriveEase')

@section('content')
<div class="max-w-5xl mx-auto space-y-8">
    <!-- Breadcrumb / Back Link -->
    <div>
        <a href="{{ route('cars.index') }}" class="inline-flex items-center gap-2 text-xs font-semibold text-slate-500 hover:text-slate-900 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Kembali ke Katalog Armada
        </a>
    </div>

    <!-- Main Detail Card -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <!-- Header -->
        <div class="p-8 border-b border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div>
                <div class="flex items-center gap-3 flex-wrap">
                    <span class="text-xs font-bold uppercase tracking-wider text-teal-700 bg-teal-50 px-2.5 py-1 rounded border border-teal-100">
                        {{ $car->brand }} &bull; {{ $car->carType?->name ?? 'Kategori Umum' }}
                    </span>
                    @if ($car->status === 'available')
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                            Unit Siap Operasional
                        </span>
                    @elseif ($car->status === 'rented')
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">
                            Sedang Dalam Masa Sewa
                        </span>
                    @else
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-rose-100 text-rose-800">
                            Dalam Pemeliharaan Rutin
                        </span>
                    @endif
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 mt-2">
                    {{ $car->name }}
                </h1>
                <p class="text-xs text-slate-500 mt-1">
                    Nomor Identifikasi Armada: <span class="font-mono font-bold text-slate-700">{{ $car->plate_number }}</span>
                </p>
            </div>

            <!-- Pricing Box -->
            <div class="bg-slate-50 p-5 rounded-xl border border-slate-200 text-left md:text-right shrink-0">
                <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block">Tarif Sewa Harian</span>
                <div class="flex items-baseline md:justify-end gap-1 mt-0.5">
                    <span class="text-2xl sm:text-3xl font-extrabold text-slate-900">
                        Rp {{ number_format($car->price_per_day, 0, ',', '.') }}
                    </span>
                    <span class="text-xs text-slate-500 font-medium">/ 24 jam</span>
                </div>
                <p class="text-[11px] text-slate-400 mt-1">Tarif lepas kunci, belum termasuk supir & BBM</p>
            </div>
        </div>

        <!-- Specifications Body -->
        <div class="p-8 space-y-8">
            <!-- Specs Grid -->
            <div>
                <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4">Spesifikasi Kendaraan</h2>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-100">
                        <span class="text-[11px] text-slate-400 uppercase font-semibold block">Tahun Produksi</span>
                        <span class="text-sm font-bold text-slate-900 block mt-1">{{ $car->model_year }}</span>
                    </div>
                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-100">
                        <span class="text-[11px] text-slate-400 uppercase font-semibold block">Tipe Transmisi</span>
                        <span class="text-sm font-bold text-slate-900 block mt-1 capitalize">{{ $car->transmission }}</span>
                    </div>
                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-100">
                        <span class="text-[11px] text-slate-400 uppercase font-semibold block">Kapasitas Penumpang</span>
                        <span class="text-sm font-bold text-slate-900 block mt-1">{{ $car->capacity }} Penumpang</span>
                    </div>
                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-100">
                        <span class="text-[11px] text-slate-400 uppercase font-semibold block">Kategori Model</span>
                        <span class="text-sm font-bold text-slate-900 block mt-1">{{ $car->carType?->name ?? '-' }}</span>
                    </div>
                </div>
            </div>

            <!-- Description -->
            <div>
                <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Deskripsi & Kelengkapan Fasilitas</h2>
                <div class="p-5 rounded-xl bg-slate-50 border border-slate-100 text-sm text-slate-700 leading-relaxed">
                    {{ $car->description ?: 'Kendaraan dalam kondisi prima, servis rutin berkala terjamin, serta dilengkapi surat-surat resmi operasional.' }}
                </div>
            </div>

            <!-- Operational Notice -->
            <div class="p-4 rounded-xl bg-blue-50 border border-blue-200 flex items-start gap-3 text-xs text-blue-900">
                <svg class="w-5 h-5 text-blue-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div class="leading-relaxed">
                    <strong class="font-semibold block mb-0.5">Informasi Reservasi & Ketersediaan</strong>
                    Status ketersediaan di atas menunjukkan kesiapan operasional unit. Pemilihan tanggal sewa dan penguncian jadwal reservasi (*booking system*) akan diaktifkan pada pembaruan tahap berikutnya (Progress 2).
                </div>
            </div>
        </div>
    </div>

    <!-- Related Vehicles in Same Category -->
    @if ($relatedCars->count() > 0)
        <div>
            <h2 class="text-base font-bold text-slate-900 mb-4">Armada Lain di Kategori {{ $car->carType?->name }}</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                @foreach ($relatedCars as $rel)
                    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm hover:shadow-md transition-shadow">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-teal-700 block">{{ $rel->brand }}</span>
                        <h3 class="text-sm font-bold text-slate-900 mt-1">{{ $rel->name }}</h3>
                        <div class="flex items-center justify-between mt-4 pt-3 border-t border-slate-100">
                            <span class="text-xs font-extrabold text-slate-900">
                                Rp {{ number_format($rel->price_per_day, 0, ',', '.') }} <span class="text-[10px] font-normal text-slate-500">/ hari</span>
                            </span>
                            <a href="{{ route('cars.show', $rel) }}" class="text-xs font-semibold text-teal-700 hover:underline">
                                Rincian &rarr;
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection
