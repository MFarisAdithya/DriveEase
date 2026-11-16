@extends('layouts.portal')

@section('title', 'Katalog mobil - DriveEase')

@section('content')
<div class="space-y-6">
    <!-- Catalog Header -->
    <header class="bg-brand-soft/45 border border-line rounded-xl p-5 sm:p-6">
        <h1 class="text-[28px] leading-[36px] sm:text-[36px] sm:leading-[44px] font-bold tracking-tight text-ink">
            Pilih mobil untuk perjalananmu
        </h1>
        <p class="mt-1.5 text-base leading-6 text-muted max-w-2xl">
            Bandingkan tarif harian, kapasitas, dan spesifikasi mobil.
        </p>
    </header>

    <!-- Search & Filter Bar -->
    <section aria-label="Pencarian dan filter mobil" class="bg-surface p-4 sm:p-5 rounded-xl border border-line shadow-xs">
        <form action="{{ route('cars.index') }}" method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
            <div class="md:col-span-4">
                <label for="search" class="block text-sm font-semibold text-ink mb-1.5">
                    Nama atau merek
                </label>
                <div class="relative">
                    <svg class="w-4 h-4 text-muted absolute left-3.5 top-3.5 pointer-events-none" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text"
                           id="search"
                           name="search"
                           value="{{ request('search') }}"
                           placeholder="Contoh: Avanza atau Honda"
                           class="w-full h-11 pl-10 pr-3.5 text-sm rounded-lg border border-field-line bg-surface text-ink focus:outline-none">
                </div>
            </div>

            <div class="md:col-span-3">
                <label for="car_type_id" class="block text-sm font-semibold text-ink mb-1.5">
                    Kategori
                </label>
                <select id="car_type_id"
                        name="car_type_id"
                        class="w-full h-11 px-3.5 text-sm rounded-lg border border-field-line bg-surface text-ink focus:outline-none">
                    <option value="">Semua kategori</option>
                    @foreach ($carTypes as $type)
                        <option value="{{ $type->id }}" {{ (string) request('car_type_id') === (string) $type->id ? 'selected' : '' }}>
                            {{ $type->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-2">
                <label for="transmission" class="block text-sm font-semibold text-ink mb-1.5">
                    Transmisi
                </label>
                <select id="transmission"
                        name="transmission"
                        class="w-full h-11 px-3.5 text-sm rounded-lg border border-field-line bg-surface text-ink focus:outline-none">
                    <option value="">Semua transmisi</option>
                    <option value="automatic" {{ request('transmission') === 'automatic' ? 'selected' : '' }}>Otomatis</option>
                    <option value="manual" {{ request('transmission') === 'manual' ? 'selected' : '' }}>Manual</option>
                </select>
            </div>

            <div class="md:col-span-3 flex flex-wrap sm:flex-nowrap items-center gap-2">
                <button type="submit"
                        class="flex-1 min-h-11 px-5 bg-action hover:bg-action-hover text-brand-navy text-sm font-bold rounded-lg transition-colors cursor-pointer text-center whitespace-nowrap">
                    Cari mobil
                </button>
                @if (request()->anyFilled(['search', 'car_type_id', 'transmission']))
                    <a href="{{ route('cars.index') }}"
                       class="min-h-11 px-4 inline-flex items-center justify-center rounded-lg border border-field-line bg-surface hover:bg-surface-raised text-sm font-semibold text-ink transition-colors whitespace-nowrap">
                        Hapus filter
                    </a>
                @endif
            </div>
        </form>
    </section>

    <!-- Results Summary & Active Filters -->
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm font-medium text-muted">
            <strong class="font-bold text-ink tabular-nums">{{ $cars->total() }}</strong> mobil ditemukan
        </p>

        @if (request()->anyFilled(['search', 'car_type_id', 'transmission']))
            <div class="flex flex-wrap items-center gap-2 text-xs">
                @if (request()->filled('search'))
                    <span class="inline-flex items-center px-2.5 py-1 rounded-md bg-brand-soft/60 text-brand-navy font-semibold border border-line">
                        Kata kunci: "{{ request('search') }}"
                    </span>
                @endif
                @if (request()->filled('car_type_id'))
                    @php
                        $activeType = $carTypes->firstWhere('id', (int) request('car_type_id'));
                    @endphp
                    @if ($activeType)
                        <span class="inline-flex items-center px-2.5 py-1 rounded-md bg-brand-soft/60 text-brand-navy font-semibold border border-line">
                            Kategori: {{ $activeType->name }}
                        </span>
                    @endif
                @endif
                @if (request()->filled('transmission'))
                    <span class="inline-flex items-center px-2.5 py-1 rounded-md bg-brand-soft/60 text-brand-navy font-semibold border border-line">
                        Transmisi: {{ request('transmission') === 'automatic' ? 'Otomatis' : 'Manual' }}
                    </span>
                @endif
            </div>
        @endif
    </div>

    <!-- Vehicle Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse ($cars as $car)
            @php
                $modelPhoto = collect(config('catalog.photos'))->first(
                    fn ($file, $model) => str_starts_with(strtolower(trim($car->name)).' ', $model.' ')
                );
                $hasUnitPhoto = $car->image && Storage::disk('public')->exists($car->image);
                $statusMeta = match ($car->status) {
                    'available' => [
                        'label' => 'Tersedia',
                        'classes' => 'bg-success-surface text-success border-success-line',
                    ],
                    'rented' => [
                        'label' => 'Sedang disewa',
                        'classes' => 'bg-warning-surface text-warning border-warning-line',
                    ],
                    'maintenance' => [
                        'label' => 'Dalam perawatan',
                        'classes' => 'bg-danger-surface text-danger border-danger-line',
                    ],
                    default => [
                        'label' => 'Belum diketahui',
                        'classes' => 'bg-surface-raised text-muted border-line',
                    ],
                };
            @endphp

            <article class="flex flex-col overflow-hidden rounded-xl border border-line bg-surface shadow-xs hover:border-field-line transition-colors">
                <!-- Media 16:9 -->
                @if ($hasUnitPhoto || $modelPhoto)
                    <figure class="relative aspect-[16/9] bg-surface-raised border-b border-line overflow-hidden">
                        <img src="{{ $hasUnitPhoto ? Storage::disk('public')->url($car->image) : asset('images/models/'.$modelPhoto) }}"
                             alt="{{ $car->name }} ({{ $car->brand }})"
                             loading="lazy"
                             onerror="this.classList.add('hidden'); this.parentElement.querySelector('[data-photo-fallback]').classList.remove('hidden');"
                             class="w-full h-full object-cover">
                        <div data-photo-fallback class="hidden w-full h-full flex flex-col items-center justify-center p-4 text-center bg-surface-raised">
                            <span class="text-xs font-semibold text-muted">{{ $car->brand }} &middot; {{ $car->carType?->name ?? 'Mobil' }}</span>
                            <span class="text-base font-bold text-ink mt-1">{{ $car->name }}</span>
                        </div>
                        @unless ($hasUnitPhoto)
                            <figcaption class="absolute bottom-2 right-2 bg-brand-navy/85 text-white px-2.5 py-1 rounded text-xs font-medium">
                                Foto model
                            </figcaption>
                        @endunless
                    </figure>
                @else
                    <div class="aspect-[16/9] bg-surface-raised border-b border-line flex flex-col items-center justify-center p-4 text-center">
                        <span class="text-xs font-semibold text-muted">{{ $car->brand }} &middot; {{ $car->carType?->name ?? 'Mobil' }}</span>
                        <span class="text-base font-bold text-ink mt-1">{{ $car->name }}</span>
                    </div>
                @endif

                <!-- Card Body -->
                <div class="p-5 flex-1 flex flex-col justify-between gap-4">
                    <div>
                        <!-- Category/Year & Status -->
                        <div class="flex items-center justify-between gap-2 flex-wrap">
                            <p class="text-sm font-medium text-muted">
                                {{ $car->carType?->name ?? 'Mobil' }} &middot; {{ $car->model_year }}
                            </p>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $statusMeta['classes'] }}">
                                @if ($car->status === 'available')
                                    <svg class="w-3.5 h-3.5 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                @elseif ($car->status === 'rented')
                                    <svg class="w-3.5 h-3.5 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                @else
                                    <svg class="w-3.5 h-3.5 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5 19h14a2 2 0 001.732-3l-7-12a2 2 0 00-3.464 0l-7 12A2 2 0 005 19z"/>
                                    </svg>
                                @endif
                                {{ $statusMeta['label'] }}
                            </span>
                        </div>

                        <!-- Vehicle Name -->
                        <h2 class="mt-2 text-[20px] leading-[28px] font-semibold text-ink">
                            <a href="{{ route('cars.show', $car) }}" class="hover:underline">
                                {{ $car->name }}
                            </a>
                        </h2>

                        <!-- Core Specs -->
                        <p class="mt-1 text-sm leading-5 text-muted">
                            {{ $car->transmission === 'automatic' ? 'Otomatis' : 'Manual' }} &middot; {{ $car->capacity }} kursi
                        </p>
                    </div>

                    <!-- Comparison Group: Price & Action -->
                    <div class="pt-4 border-t border-line flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs text-muted">Tarif harian</p>
                            <p class="text-ink">
                                <strong class="text-[24px] leading-[32px] font-bold tabular-nums">Rp{{ number_format($car->price_per_day, 0, ',', '.') }}</strong>
                                <span class="text-sm font-normal text-muted">/ hari</span>
                            </p>
                        </div>
                        <a href="{{ route('cars.show', $car) }}"
                           class="min-h-11 px-4 inline-flex items-center justify-center rounded-lg bg-brand-navy text-white hover:opacity-95 text-sm font-semibold transition-opacity shrink-0">
                            Lihat mobil
                        </a>
                    </div>
                </div>
            </article>
        @empty
            <div class="col-span-full bg-surface rounded-xl p-10 sm:p-12 text-center border border-line">
                <svg class="w-12 h-12 text-muted mx-auto mb-3" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 19H5V8h14m-3-5H8L5 6v13a2 2 0 002 2h10a2 2 0 002-2V6l-3-3z"/>
                </svg>
                <p class="text-lg font-semibold text-ink">Mobil belum ditemukan</p>
                <p class="text-sm text-muted mt-1">Coba ubah pencarian atau hapus filter.</p>
                <a href="{{ route('cars.index') }}"
                   class="mt-4 min-h-11 px-4 inline-flex items-center justify-center rounded-lg border border-field-line bg-surface hover:bg-surface-raised text-sm font-semibold text-ink transition-colors">
                    Hapus filter
                </a>
            </div>
        @endforelse
    </div>

    <!-- Photo Credits & Pagination -->
    <div class="flex flex-col gap-4 pt-2">
        <div>
            <a class="text-xs text-muted underline hover:text-ink inline-flex items-center min-h-8" href="{{ asset('images/models/kredit.html') }}">
                Kredit foto model
            </a>
        </div>

        @if ($cars->hasPages())
            <div class="bg-surface rounded-xl p-4 border border-line">
                {{ $cars->links('layouts.pagination') }}
            </div>
        @endif
    </div>
</div>
@endsection
