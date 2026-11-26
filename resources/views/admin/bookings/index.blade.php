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
    <div class="flex flex-wrap items-center justify-end gap-2">
        <a href="{{ route('admin.reports.index') }}" class="inline-flex min-h-10 items-center rounded-lg border border-line bg-surface-raised px-4 py-2 text-sm font-semibold text-ink hover:bg-surface-hover">Laporan keuangan</a>
        <a href="{{ route('admin.bookings.create') }}" class="inline-flex min-h-10 items-center rounded-lg bg-action px-4 py-2 text-sm font-bold text-ink hover:bg-action-hover">Pemesanan langsung (walk-in)</a>
    </div>

    {{-- Filter lanjutan terbuka jika salah satu nilainya aktif. Nilai yang dikirim
         tetap memakai nama parameter yang dibaca controller untuk pencarian. --}}
    <form method="GET" action="{{ route('admin.bookings.index') }}" aria-label="Filter pemesanan" class="space-y-3 rounded-xl border border-line bg-surface p-4 shadow-sm">
        <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label for="admin_booking_search" class="block text-[11px] font-semibold text-muted mb-1">Kata Kunci</label>
                    <input id="admin_booking_search" name="search" value="{{ request('search') }}" placeholder="Kode, pelanggan, atau mobil..." class="w-full rounded-lg border border-field-line bg-surface-raised px-3 py-2 text-xs">
                </div>
                <div>
                    <label for="admin_quick_filter" class="mb-1 block text-xs font-semibold text-muted">Antrean kerja</label>
                    <select id="admin_quick_filter" name="filter" class="w-full rounded-lg border border-field-line bg-surface-raised px-3 py-2 text-xs">
                        <option value="">Semua antrean</option>
                        <option value="active_rentals" @selected(request('filter') === 'active_rentals')>Sewa Aktif</option>
                        <option value="overdue_verification" @selected(request('filter') === 'overdue_verification')>Verifikasi Bayar Melewati SLA</option>
                        <option value="review_followup" @selected(request('filter') === 'review_followup')>Tindak Lanjut Pemeriksaan</option>
                        <option value="pending_changes" @selected(request('filter') === 'pending_changes')>Usulan Perubahan</option>
                        <option value="pending_extensions" @selected(request('filter') === 'pending_extensions')>Pengajuan Perpanjangan</option>
                        <option value="affected_bookings" @selected(request('filter') === 'affected_bookings')>Pesanan Terdampak</option>
                    </select>
                </div>
        </div>
        <div class="flex flex-col gap-2 sm:gap-3">
            <details @if (request()->anyFilled(['booking_channel', 'rental_status', 'payment_status', 'date_from', 'date_to'])) open @endif>
                <summary class="w-fit cursor-pointer rounded px-1 py-2 text-sm font-semibold text-link">Filter lanjutan</summary>
                <div class="grid gap-3 py-2 sm:grid-cols-2 xl:grid-cols-5">
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
                </div>
            </details>
            <div class="ml-auto flex items-center gap-3">
                @if (request()->anyFilled(['search', 'booking_channel', 'rental_status', 'payment_status', 'date_from', 'date_to', 'filter']))
                    <a href="{{ route('admin.bookings.index') }}" class="px-1 py-2 text-sm font-semibold text-link hover:underline">Reset</a>
                @endif
                <button type="submit" class="min-h-10 rounded-lg bg-action px-4 py-2 text-sm font-semibold text-ink hover:bg-action-hover">Terapkan</button>
            </div>
        </div>
    </form>

    <div class="bg-surface rounded-xl border border-line shadow-sm overflow-hidden">
        <div role="region" aria-label="Daftar pemesanan" tabindex="0" class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-raised text-[11px] font-semibold text-muted uppercase tracking-wider border-b border-line">
                        <th scope="col" class="px-3 py-3 lg:px-4">Pemesanan</th>
                        <th scope="col" class="px-3 py-3 lg:px-4">Pelanggan</th>
                        <th scope="col" class="px-3 py-3 lg:px-4">Kendaraan</th>
                        <th scope="col" class="px-3 py-3 lg:px-4">Jadwal (WIB)</th>
                        <th scope="col" class="px-3 py-3 lg:px-4">Total</th>
                        <th scope="col" class="px-3 py-3 lg:px-4">Status</th>
                        <th scope="col" class="px-3 py-3 lg:px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line text-sm">
                    @forelse ($bookings as $booking)
                        <tr class="hover:bg-surface-raised/80">
                            <td class="px-3 py-3 lg:px-4">
                                <span class="block whitespace-nowrap font-mono text-xs font-bold text-ink">{{ $booking->rental_code }}</span>
                                <span class="text-xs text-muted">{{ $booking->channelLabel() }}</span>
                            </td>
                            <td class="px-3 py-3 lg:px-4">
                                <span class="font-semibold text-ink block">{{ $booking->user?->name }}</span>
                                <span class="text-xs text-muted">{{ $booking->user?->email }}</span>
                            </td>
                            <td class="px-3 py-3 lg:px-4">
                                <span class="font-semibold text-ink block">{{ $booking->car?->name }}</span>
                                <span class="text-xs text-muted">{{ $booking->car?->plate_number }}</span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 text-xs text-muted lg:px-4">
                                <span>{{ $booking->pickup_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }}</span>
                                <span class="block">s.d. {{ $booking->return_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }}</span>
                            </td>
                            <td class="px-3 py-3 lg:px-4 font-bold text-ink">Rp {{ number_format((float) $booking->total_amount, 0, ',', '.') }}</td>
                            <td class="px-3 py-3 lg:px-4 text-xs">
                                <span class="block font-semibold text-ink">{{ $booking->rentalStatusLabel() }}</span>
                                <span class="text-muted">{{ $booking->paymentStatusLabel() }}</span>
                            </td>
                            <td class="px-3 py-3 lg:px-4 text-right">
                                <a href="{{ route('admin.bookings.show', $booking) }}" aria-label="Detail {{ $booking->rental_code }}" class="inline-flex min-h-10 items-center whitespace-nowrap text-sm font-semibold text-link hover:underline">Detail &rarr;</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-10 text-center text-sm text-muted">Belum ada data pemesanan.</td>
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
