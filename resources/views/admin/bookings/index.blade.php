@extends('layouts.admin')

@section('title', 'Daftar Pemesanan - DriveEase Admin')
@section('page_title', 'Manajemen Pemesanan')

@section('content')
<div class="space-y-6">
    <div class="bg-surface rounded-xl border border-line p-5 shadow-sm flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <form method="GET" action="{{ route('admin.bookings.index') }}" class="flex flex-wrap items-center gap-3 flex-1">
            <input name="search" value="{{ request('search') }}" placeholder="Cari kode, nama pelanggan, atau mobil..." class="min-w-[220px] flex-1 rounded-lg border border-field-line bg-surface-raised px-3 py-2 text-xs">
            <select name="booking_channel" class="rounded-lg border border-field-line bg-surface-raised px-3 py-2 text-xs">
                <option value="">Semua Kanal</option>
                <option value="online" @selected(request('booking_channel') === 'online')>Online</option>
                <option value="walk_in" @selected(request('booking_channel') === 'walk_in')>Langsung (Admin)</option>
            </select>
            <select name="rental_status" class="rounded-lg border border-field-line bg-surface-raised px-3 py-2 text-xs">
                <option value="">Semua Status</option>
                @foreach (['pending' => 'Menunggu', 'confirmed' => 'Terkonfirmasi', 'picked_up' => 'Berjalan', 'returned' => 'Dikembalikan', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan', 'expired' => 'Kedaluwarsa'] as $key => $label)
                    <option value="{{ $key }}" @selected(request('rental_status') === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-lg bg-surface-hover px-4 py-2 text-xs font-semibold text-ink">Filter</button>
            @if (request()->anyFilled(['search', 'booking_channel', 'rental_status']))
                <a href="{{ route('admin.bookings.index') }}" class="text-xs font-semibold text-danger hover:underline">Reset</a>
            @endif
        </form>
        <a href="{{ route('admin.bookings.create') }}" class="inline-flex items-center justify-center rounded-lg bg-action hover:bg-action-hover px-4 py-2 text-xs font-bold text-ink shadow-xs shrink-0">
            + Pemesanan Langsung (Walk-In)
        </a>
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
