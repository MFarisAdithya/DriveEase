@extends('layouts.admin')

@section('title', 'Daftar Pemesanan - DriveEase Admin')
@section('page_title', 'Manajemen Pemesanan')

@section('content')
<div class="space-y-6">
    @if (($overdueVerificationCount ?? 0) > 0)
        <div role="alert" class="rounded-xl border border-warning-line bg-warning-surface p-4 text-xs text-warning">
            <strong>Peringatan Verifikasi Admin:</strong> Terdapat {{ $overdueVerificationCount }} pembayaran menunggu verifikasi lebih dari 1 jam.
        </div>
    @endif
    <div class="bg-surface rounded-xl border border-line p-5 shadow-sm flex flex-col xl:flex-row xl:items-end justify-between gap-4">
        <form method="GET" action="{{ route('admin.bookings.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 flex-1 items-end">
            <div>
                <label for="admin_booking_search" class="block text-[11px] font-semibold text-muted mb-1">Kata Kunci</label>
                <input id="admin_booking_search" name="search" value="{{ request('search') }}" placeholder="Kode, pelanggan, atau mobil..." class="w-full rounded-lg border border-field-line bg-surface-raised px-3 py-2 text-xs">
            </div>
            <div>
                <label for="admin_booking_channel" class="block text-[11px] font-semibold text-muted mb-1">Kanal</label>
                <select id="admin_booking_channel" name="booking_channel" class="w-full rounded-lg border border-field-line bg-surface-raised px-3 py-2 text-xs">
                    <option value="">Semua Kanal</option>
                    <option value="online" @selected(request('booking_channel') === 'online')>Online</option>
                    <option value="walk_in" @selected(request('booking_channel') === 'walk_in')>Langsung (Admin)</option>
                </select>
            </div>
            <div>
                <label for="admin_rental_status" class="block text-[11px] font-semibold text-muted mb-1">Status Sewa</label>
                <select id="admin_rental_status" name="rental_status" class="w-full rounded-lg border border-field-line bg-surface-raised px-3 py-2 text-xs">
                    <option value="">Semua Status</option>
                    @foreach (['pending' => 'Menunggu', 'confirmed' => 'Terkonfirmasi', 'picked_up' => 'Berjalan', 'returned' => 'Dikembalikan', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan', 'expired' => 'Kedaluwarsa'] as $key => $label)
                        <option value="{{ $key }}" @selected(request('rental_status') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="admin_payment_status" class="block text-[11px] font-semibold text-muted mb-1">Status Bayar</label>
                <select id="admin_payment_status" name="payment_status" class="w-full rounded-lg border border-field-line bg-surface-raised px-3 py-2 text-xs">
                    <option value="">Semua Pembayaran</option>
                    @foreach (['unpaid' => 'Belum Bayar', 'pending_approval' => 'Menunggu Verifikasi', 'paid' => 'Lunas', 'rejected' => 'Ditolak'] as $key => $label)
                        <option value="{{ $key }}" @selected(request('payment_status') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="admin_date_from" class="block text-[11px] font-semibold text-muted mb-1">Dari Tanggal (WIB)</label>
                <input id="admin_date_from" name="date_from" type="date" value="{{ request('date_from') }}" class="w-full rounded-lg border border-field-line bg-surface-raised px-3 py-2 text-xs">
            </div>
            <div>
                <label for="admin_date_to" class="block text-[11px] font-semibold text-muted mb-1">Sampai Tanggal (WIB)</label>
                <input id="admin_date_to" name="date_to" type="date" value="{{ request('date_to') }}" class="w-full rounded-lg border border-field-line bg-surface-raised px-3 py-2 text-xs">
            </div>
            <div class="sm:col-span-2 lg:col-span-6 flex flex-wrap items-center gap-3">
                <div>
                    <label for="admin_quick_filter" class="sr-only">Filter Khusus</label>
                    <select id="admin_quick_filter" name="filter" class="rounded-lg border border-field-line bg-surface-raised px-3 py-2 text-xs">
                        <option value="">Filter Khusus</option>
                        <option value="active_rentals" @selected(request('filter') === 'active_rentals')>Sewa Aktif</option>
                        <option value="overdue_verification" @selected(request('filter') === 'overdue_verification')>Verifikasi &gt; 1 Jam</option>
                        <option value="pending_changes" @selected(request('filter') === 'pending_changes')>Usulan Perubahan</option>
                        <option value="pending_extensions" @selected(request('filter') === 'pending_extensions')>Pengajuan Perpanjangan</option>
                        <option value="affected_bookings" @selected(request('filter') === 'affected_bookings')>Pesanan Terdampak</option>
                    </select>
                </div>
                <button type="submit" class="rounded-lg bg-surface-hover px-4 py-2 text-xs font-semibold text-ink">Filter</button>
                @if (request()->anyFilled(['search', 'booking_channel', 'rental_status', 'payment_status', 'date_from', 'date_to', 'filter']))
                    <a href="{{ route('admin.bookings.index') }}" class="text-xs font-semibold text-danger hover:underline">Reset</a>
                @endif
            </div>
        </form>
        <div class="flex flex-wrap items-center gap-2 shrink-0">
            <a href="{{ route('admin.reports.index') }}" class="inline-flex items-center justify-center rounded-lg border border-line bg-surface-raised hover:bg-surface-hover px-4 py-2 text-xs font-bold text-ink">
                Laporan Keuangan
            </a>
            <a href="{{ route('admin.bookings.create') }}" class="inline-flex items-center justify-center rounded-lg bg-action hover:bg-action-hover px-4 py-2 text-xs font-bold text-ink shadow-xs">
                + Pemesanan Langsung (Walk-In)
            </a>
        </div>
    </div>

    <div class="bg-surface rounded-xl border border-line shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-raised text-[11px] font-semibold text-muted uppercase tracking-wider border-b border-line">
                        <th class="py-3 px-5">Kode</th>
                        <th class="py-3 px-5">Pelanggan</th>
                        <th class="py-3 px-5">Kendaraan</th>
                        <th class="py-3 px-5">Jadwal (WIB)</th>
                        <th class="py-3 px-5">Kanal</th>
                        <th class="py-3 px-5">Total</th>
                        <th class="py-3 px-5">Status</th>
                        <th class="py-3 px-5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line text-sm">
                    @forelse ($bookings as $booking)
                        <tr class="hover:bg-surface-raised/80">
                            <td class="py-3.5 px-5 font-mono text-xs font-bold text-ink">{{ $booking->rental_code }}</td>
                            <td class="py-3.5 px-5">
                                <span class="font-semibold text-ink block">{{ $booking->user?->name }}</span>
                                <span class="text-xs text-muted">{{ $booking->user?->email }}</span>
                            </td>
                            <td class="py-3.5 px-5">
                                <span class="font-semibold text-ink block">{{ $booking->car?->name }}</span>
                                <span class="text-xs text-muted">{{ $booking->car?->plate_number }}</span>
                            </td>
                            <td class="py-3.5 px-5 text-xs text-muted">
                                <span>{{ $booking->pickup_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }}</span>
                                <span class="block">s.d. {{ $booking->return_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }}</span>
                            </td>
                            <td class="py-3.5 px-5 text-xs">{{ $booking->channelLabel() }}</td>
                            <td class="py-3.5 px-5 font-bold text-ink">Rp {{ number_format((float) $booking->total_amount, 0, ',', '.') }}</td>
                            <td class="py-3.5 px-5 text-xs">
                                <span class="block font-semibold text-ink">{{ $booking->rentalStatusLabel() }}</span>
                                <span class="text-muted">{{ $booking->paymentStatusLabel() }}</span>
                            </td>
                            <td class="py-3.5 px-5 text-right">
                                <a href="{{ route('admin.bookings.show', $booking) }}" class="text-xs font-semibold text-link hover:underline">Detail &rarr;</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-10 text-center text-sm text-muted">Belum ada data pemesanan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($bookings->hasPages())
            <div class="border-t border-line p-4">
                {{ $bookings->links('layouts.pagination') }}
            </div>
        @endif
    </div>
</div>
@endsection
