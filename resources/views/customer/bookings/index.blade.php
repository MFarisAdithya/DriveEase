@extends('layouts.portal')

@section('title', 'Pesanan Saya - DriveEase')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div class="bg-surface rounded-xl border border-line p-6 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-ink">Daftar Pemesanan Saya</h1>
            <p class="mt-1 text-xs text-muted">Pantau jadwal pengambilan (WIB), tenggat pembayaran, dan status transaksi Anda.</p>
        </div>
        @if (auth()->user()->canMakeReservation())
            <a href="{{ route('customer.bookings.create') }}" class="inline-flex items-center justify-center rounded-lg bg-action hover:bg-action-hover px-4 py-2.5 text-xs font-bold text-ink shadow-xs transition-colors">
                + Buat Pemesanan Baru
            </a>
        @else
            <a href="{{ route('customer.verification.create') }}" class="inline-flex items-center justify-center rounded-lg border border-warning-line bg-warning-surface px-4 py-2.5 text-xs font-bold text-warning">
                Lengkapi Verifikasi untuk Memesan
            </a>
        @endif
    </div>

    <div class="bg-surface rounded-xl border border-line shadow-sm overflow-hidden">
        <div class="divide-y divide-line">
            @forelse ($bookings as $booking)
                <div class="p-5 flex flex-col md:flex-row md:items-center justify-between gap-4 hover:bg-surface-raised/60 transition-colors">
                    <div class="space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-mono text-xs font-bold px-2.5 py-1 rounded bg-surface-raised border border-line text-ink">{{ $booking->rental_code }}</span>
                            <span class="text-sm font-bold text-ink">{{ $booking->car?->name ?? 'Armada' }}</span>
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-surface-hover text-ink">{{ $booking->rentalStatusLabel() }}</span>
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-info-surface text-info">{{ $booking->paymentStatusLabel() }}</span>
                        </div>
                        <p class="text-xs text-muted">
                            Jadwal: <strong>{{ $booking->pickup_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</strong>
                            s.d. <strong>{{ $booking->return_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</strong>
                            ({{ $booking->total_days }} hari)
                        </p>
                        <p class="text-xs text-subtle">{{ $booking->nextActionNotice() }}</p>
                    </div>
                    <div class="flex items-center justify-between md:justify-end gap-4 shrink-0">
                        <div class="text-left md:text-right">
                            <span class="block text-[11px] text-muted">Total Tagihan</span>
                            <strong class="text-base font-extrabold text-ink">Rp {{ number_format((float) $booking->total_amount, 0, ',', '.') }}</strong>
                        </div>
                        <a href="{{ route('customer.bookings.show', $booking) }}" class="rounded-lg border border-field-line bg-surface-raised hover:bg-surface-hover px-3.5 py-2 text-xs font-semibold text-ink transition-colors">
                            Detail &rarr;
                        </a>
                    </div>
                </div>
            @empty
                <div class="p-10 text-center text-sm text-muted">
                    <p class="font-semibold text-ink">Belum ada pesanan kendaraan.</p>
                    <p class="mt-1 text-xs">Pilih armada yang tersedia melalui katalog untuk mulai memesan.</p>
                    <a href="{{ route('cars.index') }}" class="mt-4 inline-flex rounded-lg bg-action hover:bg-action-hover px-4 py-2 text-xs font-bold text-ink">Lihat Katalog</a>
                </div>
            @endforelse
        </div>

        @if ($bookings->hasPages())
            <div class="border-t border-line p-4">
                {{ $bookings->links('layouts.pagination') }}
            </div>
        @endif
    </div>
</div>
@endsection
