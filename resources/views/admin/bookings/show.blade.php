@extends('layouts.admin')

@section('title', 'Detail Pemesanan ' . $rental->rental_code . ' - DriveEase Admin')
@section('page_title', 'Detail Pemesanan ' . $rental->rental_code)

@section('content')
<div class="max-w-4xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('admin.bookings.index') }}" class="text-xs font-semibold text-muted hover:text-ink">&larr; Kembali ke Daftar Pemesanan</a>
        <span class="font-mono text-xs font-bold px-3 py-1 rounded-full bg-surface border border-line text-ink">{{ $rental->rental_code }}</span>
    </div>

    <div class="bg-surface rounded-xl border border-line shadow-sm p-6 sm:p-8 space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-line pb-4">
            <div>
                <h2 class="text-xl font-bold text-ink">{{ $rental->car?->name }} ({{ $rental->car?->plate_number }})</h2>
                <p class="text-xs text-muted mt-0.5">
                    Pelanggan: <strong>{{ $rental->user?->name }}</strong> ({{ $rental->user?->email }})
                    &middot; Kanal: <strong>{{ $rental->channelLabel() }}</strong>
                    @if ($rental->creator)
                        &middot; Dibuat oleh: {{ $rental->creator->name }}
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap gap-2 text-xs font-semibold">
                <span class="px-3 py-1 rounded-full bg-surface-hover text-ink">{{ $rental->rentalStatusLabel() }}</span>
                <span class="px-3 py-1 rounded-full bg-warning-surface text-warning">{{ $rental->paymentStatusLabel() }}</span>
            </div>
        </div>

        <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 text-xs">
            <div class="rounded-lg border border-line bg-surface-raised p-4">
                <dt class="text-muted font-semibold uppercase">Pengambilan (WIB)</dt>
                <dd class="mt-1 text-sm font-bold text-ink">{{ $rental->pickup_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</dd>
            </div>
            <div class="rounded-lg border border-line bg-surface-raised p-4">
                <dt class="text-muted font-semibold uppercase">Pengembalian (WIB)</dt>
                <dd class="mt-1 text-sm font-bold text-ink">{{ $rental->return_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</dd>
            </div>
            <div class="rounded-lg border border-line bg-surface-raised p-4">
                <dt class="text-muted font-semibold uppercase">Total Biaya ({{ $rental->total_days }} hari)</dt>
                <dd class="mt-1 text-base font-extrabold text-ink">Rp {{ number_format((float) $rental->total_amount, 0, ',', '.') }}</dd>
            </div>
            <div class="rounded-lg border border-line bg-surface-raised p-4">
                <dt class="text-muted font-semibold uppercase">Batas Pembayaran</dt>
                <dd class="mt-1 text-sm font-bold text-ink">{{ $rental->payment_deadline_at?->timezone('Asia/Jakarta')->format('d M Y H:i') ?? '-' }} WIB</dd>
            </div>
            <div class="rounded-lg border border-line bg-surface-raised p-4 sm:col-span-2">
                <dt class="text-muted font-semibold uppercase">Snapshot Rekening Pembayaran</dt>
                <dd class="mt-1 text-sm font-bold text-ink">
                    @if ($rental->payment_account_number)
                        {{ $rental->payment_bank_name }} &middot; {{ $rental->payment_account_number }} ({{ $rental->payment_account_holder }})
                    @else
                        Belum ada rekening tercatat pada tagihan ini.
                    @endif
                </dd>
            </div>
        </dl>

        @if ($rental->cancellation_reason)
            <div class="rounded-lg border border-danger-line bg-danger-surface p-3 text-xs text-danger">
                <strong>Alasan Pembatalan / Kedaluwarsa:</strong> {{ $rental->cancellation_reason }}
            </div>
        @endif

        @if ($rental->canBeCancelledUnpaid())
            <form method="POST" action="{{ route('admin.bookings.cancel', $rental) }}" class="pt-4 border-t border-line flex flex-col sm:flex-row items-stretch sm:items-end justify-between gap-3">
                @csrf
                @method('PATCH')
                <div class="flex-1">
                    <label for="cancellation_reason" class="block text-xs font-semibold text-ink mb-1">Alasan Pembatalan (Opsional)</label>
                    <input id="cancellation_reason" name="cancellation_reason" class="w-full rounded-lg border border-field-line bg-surface-raised p-2 text-xs" placeholder="Contoh: Pelanggan membatalkan pesanan sebelum transfer">
                </div>
                <button type="submit" class="rounded-lg border border-danger-line bg-danger-surface px-4 py-2 text-xs font-bold text-danger">
                    Batalkan Pesanan
                </button>
            </form>
        @endif
    </div>
</div>
@endsection
