@extends('layouts.portal')

@section('title', 'Dasbor Pelanggan - DriveEase')

@section('content')
<div class="space-y-6">
    <!-- Welcome Banner -->
    <div class="bg-surface rounded-xl p-6 border border-line shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-ink">Selamat Datang, {{ auth()->user()->name }}!</h2>
            <p class="text-sm text-muted mt-1">
                Pilihan armada kendaraan rental prima yang siap menemani perjalanan dinas, keluarga, atau liburan Anda.
            </p>
        </div>
        <div>
            <a href="{{ route('customer.verification.create') }}" class="inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold rounded-lg border border-field-line bg-surface hover:bg-surface-raised text-ink shadow-xs transition-colors">
                <svg class="w-4 h-4 text-link" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Status Identitas
            </a>
        </div>
    </div>

    <!-- Verification Status Banner -->
    @php
        $user = auth()->user();
    @endphp

    @if ($user->isVerified())
        <div class="p-5 rounded-xl bg-success-surface border border-success-line flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-start sm:items-center gap-3.5">
                <div class="w-10 h-10 rounded-lg bg-success-surface text-success flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-bold text-success">Akun Terverifikasi</h3>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-success-surface text-success uppercase tracking-wider">Aktif</span>
                    </div>
                    <p class="text-xs text-success mt-0.5">
                        Identitas KTP &amp; SIM Anda telah disetujui. Anda memenuhi syarat penuh untuk melakukan reservasi rental kendaraan.
                    </p>
                </div>
            </div>
            <a href="{{ route('customer.verification.create') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-success bg-surface border border-success-line rounded-lg hover:bg-success-surface transition-colors shrink-0">
                Lihat Detail Dokumen
            </a>
        </div>
    @elseif ($user->isRejectedVerification())
        <div class="p-5 rounded-xl bg-danger-surface border border-danger-line flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-start gap-3.5">
                <div class="w-10 h-10 rounded-lg bg-danger-surface text-danger flex items-center justify-center shrink-0 mt-0.5">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-bold text-danger">Verifikasi Identitas Ditolak</h3>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-danger-surface text-danger uppercase tracking-wider">Ditolak</span>
                    </div>
                    <p class="text-xs text-danger mt-1">
                        Alasan penolakan: <strong class="text-danger font-bold">"{{ $user->rejection_reason }}"</strong>
                    </p>
                    <p class="text-[11px] text-danger mt-0.5">Silakan perbaiki data atau unggah ulang foto identitas yang lebih jelas untuk melanjutkan.</p>
                </div>
            </div>
            <a href="{{ route('customer.verification.create') }}" class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-danger-action hover:bg-danger-action text-ink text-xs font-bold rounded-lg shadow-sm transition-colors shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                Perbaiki dan Kirim Ulang
            </a>
        </div>
    @elseif ($user->hasSubmittedDocuments())
        <div class="p-5 rounded-xl bg-warning-surface border border-warning-line flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-start sm:items-center gap-3.5">
                <div class="w-10 h-10 rounded-lg bg-warning-surface text-warning flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-bold text-warning">Menunggu Verifikasi</h3>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-warning-surface text-warning uppercase tracking-wider">Dalam Antrean</span>
                    </div>
                    <p class="text-xs text-warning mt-0.5">
                        Dokumen identitas Anda sedang ditinjau manual oleh tim administrator kami. Mohon tunggu proses validasi.
                    </p>
                </div>
            </div>
            <a href="{{ route('customer.verification.create') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-warning bg-surface border border-warning-line rounded-lg hover:bg-warning-surface transition-colors shrink-0">
                Periksa Dokumen
            </a>
        </div>
    @else
        <div class="p-5 rounded-xl bg-info-surface border border-info-line flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-start sm:items-center gap-3.5">
                <div class="w-10 h-10 rounded-lg bg-info-surface text-info flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-info">Lengkapi Dokumen Identitas Anda</h3>
                    <p class="text-xs text-info mt-0.5">
                        Unggah KTP dan SIM Anda agar akun terverifikasi dan dapat segera melakukan pemesanan kendaraan impian.
                    </p>
                </div>
            </div>
            <a href="{{ route('customer.verification.create') }}" class="inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-info-surface hover:bg-info-surface text-ink text-xs font-bold rounded-lg shadow-sm transition-colors shrink-0">
                Unggah Dokumen Verifikasi &rarr;
            </a>
        </div>
    @endif

    @if (isset($customerSummary))
        <section class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-surface rounded-xl border border-line p-4 shadow-sm">
                <span class="text-[11px] font-semibold uppercase tracking-wider text-muted">Pesanan Aktif</span>
                <p class="text-2xl font-bold text-ink mt-1">{{ $customerSummary['active_count'] }}</p>
            </div>
            <div class="bg-surface rounded-xl border border-line p-4 shadow-sm">
                <span class="text-[11px] font-semibold uppercase tracking-wider text-warning">Butuh Tindakan</span>
                <p class="text-2xl font-bold text-warning mt-1">{{ $customerSummary['action_needed_count'] }}</p>
            </div>
            <div class="bg-surface rounded-xl border border-line p-4 shadow-sm">
                <span class="text-[11px] font-semibold uppercase tracking-wider text-success">Transaksi Selesai</span>
                <p class="text-2xl font-bold text-success mt-1">{{ $customerSummary['completed_count'] }}</p>
            </div>
            <a href="{{ route('customer.bookings.index') }}" class="bg-surface rounded-xl border border-line p-4 shadow-sm hover:border-action transition-colors block">
                <span class="text-[11px] font-semibold uppercase tracking-wider text-muted">Total Transaksi</span>
                <p class="text-2xl font-bold text-ink mt-1">{{ $customerSummary['total_count'] }}</p>
                <span class="text-[11px] font-semibold text-link">Buka riwayat &rarr;</span>
            </a>
        </section>

        @if ($customerSummary['action_items']->isNotEmpty())
            <section role="alert" class="rounded-xl border border-warning-line bg-warning-surface p-5 space-y-2 text-xs">
                <h3 class="text-sm font-bold text-warning">Pemberitahuan yang Membutuhkan Tindakan Anda</h3>
                <ul class="space-y-2">
                    @foreach ($customerSummary['action_items'] as $item)
                        <li class="flex flex-wrap items-center justify-between gap-2 border-t border-warning-line/40 pt-2 first:border-t-0 first:pt-0">
                            <div>
                                <span class="font-mono font-bold text-ink">{{ $item['rental']->rental_code }}</span>
                                <span class="text-ink"> &middot; {{ $item['rental']->car?->name ?? 'Kendaraan' }} &mdash; {{ $item['label'] }}</span>
                            </div>
                            <a href="{{ route('customer.bookings.show', $item['rental']) }}" class="rounded-lg bg-surface px-3 py-1.5 font-semibold text-ink border border-warning-line hover:bg-surface-hover">
                                Tindak Lanjuti &rarr;
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    @endif

    @if ($recentBookings->isNotEmpty())
        <section class="bg-surface rounded-xl border border-line p-5 shadow-sm space-y-3">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-bold text-ink">Pesanan &amp; Pemberitahuan Terbaru</h3>
                <a href="{{ route('customer.bookings.index') }}" class="text-xs font-semibold text-link hover:underline">Lihat semua &rarr;</a>
            </div>
            <div class="divide-y divide-line text-xs">
                @foreach ($recentBookings as $booking)
                    <div class="py-2.5 flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <span class="font-mono font-bold text-ink">{{ $booking->rental_code }}</span>
                            <span class="text-ink"> &middot; {{ $booking->car?->name }}</span>
                            <span class="block text-muted">{{ $booking->nextActionNotice() }}</span>
                        </div>
                        <a href="{{ route('customer.bookings.show', $booking) }}" class="font-semibold text-link hover:underline">Rincian &rarr;</a>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <!-- Section Header -->
    <div class="flex items-center justify-between pt-2">
        <div>
            <h3 class="text-base font-bold text-ink">Armada Siap Disewa Hari Ini</h3>
            <p class="text-xs text-muted mt-0.5">Semua unit bergaransi kondisi bersih, terawat, dan siap jalan.</p>
        </div>
        <span class="text-xs font-semibold px-3 py-1 bg-success-surface text-success rounded-full border border-success-line">
            {{ $availableCars->count() }} Unit Tersedia
        </span>
    </div>

    <!-- Available Cars Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse ($availableCars as $car)
            <div class="bg-surface rounded-xl border border-line shadow-sm overflow-hidden flex flex-col hover:border-field-line transition-colors">
                <!-- Card Header -->
                <div class="p-5 border-b border-line flex items-start justify-between">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-link">
                            {{ $car->brand }} &bull; {{ $car->carType?->name ?? 'Mobil' }}
                        </span>
                        <h4 class="text-base font-bold text-ink mt-0.5">{{ $car->name }}</h4>
                    </div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-success-surface text-success">
                        Tersedia
                    </span>
                </div>

                <!-- Specs -->
                <div class="p-5 flex-1 space-y-4">
                    <div class="grid grid-cols-3 gap-2 text-center">
                        <div class="p-2.5 rounded-lg bg-surface-raised border border-line">
                            <span class="text-[10px] text-muted uppercase font-semibold block">Tahun</span>
                            <span class="text-xs font-bold text-ink block mt-0.5">{{ $car->model_year }}</span>
                        </div>
                        <div class="p-2.5 rounded-lg bg-surface-raised border border-line">
                            <span class="text-[10px] text-muted uppercase font-semibold block">Transmisi</span>
                            <span class="text-xs font-bold text-ink block mt-0.5 capitalize">{{ $car->transmission === 'automatic' ? 'Otomatis' : 'Manual' }}</span>
                        </div>
                        <div class="p-2.5 rounded-lg bg-surface-raised border border-line">
                            <span class="text-[10px] text-muted uppercase font-semibold block">Kapasitas</span>
                            <span class="text-xs font-bold text-ink block mt-0.5">{{ $car->capacity }} Seat</span>
                        </div>
                    </div>

                    @if ($car->description)
                        <p class="text-xs text-muted line-clamp-2 leading-relaxed">
                            {{ $car->description }}
                        </p>
                    @endif
                </div>

                <!-- Card Footer (Pricing & Link) -->
                <div class="p-5 bg-surface-raised/70 border-t border-line flex items-center justify-between">
                    <div>
                        <span class="text-[10px] text-muted uppercase font-semibold block">Tarif Sewa</span>
                        <div class="flex items-baseline gap-1">
                            <span class="text-base font-extrabold text-ink">
                                Rp {{ number_format($car->price_per_day, 0, ',', '.') }}
                            </span>
                            <span class="text-[11px] text-muted">/ hari</span>
                        </div>
                    </div>
                    <a href="{{ route('cars.show', $car) }}" class="inline-flex items-center gap-1 text-xs font-semibold text-link hover:text-link bg-surface px-3 py-1.5 rounded-lg border border-line hover:border-focus transition-colors shadow-xs">
                        Lihat Detail
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                </div>
            </div>
        @empty
            <div class="col-span-full bg-surface rounded-xl p-12 text-center border border-line text-muted">
                <svg class="w-12 h-12 text-subtle mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 19H5V8h14m-3-5H8L5 6v13a2 2 0 002 2h10a2 2 0 002-2V6l-3-3z"/>
                </svg>
                <p class="font-semibold text-ink">Saat ini belum ada armada yang tersedia.</p>
                <p class="text-xs text-muted mt-1">Silakan periksa kembali beberapa saat lagi.</p>
            </div>
        @endforelse
    </div>
</div>
@endsection
