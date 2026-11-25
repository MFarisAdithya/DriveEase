@extends('layouts.admin')

@section('title', 'Dasbor Administrator - DriveEase')
@section('page_title', 'Dasbor Ringkasan Operasional')

@section('content')
<div class="space-y-8">
    <!-- Top Greeting & Shortcut -->
    <div class="bg-surface rounded-xl p-6 border border-line shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-ink">Halo, {{ auth()->user()->name }}!</h2>
            <p class="text-sm text-muted mt-1">Selamat datang di konsol utama manajemen rental kendaraan DriveEase.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.cars.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-action hover:bg-action-hover text-ink text-sm font-semibold rounded-lg shadow-sm transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Tambah Kendaraan Baru
            </a>
            <a href="{{ route('admin.bookings.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-surface-raised hover:bg-surface-hover text-ink text-sm font-semibold rounded-lg border border-line shadow-sm transition-colors">
                Pemesanan Langsung
            </a>
            <a href="{{ route('admin.cars.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-line hover:bg-surface-hover text-ink text-sm font-semibold rounded-lg shadow-sm transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                </svg>
                Semua Armada
            </a>
        </div>
    </div>

    <!-- Statistics Grid (All from DB) -->
    @if (($impactedNextBookings ?? collect())->isNotEmpty() || ($stats['overdue_trips'] ?? 0) > 0)
        <div role="alert" class="rounded-xl border border-danger-line bg-danger-surface p-4 text-xs text-danger flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <strong class="block text-sm">Peringatan Operasional: {{ $stats['overdue_trips'] ?? 0 }} Pengembalian Terlambat &middot; {{ ($impactedNextBookings ?? collect())->count() }} Pesanan Berikutnya Terdampak</strong>
                <p class="mt-0.5">Segera tangani pesanan terdampak dengan kendaraan pengganti tanpa biaya tambahan, penjadwalan ulang, atau refund penuh.</p>
            </div>
            <a href="{{ route('admin.bookings.index', ['filter' => 'affected_bookings']) }}" class="rounded-lg bg-danger-action px-4 py-2 font-bold text-white shrink-0">
                Lihat Pesanan Terdampak
            </a>
        </div>
    @endif
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-5">
        <!-- Total Vehicles -->
        <div class="bg-surface rounded-xl p-5 border border-line shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-muted">Total Kendaraan</span>
                <span class="p-2 rounded-lg bg-surface-hover text-ink">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 19H5V8h14m-3-5H8L5 6v13a2 2 0 002 2h10a2 2 0 002-2V6l-3-3z"/>
                    </svg>
                </span>
            </div>
            <p class="text-3xl font-bold text-ink mt-3">{{ $stats['total'] }}</p>
            <p class="text-xs text-muted mt-1">Armada terdaftar</p>
        </div>

        <!-- Available Vehicles -->
        <div class="bg-surface rounded-xl p-5 border border-line shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-success">Unit Tersedia</span>
                <span class="p-2 rounded-lg bg-success-surface text-success">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </span>
            </div>
            <p class="text-3xl font-bold text-success mt-3">{{ $stats['available'] }}</p>
            <p class="text-xs text-muted mt-1">Siap disewa pelanggan</p>
        </div>

        <!-- Rented Vehicles -->
        <div class="bg-surface rounded-xl p-5 border border-line shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-warning">Sedang Disewa</span>
                <span class="p-2 rounded-lg bg-warning-surface text-warning">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </span>
            </div>
            <p class="text-3xl font-bold text-warning mt-3">{{ $stats['rented'] }}</p>
            <p class="text-xs text-muted mt-1">Berada di tangan pelanggan</p>
        </div>

        <!-- Maintenance Vehicles -->
        <div class="bg-surface rounded-xl p-5 border border-line shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-danger">Perawatan / Servis</span>
                <span class="p-2 rounded-lg bg-danger-surface text-danger">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </span>
            </div>
            <p class="text-3xl font-bold text-danger mt-3">{{ $stats['maintenance'] }}</p>
            <p class="text-xs text-muted mt-1">Dalam perbaikan bengkel</p>
        </div>

        <!-- Pending Verifications (Clickable to filtered list) -->
        <a href="{{ route('admin.verifications.index', ['status' => 'ready']) }}" class="bg-surface rounded-xl p-5 border {{ $stats['pending_verifications'] > 0 ? 'border-warning-line ring-2 ring-warning-line' : 'border-line' }} shadow-sm hover:border-warning-line transition-all block group">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-warning group-hover:text-warning">Siap ditinjau</span>
                <span class="p-2 rounded-lg bg-warning-surface text-warning group-hover:bg-warning-surface transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                </span>
            </div>
            <p class="text-3xl font-bold text-warning mt-3">{{ $stats['pending_verifications'] }}</p>
            <p class="text-xs text-warning/80 mt-1 flex items-center gap-1 font-medium">
                Pengajuan dengan berkas lengkap &rarr;
                <span class="block">{{ $stats['incomplete_verifications'] }} menunggu kelengkapan</span>
            </p>
        </a>
    </div>

    <!-- Live Transaction & Revenue Summary (All from DB) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <a href="{{ route('admin.bookings.index', ['filter' => 'active_rentals']) }}" class="bg-surface rounded-xl p-5 border border-line shadow-sm hover:border-action transition-colors block">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted">Sewa Aktif &amp; Terjadwal</span>
            <p class="text-2xl font-bold text-ink mt-2">{{ $stats['active_bookings'] ?? 0 }}</p>
            <p class="text-xs text-muted mt-1">{{ $stats['pending_payments'] ?? 0 }} pembayaran menunggu verifikasi</p>
        </a>
        <a href="{{ route('admin.bookings.index') }}" class="bg-surface rounded-xl p-5 border border-line shadow-sm hover:border-warning-line transition-colors block">
            <span class="text-xs font-semibold uppercase tracking-wider text-warning">Antrean Perubahan &amp; Refund</span>
            <p class="text-2xl font-bold text-warning mt-2">{{ ($stats['pending_refunds_count'] ?? 0) + ($stats['pending_extensions'] ?? 0) + ($stats['pending_changes'] ?? 0) }}</p>
            <p class="text-xs text-muted mt-1">{{ $stats['pending_refunds_count'] ?? 0 }} refund &middot; {{ $stats['pending_extensions'] ?? 0 }} perpanjangan &middot; {{ $stats['pending_changes'] ?? 0 }} perubahan</p>
        </a>
        <a href="{{ route('admin.reports.index') }}" class="bg-surface rounded-xl p-5 border border-line shadow-sm hover:border-success-line transition-colors block">
            <span class="text-xs font-semibold uppercase tracking-wider text-success">Penerimaan Kotor Bulan Ini</span>
            <p class="text-2xl font-bold text-success mt-2">Rp {{ number_format((float) ($stats['month_gross_receipts'] ?? 0), 0, ',', '.') }}</p>
            <p class="text-xs text-muted mt-1">{{ $stats['month_transactions'] ?? 0 }} transaksi pada periode berjalan</p>
        </a>
        <a href="{{ route('admin.reports.index') }}" class="bg-surface rounded-xl p-5 border border-line shadow-sm hover:border-action transition-colors block">
            <span class="text-xs font-semibold uppercase tracking-wider text-ink">Penerimaan Bersih Bulan Ini</span>
            <p class="text-2xl font-bold text-ink mt-2">Rp {{ number_format((float) ($stats['month_net_receipts'] ?? 0), 0, ',', '.') }}</p>
            <p class="text-xs text-muted mt-1">Refund terealisasi Rp {{ number_format((float) ($stats['month_processed_refunds'] ?? 0), 0, ',', '.') }} &rarr; Laporan</p>
        </a>
    </div>

    <!-- Actionable Transactions -->
    <div class="bg-surface rounded-xl border border-line shadow-sm overflow-hidden">
        <div class="p-6 border-b border-line flex items-center justify-between">
            <div>
                <h3 class="text-base font-bold text-ink">Transaksi yang Perlu Ditangani</h3>
                <p class="text-xs text-muted mt-0.5">Pesanan yang menunggu verifikasi bayar, perubahan, perpanjangan, tagihan akhir, atau realisasi refund.</p>
            </div>
            <a href="{{ route('admin.bookings.index') }}" class="text-xs font-semibold text-link hover:underline">
                Kelola Semua Pemesanan &rarr;
            </a>
        </div>
        <div class="divide-y divide-line">
            @forelse (($actionableBookings ?? collect()) as $actionRental)
                <div class="px-6 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                    <div>
                        <span class="font-mono font-bold text-ink">{{ $actionRental->rental_code }}</span>
                        <span class="text-muted"> &middot; {{ $actionRental->user?->name ?? '-' }} &middot; {{ $actionRental->car?->name ?? '-' }}</span>
                        <p class="text-muted mt-0.5">{{ $actionRental->rentalStatusLabel() }} &middot; {{ $actionRental->paymentStatusLabel() }} &middot; Refund: {{ $actionRental->refundStatusLabel() }}</p>
                    </div>
                    <a href="{{ route('admin.bookings.show', $actionRental) }}" class="inline-flex items-center justify-center rounded-lg border border-field-line bg-surface-raised hover:bg-surface-hover px-3.5 py-2 font-semibold text-ink shrink-0">
                        Tinjau Transaksi &rarr;
                    </a>
                </div>
            @empty
                <div class="px-6 py-8 text-center text-xs text-muted">
                    Tidak ada transaksi tertunda yang memerlukan tindakan admin saat ini.
                </div>
            @endforelse
        </div>
    </div>

    <!-- Recent Vehicles Table -->
    <div class="bg-surface rounded-xl border border-line shadow-sm overflow-hidden">
        <div class="p-6 border-b border-line flex items-center justify-between">
            <div>
                <h3 class="text-base font-bold text-ink">Kendaraan Terbaru Ditambahkan</h3>
                <p class="text-xs text-muted mt-0.5">5 unit terakhir yang tercatat di dalam sistem database.</p>
            </div>
            <a href="{{ route('admin.cars.index') }}" class="text-xs font-semibold text-link hover:text-link">
                Lihat Semua &rarr;
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-raised text-[11px] font-semibold text-muted uppercase tracking-wider border-b border-line">
                        <th class="py-3 px-6">Armada</th>
                        <th class="py-3 px-6">Kategori</th>
                        <th class="py-3 px-6">Plat Nomor</th>
                        <th class="py-3 px-6">Tahun</th>
                        <th class="py-3 px-6">Tarif / Hari</th>
                        <th class="py-3 px-6">Status</th>
                        <th class="py-3 px-6 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line text-sm">
                    @forelse ($recentCars as $car)
                        <tr class="hover:bg-surface-raised/80 transition-colors">
                            <td class="py-3.5 px-6">
                                <span class="font-semibold text-ink block">{{ $car->name }}</span>
                                <span class="text-xs text-muted">{{ $car->brand }}</span>
                            </td>
                            <td class="py-3.5 px-6 text-muted">
                                <span class="px-2 py-0.5 rounded text-xs font-medium bg-surface-hover text-ink">
                                    {{ $car->carType?->name ?? 'Umum' }}
                                </span>
                            </td>
                            <td class="py-3.5 px-6 font-mono text-xs font-semibold text-ink">
                                {{ $car->plate_number }}
                            </td>
                            <td class="py-3.5 px-6 text-muted">{{ $car->model_year }}</td>
                            <td class="py-3.5 px-6 font-semibold text-ink">
                                Rp {{ number_format($car->price_per_day, 0, ',', '.') }}
                            </td>
                            <td class="py-3.5 px-6">
                                @if ($car->status === 'available')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-success-surface text-success">
                                        Tersedia
                                    </span>
                                @elseif ($car->status === 'rented')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-warning-surface text-warning">
                                        Disewa
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-danger-surface text-danger">
                                        Perawatan
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-6 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <a href="{{ route('admin.cars.show', $car) }}" class="text-xs font-medium text-muted hover:text-link">Detail</a>
                                    <span class="text-muted">|</span>
                                    <a href="{{ route('admin.cars.edit', $car) }}" class="text-xs font-medium text-link hover:text-link">Ubah</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-muted">
                                Belum ada data kendaraan di database.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
