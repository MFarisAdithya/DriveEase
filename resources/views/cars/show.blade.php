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
                @if ($photo['is_model'])
                    <figcaption class="absolute bottom-3 right-3 rounded bg-white/95 px-2.5 py-1 text-xs font-medium text-ink shadow-xs">
                        Foto model
                    </figcaption>
                @endif
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
                    <h2 class="text-sm font-bold text-ink">Cek Ketersediaan &amp; Estimasi Biaya (WIB)</h2>
                    <p class="text-xs text-muted mt-0.5">Durasi sewa 1 hari = 24 jam sejak jadwal pengambilan dengan jeda persiapan 1 jam antarpenyewaan.</p>
                </div>

                <form method="GET" action="{{ route('cars.show', $car) }}" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                    @foreach (['search', 'car_type_id', 'transmission', 'page'] as $filterKey)
                        @if (request()->filled($filterKey))
                            <input type="hidden" name="{{ $filterKey }}" value="{{ request($filterKey) }}">
                        @endif
                    @endforeach
                    <div class="sm:col-span-6">
                        <label for="detail_pickup_at" class="block text-xs font-semibold text-ink mb-1">Waktu Pengambilan (WIB)</label>
                        <input id="detail_pickup_at" name="pickup_at" type="datetime-local" value="{{ request('pickup_at') }}" required class="w-full rounded-lg border border-field-line bg-surface p-2.5 text-sm">
                    </div>
                    <div class="sm:col-span-3">
                        <label for="detail_total_days" class="block text-xs font-semibold text-ink mb-1">Jumlah Hari</label>
                        <input id="detail_total_days" name="total_days" type="number" min="1" max="365" value="{{ request('total_days', 1) }}" required class="w-full rounded-lg border border-field-line bg-surface p-2.5 text-sm">
                    </div>
                    <div class="sm:col-span-3">
                        <button type="submit" class="w-full rounded-lg bg-action hover:bg-action-hover px-4 py-2.5 text-xs font-bold text-ink shadow-xs transition-colors">Periksa Jadwal</button>
                    </div>
                </form>

                @if ($periodErrors->any())
                    <ul role="alert" class="rounded-lg border border-danger-line bg-danger-surface p-3 text-xs text-danger space-y-1">
                        @foreach ($periodErrors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                @elseif ($periodCheck)
                    <div class="rounded-lg border p-4 text-xs sm:text-sm space-y-2 {{ $periodCheck['available'] ? 'border-success-line bg-success-surface text-success' : 'border-danger-line bg-danger-surface text-danger' }}">
                        <p class="font-bold">
                            {{ $periodCheck['available'] ? 'Tersedia untuk periode yang dipilih' : 'Tidak tersedia pada periode yang dipilih' }}
                        </p>
                        @if ($periodCheck['reason'])
                            <p>{{ $periodCheck['reason'] }}</p>
                        @endif
                        <p>
                            Jadwal: <strong>{{ $periodCheck['pricing']['pickup_at']->format('d M Y H:i') }} WIB</strong>
                            s.d. <strong>{{ $periodCheck['pricing']['return_at']->format('d M Y H:i') }} WIB</strong>
                            ({{ $periodCheck['pricing']['total_days'] }} hari &times; Rp {{ number_format($periodCheck['pricing']['daily_price'], 0, ',', '.') }})
                            &mdash; Total: <strong>Rp {{ number_format($periodCheck['pricing']['total_amount'], 0, ',', '.') }}</strong>
                        </p>

                        @if ($periodCheck['available'])
                            <div class="pt-2">
                                @auth
                                    @if (auth()->user()->isCustomer())
                                        @if (auth()->user()->canMakeReservation())
                                            @if (Route::has('customer.bookings.create'))
                                                <a href="{{ route('customer.bookings.create', ['car_id' => $car->id, 'pickup_at' => request('pickup_at'), 'total_days' => request('total_days')]) }}" class="inline-flex items-center rounded-lg bg-action hover:bg-action-hover px-4 py-2 text-xs font-bold text-ink shadow-xs">
                                                    Pesan Kendaraan Ini &rarr;
                                                </a>
                                            @endif
                                        @else
                                            <a href="{{ route('customer.verification.create') }}" class="inline-flex items-center rounded-lg border border-warning-line bg-warning-surface px-4 py-2 text-xs font-bold text-warning">
                                                Lengkapi Verifikasi Identitas untuk Memesan &rarr;
                                            </a>
                                        @endif
                                    @elseif (auth()->user()->isAdmin() && Route::has('admin.bookings.create'))
                                        <a href="{{ route('admin.bookings.create', ['car_id' => $car->id, 'pickup_at' => request('pickup_at'), 'total_days' => request('total_days')]) }}" class="inline-flex items-center rounded-lg bg-action hover:bg-action-hover px-4 py-2 text-xs font-bold text-ink shadow-xs">
                                            Buat Pemesanan Langsung &rarr;
                                        </a>
                                    @endif
                                @else
                                    <a href="{{ route('login') }}" class="inline-flex items-center rounded-lg bg-action hover:bg-action-hover px-4 py-2 text-xs font-bold text-ink shadow-xs">
                                        Masuk untuk Memesan &rarr;
                                    </a>
                                @endauth
                            </div>
                        @endif
                    </div>
                @endif
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
