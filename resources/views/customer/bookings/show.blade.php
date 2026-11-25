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

    @if ($errors->any())
        <ul role="alert" class="rounded-xl border border-danger-line bg-danger-surface p-4 text-xs text-danger space-y-1">
            @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    @endif

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
                <dt class="text-muted font-semibold uppercase">Sudah Dibayar / Sisa Tagihan</dt>
                <dd class="mt-1 text-sm font-bold text-ink">
                    Rp {{ number_format((float) $rental->paid_amount, 0, ',', '.') }}
                    / <span class="text-danger">Rp {{ number_format($rental->remainingBalance(), 0, ',', '.') }}</span>
                </dd>
            </div>
            <div class="rounded-lg border border-line bg-surface-raised p-4">
                <dt class="text-muted font-semibold uppercase">Batas Unggah Pembayaran</dt>
                <dd class="mt-1 text-sm font-bold text-ink">{{ $rental->payment_deadline_at?->timezone('Asia/Jakarta')->format('d M Y H:i') ?? '-' }} WIB</dd>
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

        @if ($rental->payment_rejection_reason)
            <div class="rounded-xl border border-danger-line bg-danger-surface p-4 text-xs text-danger">
                <strong class="block font-bold">Bukti Pembayaran Ditolak</strong>
                <p class="mt-1">Alasan: "{{ $rental->payment_rejection_reason }}"</p>
                <p class="mt-1">Sisa kekurangan pembayaran: <strong>Rp {{ number_format($rental->remainingBalance(), 0, ',', '.') }}</strong>. Unggah perbaikan sebelum <strong>{{ $rental->payment_deadline_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</strong>.</p>
            </div>
        @endif

        @if ($rental->canUploadPaymentProof())
            <form method="POST" action="{{ route('customer.bookings.payments.store', $rental) }}" enctype="multipart/form-data" class="rounded-xl border border-line bg-surface-raised p-5 space-y-4">
                @csrf
                <h2 class="text-sm font-bold text-ink">
                    {{ $rental->payment_status === \App\Models\Rental::PAYMENT_REJECTED ? 'Perbaiki Bukti / Lengkapi Kekurangan Pembayaran' : 'Unggah Bukti Transfer Pembayaran' }}
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="payment_proof" class="block text-xs font-semibold text-ink mb-1">Foto Bukti Transfer (JPG/PNG/WEBP, maks 2 MB)</label>
                        <input id="payment_proof" name="payment_proof" type="file" accept=".jpg,.jpeg,.png,.webp" required class="w-full rounded-lg border border-field-line bg-surface p-2 text-xs">
                    </div>
                    <div>
                        <label for="claimed_amount" class="block text-xs font-semibold text-ink mb-1">Nominal yang Ditransfer (Rp)</label>
                        <input id="claimed_amount" name="claimed_amount" type="number" min="1" value="{{ old('claimed_amount', (int) $rental->remainingBalance()) }}" required class="w-full rounded-lg border border-field-line bg-surface p-2 text-sm">
                    </div>
                </div>
                <button type="submit" class="rounded-lg bg-action hover:bg-action-hover px-5 py-2.5 text-xs font-bold text-ink shadow-xs">
                    Kirim Bukti Pembayaran
                </button>
            </form>
        @endif

        @if ($rental->payments->isNotEmpty())
            <div class="space-y-3">
                <h2 class="text-sm font-bold text-ink">Riwayat Pengiriman Bukti Pembayaran</h2>
                <ul class="divide-y divide-line rounded-xl border border-line bg-surface-raised text-xs">
                    @foreach ($rental->payments as $payment)
                        <li class="p-4 flex flex-wrap items-center justify-between gap-3">
                            <div class="space-y-1">
                                <span class="font-bold text-ink">{{ $payment->statusLabel() }}</span>
                                <span class="text-muted"> &middot; Dikirim {{ $payment->submitted_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</span>
                                <p>Klaim nominal: <strong>Rp {{ number_format((float) $payment->claimed_amount, 0, ',', '.') }}</strong>
                                    @if ($payment->verified_amount !== null)
                                        &middot; Terverifikasi: <strong>Rp {{ number_format((float) $payment->verified_amount, 0, ',', '.') }}</strong>
                                    @endif
                                </p>
                                @if ($payment->rejection_reason)
                                    <p class="text-danger">Alasan penolakan: {{ $payment->rejection_reason }}</p>
                                @endif
                            </div>
                            <a href="{{ route('documents.payment.show', $payment) }}" target="_blank" class="rounded-lg border border-field-line bg-surface px-3 py-1.5 font-semibold text-link hover:underline">
                                Lihat Bukti Privat
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

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

        @if ($rental->histories->isNotEmpty())
            <div class="space-y-2 border-t border-line pt-4">
                <h2 class="text-sm font-bold text-ink">Jejak Aktivitas Pesanan</h2>
                <ul class="space-y-1.5 text-xs text-muted">
                    @foreach ($rental->histories as $history)
                        <li>
                            <strong class="text-ink">{{ $history->created_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</strong>
                            &middot; {{ $history->action }}
                            @if ($history->actor)
                                (oleh {{ $history->actor->name }})
                            @endif
                            @if ($history->reason)
                                &mdash; {{ $history->reason }}
                            @endif
                        </li>
                    @endforeach
                </ul>
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
