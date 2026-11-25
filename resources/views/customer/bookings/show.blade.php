@extends('layouts.portal')

@section('title', 'Detail Pesanan ' . $rental->rental_code . ' - DriveEase')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('customer.bookings.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-muted hover:text-ink">
            &larr; Kembali ke Daftar Pesanan
        </a>
        <span class="font-mono text-xs font-bold px-3 py-1 rounded-full bg-surface border border-line text-ink">
            Kode: {{ $rental->rental_code }}
        </span>
    </div>

    <div class="rounded-xl border border-info-line bg-info-surface p-4 text-xs sm:text-sm text-info">
        <strong class="block font-bold">Status &amp; Tindakan Berikutnya</strong>
        <p class="mt-0.5">{{ $rental->nextActionNotice() }}</p>
    </div>

    <div class="bg-surface rounded-xl border border-line shadow-sm p-6 sm:p-8 space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-line pb-4">
            <div>
                <h1 class="text-xl font-bold text-ink">{{ $rental->car?->name ?? 'Kendaraan' }}</h1>
                <p class="text-xs text-muted mt-0.5">{{ $rental->car?->brand }} &middot; {{ $rental->car?->plate_number }} &middot; {{ $rental->channelLabel() }}</p>
            </div>
            <div class="flex flex-wrap gap-2 text-xs font-semibold">
                <span class="px-3 py-1 rounded-full bg-surface-hover text-ink">{{ $rental->rentalStatusLabel() }}</span>
                <span class="px-3 py-1 rounded-full bg-warning-surface text-warning">{{ $rental->paymentStatusLabel() }}</span>
            </div>
        </div>

        <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 text-xs">
            <div class="rounded-lg border border-line bg-surface-raised p-4">
                <dt class="text-muted font-semibold uppercase">Jadwal Pengambilan (WIB)</dt>
                <dd class="mt-1 text-sm font-bold text-ink">{{ $rental->pickup_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</dd>
            </div>
            <div class="rounded-lg border border-line bg-surface-raised p-4">
                <dt class="text-muted font-semibold uppercase">Batas Pengembalian (WIB)</dt>
                <dd class="mt-1 text-sm font-bold text-ink">{{ $rental->return_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</dd>
            </div>
            <div class="rounded-lg border border-line bg-surface-raised p-4">
                <dt class="text-muted font-semibold uppercase">Durasi &amp; Tarif</dt>
                <dd class="mt-1 text-sm font-bold text-ink">{{ $rental->total_days }} hari &times; Rp {{ number_format((float) $rental->daily_price, 0, ',', '.') }}</dd>
            </div>
            <div class="rounded-lg border border-line bg-surface-raised p-4">
                <dt class="text-muted font-semibold uppercase">Total Biaya Sewa</dt>
                <dd class="mt-1 text-base font-extrabold text-ink">Rp {{ number_format((float) $rental->total_amount, 0, ',', '.') }}</dd>
            </div>
            <div class="rounded-lg border border-line bg-surface-raised p-4">
                <dt class="text-muted font-semibold uppercase">Batas Unggah Pembayaran</dt>
                <dd class="mt-1 text-sm font-bold text-ink">{{ $rental->payment_deadline_at?->timezone('Asia/Jakarta')->format('d M Y H:i') ?? '-' }} WIB</dd>
            </div>
            <div class="rounded-lg border border-line bg-surface-raised p-4">
                <dt class="text-muted font-semibold uppercase">Persetujuan Ketentuan</dt>
                <dd class="mt-1 text-sm font-bold text-ink">{{ $rental->terms_accepted_at?->timezone('Asia/Jakarta')->format('d M Y H:i') ?? '-' }} WIB</dd>
            </div>
        </dl>

        <div class="rounded-xl border border-line bg-surface-raised p-5 space-y-1 text-xs">
            <h2 class="text-sm font-bold text-ink">Rekening Tujuan Pembayaran (Tercatat pada Tagihan)</h2>
            @if ($rental->payment_account_number)
                <p>Bank: <strong>{{ $rental->payment_bank_name }}</strong></p>
                <p>Nomor Rekening: <strong class="font-mono">{{ $rental->payment_account_number }}</strong></p>
                <p>Atas Nama: <strong>{{ $rental->payment_account_holder }}</strong></p>
            @else
                <p class="text-muted">Informasi rekening tujuan pembayaran belum dikonfigurasi admin saat pesanan dibuat.</p>
            @endif
        </div>

        @if ($rental->notes)
            <div class="text-xs text-muted">
                <strong class="text-ink">Catatan:</strong> {{ $rental->notes }}
            </div>
        @endif

        @if ($rental->cancellation_reason)
            <div class="rounded-lg border border-danger-line bg-danger-surface p-3 text-xs text-danger">
                <strong>Alasan Pembatalan / Kedaluwarsa:</strong> {{ $rental->cancellation_reason }}
            </div>
        @endif

        @if ($rental->canBeCancelledUnpaid())
            <div class="pt-4 border-t border-line flex justify-end">
                <form method="POST" action="{{ route('customer.bookings.cancel', $rental) }}" onsubmit="return confirm('Batalkan pesanan {{ $rental->rental_code }}?');">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="rounded-lg border border-danger-line bg-danger-surface px-4 py-2 text-xs font-bold text-danger hover:bg-danger-surface/80 transition-colors">
                        Batalkan Pesanan Belum Dibayar
                    </button>
                </form>
            </div>
        @endif
    </div>
</div>
@endsection
