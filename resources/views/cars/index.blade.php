@extends('layouts.portal')
@section('theme_class', 'theme-refresh')
@section('title', 'Katalog kendaraan — DriveEase')

@section('content')
<div class="space-y-6">
    <header class="rounded-xl bg-accent px-5 py-6 md:px-8 md:py-8">
        <p class="mb-2 text-sm font-semibold">Katalog kendaraan</p>
        <h1 class="max-w-2xl text-[28px] leading-9 font-bold tracking-tight md:text-4xl md:leading-[44px]">Pilih mobil untuk perjalananmu</h1>
        <p class="mt-3 text-sm md:text-base">Bandingkan kapasitas, transmisi, dan tarif harian dalam satu tempat.</p>
    </header>
    <form action="{{ route('cars.index') }}" method="GET" class="grid grid-cols-2 items-end gap-3 md:gap-4 lg:grid-cols-12">
        <div class="col-span-2 lg:col-span-5">
            <label class="ui-label" for="car-search">Nama atau merek</label>
            <input id="car-search" name="search" value="{{ request('search') }}" placeholder="Contoh: Avanza atau Honda" class="ui-field">
        </div>
        <div class="lg:col-span-3">
            <label class="ui-label" for="car-category">Kategori</label>
            <select id="car-category" name="car_type_id" class="ui-field">
                <option value="">Semua kategori</option>
                @foreach ($carTypes as $type)
                    <option value="{{ $type->id }}" @selected(request('car_type_id') == $type->id)>{{ $type->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="lg:col-span-2">
            <label class="ui-label" for="car-transmission">Transmisi</label>
            <select id="car-transmission" name="transmission" class="ui-field">
                <option value="">Semua transmisi</option>
                <option value="automatic" @selected(request('transmission') === 'automatic')>Otomatis</option>
                <option value="manual" @selected(request('transmission') === 'manual')>Manual</option>
            </select>
        </div>
        <button class="ui-button col-span-2 lg:col-span-2">Cari mobil</button>
    </form>
    <section aria-label="Hasil pencarian kendaraan">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2 border-t border-line pt-5">
            <p class="text-sm"><strong>{{ $cars->total() }} mobil</strong> {{ request()->anyFilled(['search', 'car_type_id', 'transmission']) ? 'ditemukan dengan filter ini' : 'dalam katalog' }}</p>
            @if (request()->anyFilled(['search', 'car_type_id', 'transmission']))
                <a href="{{ route('cars.index') }}" class="ui-button ui-secondary">Hapus filter</a>
            @endif
        </div>
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
            @forelse ($cars as $car)
                @php
                    $modelPhoto = collect(config('catalog.photos'))->first(fn ($file, $model) => str_starts_with(strtolower(trim($car->name)).' ', $model.' '));
                    $hasUnitPhoto = $car->image && Storage::disk('public')->exists($car->image);
                @endphp
                <article class="ui-panel flex min-w-0 flex-col">
                    <figure class="vehicle-photo">
                        <div class="flex h-full items-center justify-center p-5 text-sm text-muted">Foto kendaraan belum tersedia</div>
                        @if ($hasUnitPhoto || $modelPhoto)
                            <img src="{{ $hasUnitPhoto ? Storage::disk('public')->url($car->image) : asset('images/models/'.$modelPhoto) }}" alt="{{ $car->name }}" loading="lazy" onerror="this.hidden = true; this.parentElement.querySelector('figcaption')?.remove()">
                            @unless ($hasUnitPhoto)
                                <figcaption class="absolute bottom-3 right-3 rounded bg-white px-2 py-1 text-xs text-ink">Foto model</figcaption>
                            @endunless
                        @endif
                    </figure>
                    <div class="flex flex-1 flex-col p-5">
                        <p class="text-sm text-muted">{{ $car->carType?->name ?? 'Mobil' }} · {{ $car->model_year }}</p>
                        <h2 class="mt-2 text-xl leading-7 font-semibold break-words">{{ $car->name }}</h2>
                        <dl class="mt-auto grid grid-cols-2 gap-3 pt-5 text-sm">
                            <div><dt class="text-muted">Transmisi</dt><dd class="mt-1 font-semibold">{{ $car->transmission === 'automatic' ? 'Otomatis' : 'Manual' }}</dd></div>
                            <div><dt class="text-muted">Kapasitas</dt><dd class="mt-1 font-semibold">{{ $car->capacity }} kursi</dd></div>
                        </dl>
                    </div>
                    <div class="border-t border-line p-5">
                        <p class="mb-2 text-sm font-semibold {{ $car->status === 'available' ? 'text-success' : 'text-muted' }}">{{ ['available' => 'Tersedia', 'rented' => 'Sedang disewa', 'maintenance' => 'Dalam perawatan'][$car->status] ?? 'Status belum tersedia' }}</p>
                        <p><strong class="text-2xl font-bold tabular-nums">Rp{{ number_format($car->price_per_day, 0, ',', '.') }}</strong><span class="text-sm text-muted"> / hari</span></p>
                        <a class="ui-button ui-secondary mt-4 w-full" href="{{ route('cars.show', $car) }}">Lihat mobil<span class="sr-only"> {{ $car->name }}</span></a>
                    </div>
                </article>
            @empty
                <div class="ui-panel col-span-full p-8 text-center">
                    <h2 class="text-xl font-bold">Mobil belum ditemukan</h2>
                    <p class="mt-2 text-muted">Tidak ada armada yang sesuai dengan pencarian ini. Coba ubah pencarian atau hapus filter.</p>
                    <a href="{{ route('cars.index') }}" class="ui-button mt-5">Hapus filter</a>
                </div>
            @endforelse
        </div>
        <div class="mt-5 flex flex-wrap justify-between gap-2 text-xs text-muted">
            <p>Status menunjukkan kondisi kendaraan saat ini, bukan ketersediaan pada tanggal tertentu.</p>
            <a class="inline-flex min-h-11 items-center underline" href="{{ asset('images/models/kredit.html') }}">Kredit foto model</a>
        </div>
        @if ($cars->hasPages())
            <div class="ui-panel mt-5 p-4">{{ $cars->links('layouts.pagination') }}</div>
        @endif
    </section>
</div>
@endsection
