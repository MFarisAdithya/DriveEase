@extends('layouts.admin')

@section('title', 'Dasbor Administrator - DriveEase')
@section('page_title', 'Dasbor Ringkasan Operasional')

@section('content')
<div class="space-y-5">
    <div class="flex flex-wrap justify-end gap-2">
        <a href="{{ route('admin.cars.create') }}" class="inline-flex min-h-10 items-center rounded-lg border border-line bg-surface-raised px-4 py-2 text-sm font-semibold text-ink hover:bg-surface-hover">Tambah kendaraan</a>
        <a href="{{ route('admin.bookings.create') }}" class="inline-flex min-h-10 items-center rounded-lg bg-action px-4 py-2 text-sm font-bold text-ink hover:bg-action-hover">Pemesanan langsung</a>
    </div>

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

    <section aria-labelledby="work-queues" class="space-y-2">
        <h2 id="work-queues" class="text-base font-bold text-ink">Antrean kerja</h2>
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <a href="{{ route('admin.verifications.index', ['status' => 'ready']) }}" class="rounded-xl border border-line bg-surface p-3 hover:border-warning-line">
                <span class="block text-sm font-semibold text-ink">Verifikasi identitas</span>
                <strong class="mt-1 block text-lg text-warning">{{ $stats['pending_verifications'] }} siap ditinjau</strong>
                <span class="text-xs text-muted">{{ $stats['incomplete_verifications'] }} menunggu kelengkapan</span>
            </a>
            <a href="{{ route('admin.bookings.index', ['filter' => 'review_followup']) }}" class="rounded-xl border border-line bg-surface p-3 hover:border-warning-line">
                <span class="block text-sm font-semibold text-ink">Pemeriksaan tertunda</span>
                <strong class="mt-1 block text-lg text-warning">{{ $stats['review_followups'] ?? 0 }} tindak lanjut pemeriksaan</strong>
            </a>
            <a href="{{ route('admin.bookings.index', ['filter' => 'active_rentals']) }}" class="rounded-xl border border-line bg-surface p-3 hover:border-action">
                <span class="block text-sm font-semibold text-ink">Sewa aktif dan terjadwal</span>
                <strong class="mt-1 block text-lg text-ink">{{ $stats['active_bookings'] ?? 0 }} pesanan</strong>
                <span class="text-xs text-muted">{{ $stats['pending_payments'] ?? 0 }} pembayaran menunggu verifikasi</span>
            </a>
            <a href="{{ route('admin.bookings.index') }}" class="rounded-xl border border-line bg-surface p-3 hover:border-warning-line">
                <span class="block text-sm font-semibold text-ink">Perubahan dan refund</span>
                <p class="mt-1 text-sm text-muted">{{ $stats['pending_changes'] ?? 0 }} perubahan &middot; {{ $stats['pending_extensions'] ?? 0 }} perpanjangan &middot; {{ $stats['pending_refunds_count'] ?? 0 }} refund</p>
            </a>
        </div>
    </section>

    <!-- Actionable Transactions -->
    <div class="bg-surface rounded-xl border border-line shadow-sm overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-4 py-4 sm:px-6">
            <div>
                <h2 class="text-base font-bold text-ink">Transaksi yang Perlu Ditangani</h2>
                <p class="text-xs text-muted mt-0.5">Pesanan yang menunggu verifikasi bayar, perubahan, perpanjangan, tagihan akhir, atau realisasi refund.</p>
            </div>
            <a href="{{ route('admin.bookings.index') }}" class="text-xs font-semibold text-link hover:underline">
                Kelola Semua Pemesanan &rarr;
            </a>
        </div>
        <div class="divide-y divide-line">
            @forelse (($actionableBookings ?? collect()) as $actionRental)
                <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm sm:px-6">
                    <div>
                        <span class="font-mono font-bold text-ink">{{ $actionRental->rental_code }}</span>
                        <span class="text-muted"> &middot; {{ $actionRental->user?->name ?? '-' }} &middot; {{ $actionRental->car?->name ?? '-' }}</span>
                        <p class="text-muted mt-0.5">{{ $actionRental->rentalStatusLabel() }} &middot; {{ $actionRental->paymentStatusLabel() }} &middot; Refund: {{ $actionRental->refundStatusLabel() }}</p>
                    </div>
                    <a href="{{ route('admin.bookings.show', $actionRental) }}" aria-label="Tinjau transaksi {{ $actionRental->rental_code }}" class="ml-auto inline-flex min-h-10 items-center justify-center rounded-lg border border-field-line bg-surface-raised hover:bg-surface-hover px-3.5 py-2 font-semibold text-ink shrink-0">
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

    <section aria-labelledby="fleet-summary" class="rounded-xl border border-line bg-surface p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 id="fleet-summary" class="text-base font-bold text-ink">Ringkasan armada</h2>
            <a href="{{ route('admin.cars.index') }}" class="ml-auto text-sm font-semibold text-link hover:underline">Kelola kendaraan &rarr;</a>
        </div>
        <dl class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach (['total' => 'Total kendaraan', 'available' => 'Tersedia', 'rented' => 'Sedang disewa', 'maintenance' => 'Perawatan'] as $key => $label)
                <div class="rounded-lg bg-surface-raised p-3">
                    <dt class="text-sm text-muted">{{ $label }}</dt>
                    <dd class="text-lg font-bold text-ink">{{ $stats[$key] }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    <div class="grid gap-3 sm:grid-cols-2">
        <a href="{{ route('admin.reports.index') }}" class="bg-surface rounded-xl p-5 border border-line shadow-sm hover:border-success-line transition-colors block">
            <span class="text-xs font-semibold uppercase tracking-wider text-success">Penerimaan Kas Kotor Bulan Ini</span>
            <p class="text-2xl font-bold text-success mt-2">Rp {{ number_format((float) ($stats['month_gross_receipts'] ?? 0), 0, ',', '.') }}</p>
            <p class="text-xs text-muted mt-1">{{ $stats['month_transactions'] ?? 0 }} pesanan tercatat pada buku kas WIB</p>
        </a>
        <a href="{{ route('admin.reports.index') }}" class="bg-surface rounded-xl p-5 border border-line shadow-sm hover:border-action transition-colors block">
            <span class="text-xs font-semibold uppercase tracking-wider text-ink">Penerimaan Bersih (Kas) Bulan Ini</span>
            <p class="text-2xl font-bold text-ink mt-2">Rp {{ number_format((float) ($stats['month_net_receipts'] ?? 0), 0, ',', '.') }}</p>
            <p class="text-xs text-muted mt-1">Refund terealisasi Rp {{ number_format((float) ($stats['month_processed_refunds'] ?? 0), 0, ',', '.') }} &middot; Kewajiban tertunda Rp {{ number_format((float) ($stats['month_pending_refunds'] ?? 0), 0, ',', '.') }}</p>
        </a>
    </div>

    <!-- Recent Vehicles Table -->
    <div class="bg-surface rounded-xl border border-line shadow-sm overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-4 py-4 sm:px-6">
            <div>
                <h2 class="text-base font-bold text-ink">Kendaraan Terbaru Ditambahkan</h2>
            </div>
            <a href="{{ route('admin.cars.index') }}" class="text-xs font-semibold text-link hover:text-link">
                Lihat Semua &rarr;
            </a>
        </div>

        <div role="region" aria-label="Daftar kendaraan terbaru" tabindex="0" class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-raised text-[11px] font-semibold text-muted uppercase tracking-wider border-b border-line">
                        <th scope="col" class="py-3 px-6">Armada</th>
                        <th scope="col" class="py-3 px-6">Kategori</th>
                        <th scope="col" class="py-3 px-6">Plat Nomor</th>
                        <th scope="col" class="py-3 px-6">Tahun</th>
                        <th scope="col" class="py-3 px-6">Tarif / Hari</th>
                        <th scope="col" class="py-3 px-6">Status</th>
                        <th scope="col" class="py-3 px-6 text-right">Aksi</th>
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
