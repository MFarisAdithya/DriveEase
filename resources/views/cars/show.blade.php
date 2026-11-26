@extends('layouts.portal')

@section('title', $car->name . ' - Rincian Armada | DriveEase')

@section('content')
<div class="max-w-5xl mx-auto space-y-8">
    <!-- Breadcrumb / Back Link -->
    <div>
        <a href="{{ route('cars.index', $catalogFilters) }}" class="inline-flex items-center gap-2 text-xs font-semibold text-muted hover:text-ink transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Kembali ke Katalog Armada
        </a>
    </div>

    <!-- Main Detail Card -->
    <div class="bg-surface rounded-2xl border border-line shadow-sm overflow-hidden">
        @php
            $photo = $car->resolvePhoto();
        @endphp
        @if ($photo['url'])
            <figure class="relative aspect-[16/9] max-h-[420px] w-full bg-surface-raised border-b border-line overflow-hidden">
                <img src="{{ $photo['url'] }}" alt="{{ $car->name }}" class="w-full h-full object-cover">
            </figure>
        @endif

        <!-- Header -->
        <div class="p-8 border-b border-line flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div>
                <div class="flex items-center gap-3 flex-wrap">
                    <span class="text-xs font-bold uppercase tracking-wider text-link bg-surface-hover px-2.5 py-1 rounded border border-focus">
                        {{ $car->brand }} &bull; {{ $car->carType?->name ?? 'Kategori Umum' }}
                    </span>
                    @if ($car->status === 'available')
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-success-surface text-success">
                            Unit Siap Operasional
                        </span>
                    @elseif ($car->status === 'rented')
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-warning-surface text-warning">
                            Sedang Dalam Masa Sewa
                        </span>
                    @else
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-danger-surface text-danger">
                            Dalam Pemeliharaan Rutin
                        </span>
                    @endif
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-ink mt-2">
                    {{ $car->name }}
                </h1>
                <p class="text-xs text-muted mt-1">
                    Nomor Identifikasi Armada: <span class="font-mono font-bold text-ink">{{ $car->plate_number }}</span>
                </p>
            </div>

            <!-- Pricing Box -->
            <div class="bg-surface-raised p-5 rounded-xl border border-line text-left md:text-right shrink-0">
                <span class="text-[11px] font-semibold text-muted uppercase tracking-wider block">Tarif Sewa Harian</span>
                <div class="flex items-baseline md:justify-end gap-1 mt-0.5">
                    <span class="text-2xl sm:text-3xl font-extrabold text-ink">
                        Rp {{ number_format($car->price_per_day, 0, ',', '.') }}
                    </span>
                    <span class="text-xs text-muted font-medium">/ 24 jam</span>
                </div>
                <p class="text-[11px] text-muted mt-1">Tarif lepas kunci, belum termasuk supir &amp; BBM</p>
            </div>
        </div>

        <!-- Specifications Body -->
        <div class="p-8 space-y-8">
            <!-- Specs Grid -->
            <div>
                <h2 class="text-xs font-bold text-muted uppercase tracking-wider mb-4">Spesifikasi Kendaraan</h2>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div class="p-4 rounded-xl bg-surface-raised border border-line">
                        <span class="text-[11px] text-muted uppercase font-semibold block">Tahun Produksi</span>
                        <span class="text-sm font-bold text-ink block mt-1">{{ $car->model_year }}</span>
                    </div>
                    <div class="p-4 rounded-xl bg-surface-raised border border-line">
                        <span class="text-[11px] text-muted uppercase font-semibold block">Tipe Transmisi</span>
                        <span class="text-sm font-bold text-ink block mt-1 capitalize">{{ $car->transmission === 'automatic' ? 'Otomatis' : 'Manual' }}</span>
                    </div>
                    <div class="p-4 rounded-xl bg-surface-raised border border-line">
                        <span class="text-[11px] text-muted uppercase font-semibold block">Kapasitas Penumpang</span>
                        <span class="text-sm font-bold text-ink block mt-1">{{ $car->capacity }} Penumpang</span>
                    </div>
                    <div class="p-4 rounded-xl bg-surface-raised border border-line">
                        <span class="text-[11px] text-muted uppercase font-semibold block">Kategori Model</span>
                        <span class="text-sm font-bold text-ink block mt-1">{{ $car->carType?->name ?? '-' }}</span>
                    </div>
                </div>
            </div>

            <!-- Description -->
            <div>
                <h2 class="text-xs font-bold text-muted uppercase tracking-wider mb-3">Deskripsi &amp; Kelengkapan Fasilitas</h2>
                <div class="p-5 rounded-xl bg-surface-raised border border-line text-sm text-ink leading-relaxed">
                    {{ $car->description ?: 'Deskripsi kendaraan belum tersedia.' }}
                </div>
            </div>

            <!-- Period Availability Checker (G-03, C-04) -->
            <div class="rounded-xl border border-line bg-surface-raised p-6 space-y-4">
                <div>
                    <h2 class="text-sm font-bold text-ink">Ketersediaan &amp; Estimasi Sewa</h2>
                    <p class="text-xs text-muted mt-0.5">Durasi sewa 1 hari = 24 jam sejak jadwal pengambilan dengan jeda persiapan 1 jam antarpenyewaan.</p>
                </div>

                <form method="GET" action="{{ route('cars.show', $car) }}" data-availability-form data-availability-url="{{ route('api.v1.availability') }}" data-car-id="{{ $car->id }}" class="grid grid-cols-1 sm:grid-cols-2 gap-3 items-end">
                    <div>
                        <label for="detail_pickup_at" class="block text-xs font-semibold text-ink mb-1">Waktu Pengambilan (WIB)</label>
                        <input id="detail_pickup_at" name="pickup_at" type="datetime-local" value="{{ request('pickup_at') }}" required class="w-full rounded-lg border border-field-line bg-surface p-2.5 text-sm">
                    </div>
                    <div>
                        <label for="detail_total_days" class="block text-xs font-semibold text-ink mb-1">Jumlah Hari</label>
                        <input id="detail_total_days" name="total_days" type="number" min="1" max="365" value="{{ request('total_days', 1) }}" required class="w-full rounded-lg border border-field-line bg-surface p-2.5 text-sm">
                    </div>
                </form>

                <div class="rounded-lg border border-line p-4 space-y-3 text-xs sm:text-sm" data-availability-result>
                    <p role="status" data-availability-status>
                        @if ($periodErrors->any())
                            {{ $periodErrors->first() }}
                        @elseif ($periodCheck && $periodCheck['available'])
                            Tersedia untuk periode yang dipilih.
                            Kembali {{ $periodCheck['pricing']['return_at']->format('d M Y H:i') }} WIB
                            · Tarif Rp {{ number_format($periodCheck['pricing']['daily_price'], 0, ',', '.') }}/hari
                            · Total Rp {{ number_format($periodCheck['pricing']['total_amount'], 0, ',', '.') }}.
                        @elseif ($periodCheck)
                            Tidak tersedia pada periode yang dipilih.
                        @else
                            Isi waktu pengambilan dan jumlah hari.
                        @endif
                    </p>
                    <div data-availability-action @if (! ($periodCheck['available'] ?? false)) hidden @endif>
                        <div class="flex justify-end">
                            @auth
                                @if (auth()->user()->isCustomer())
                                    @if (auth()->user()->canMakeReservation())
                                        <a data-availability-booking href="{{ route('customer.bookings.create', ['car_id' => $car->id, 'pickup_at' => request('pickup_at'), 'total_days' => request('total_days')]) }}" class="ui-button">Pesan Kendaraan Ini</a>
                                    @else
                                        <a href="{{ route('customer.verification.create') }}" class="ui-button ui-secondary">Lengkapi Identitas untuk Memesan</a>
                                    @endif
                                @elseif (auth()->user()->isAdmin())
                                    <a data-availability-booking href="{{ route('admin.bookings.create', ['car_id' => $car->id, 'pickup_at' => request('pickup_at'), 'total_days' => request('total_days')]) }}" class="ui-button">Buat Pemesanan Langsung</a>
                                @endif
                            @else
                                <a href="{{ route('login') }}" class="ui-button">Masuk untuk Memesan</a>
                            @endauth
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Related Vehicles in Same Category -->
    @if ($relatedCars->count() > 0)
        <div>
            <h2 class="text-base font-bold text-ink mb-4">Armada Lain di Kategori {{ $car->carType?->name }}</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                @foreach ($relatedCars as $rel)
                    <div class="bg-surface rounded-xl border border-line p-5 shadow-sm hover:shadow-md transition-shadow">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-link block">{{ $rel->brand }}</span>
                        <h3 class="text-sm font-bold text-ink mt-1">{{ $rel->name }}</h3>
                        <div class="flex items-center justify-between mt-4 pt-3 border-t border-line">
                            <span class="text-xs font-extrabold text-ink">
                                Rp {{ number_format($rel->price_per_day, 0, ',', '.') }} <span class="text-[10px] font-normal text-muted">/ hari</span>
                            </span>
                            <a href="{{ route('cars.show', array_merge(['car' => $rel], $catalogFilters)) }}" class="text-xs font-semibold text-link hover:underline">
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
