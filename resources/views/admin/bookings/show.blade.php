@extends('layouts.admin')

@section('title', 'Detail Pemesanan ' . $rental->rental_code . ' - DriveEase Admin')
@section('page_title', 'Detail Pemesanan ' . $rental->rental_code)

@section('content')
<div class="max-w-5xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('admin.bookings.index') }}" class="text-xs font-semibold text-muted hover:text-ink">&larr; Kembali ke Daftar Pemesanan</a>
        <span class="font-mono text-xs font-bold px-3 py-1 rounded-full bg-surface border border-line text-ink">{{ $rental->rental_code }}</span>
    </div>

    @if ($errors->any())
        <ul role="alert" class="rounded-xl border border-danger-line bg-danger-surface p-4 text-xs text-danger space-y-1">
            @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    @endif

    @if ($rental->isAdminVerificationOverdue())
        <div role="alert" class="rounded-xl border border-warning-line bg-warning-surface p-4 text-xs text-warning">
            <strong>Peringatan SLA Verifikasi (&gt; 1 jam):</strong> Bukti transfer dikirim pada {{ $rental->payment_submitted_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB dan belum diverifikasi admin. Segera periksa agar jadwal pelanggan tidak tertunda.
        </div>
    @endif

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
                <dt class="text-muted font-semibold uppercase">Total / Dibayar / Sisa</dt>
                <dd class="mt-1 text-sm font-extrabold text-ink">
                    Rp {{ number_format((float) $rental->total_amount, 0, ',', '.') }}
                    / Rp {{ number_format((float) $rental->paid_amount, 0, ',', '.') }}
                    / <span class="text-danger">Rp {{ number_format($rental->remainingBalance(), 0, ',', '.') }}</span>
                </dd>
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

        @if ($rental->canUploadPaymentProof())
            <form method="POST" action="{{ route('admin.bookings.payments.store', $rental) }}" enctype="multipart/form-data" class="rounded-xl border border-line bg-surface-raised p-5 space-y-3">
                @csrf
                <h3 class="text-sm font-bold text-ink">Unggah Bukti Transfer (Bantuan Walk-In)</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <input name="payment_proof" type="file" accept=".jpg,.jpeg,.png,.webp" required class="rounded-lg border border-field-line bg-surface p-2 text-xs">
                    <input name="claimed_amount" type="number" min="1" value="{{ (int) $rental->remainingBalance() }}" required class="rounded-lg border border-field-line bg-surface p-2 text-xs">
                </div>
                <button type="submit" class="rounded-lg bg-surface-hover px-4 py-2 text-xs font-bold text-ink">Simpan Bukti Transfer</button>
            </form>
        @endif

        @if ($rental->payments->isNotEmpty())
            <div class="space-y-4">
                <h3 class="text-sm font-bold text-ink">Verifikasi Pembayaran</h3>
                @foreach ($rental->payments as $payment)
                    <div class="rounded-xl border border-line bg-surface-raised p-5 space-y-4 text-xs">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <span class="font-bold text-ink">{{ $payment->statusLabel() }}</span>
                                <span class="text-muted"> &middot; Dikirim {{ $payment->submitted_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</span>
                                <p class="mt-1">Nominal diklaim: <strong>Rp {{ number_format((float) $payment->claimed_amount, 0, ',', '.') }}</strong></p>
                                @if ($payment->verified_by)
                                    <p class="text-muted">Diverifikasi oleh {{ $payment->verifier?->name }} pada {{ $payment->verified_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB (Rp {{ number_format((float) $payment->verified_amount, 0, ',', '.') }})</p>
                                @endif
                                @if ($payment->rejection_reason)
                                    <p class="text-danger">Alasan penolakan: {{ $payment->rejection_reason }}</p>
                                @endif
                            </div>
                            <a href="{{ route('documents.payment.show', $payment) }}" target="_blank" class="rounded-lg border border-field-line bg-surface px-3 py-1.5 font-semibold text-link hover:underline">
                                Buka Bukti Transfer
                            </a>
                        </div>

                        @if ($payment->isPendingApproval())
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-3 border-t border-line">
                                <form method="POST" action="{{ route('admin.payments.approve', $payment) }}" class="space-y-2 rounded-lg border border-success-line bg-success-surface/50 p-4">
                                    @csrf
                                    @method('PATCH')
                                    <label for="verified_amount_{{ $payment->id }}" class="block font-bold text-success">Setujui Pembayaran Lunas (Rp)</label>
                                    <input id="verified_amount_{{ $payment->id }}" name="verified_amount" type="number" min="1" value="{{ (int) $rental->remainingBalance() }}" required class="w-full rounded-lg border border-field-line bg-surface p-2 text-xs">
                                    <button type="submit" class="rounded-lg bg-success-action px-4 py-2 font-bold text-white">Verifikasi Lunas &amp; Konfirmasi</button>
                                </form>

                                <form method="POST" action="{{ route('admin.payments.reject', $payment) }}" class="space-y-2 rounded-lg border border-danger-line bg-danger-surface/50 p-4">
                                    @csrf
                                    @method('PATCH')
                                    <label for="rejection_reason_{{ $payment->id }}" class="block font-bold text-danger">Tolak Bukti Pembayaran (Alasan Wajib)</label>
                                    <input id="rejection_reason_{{ $payment->id }}" name="rejection_reason" required minlength="5" placeholder="Contoh: Nominal transfer kurang atau foto buram" class="w-full rounded-lg border border-field-line bg-surface p-2 text-xs">
                                    <label for="received_amount_{{ $payment->id }}" class="block font-semibold text-ink">Nominal Masuk Sebagian (Opsional, 0 jika ditolak penuh)</label>
                                    <input id="received_amount_{{ $payment->id }}" name="received_amount" type="number" min="0" value="0" class="w-full rounded-lg border border-field-line bg-surface p-2 text-xs">
                                    <button type="submit" class="rounded-lg bg-danger-action px-4 py-2 font-bold text-white">Tolak Bukti Pembayaran</button>
                                </form>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        @if ($rental->cancellation_reason)
            <div class="rounded-lg border border-danger-line bg-danger-surface p-3 text-xs text-danger">
                <strong>Alasan Pembatalan / Kedaluwarsa:</strong> {{ $rental->cancellation_reason }}
            </div>
        @endif

        @if ($rental->cancellation_requested_at || $rental->refund_status !== \App\Models\Rental::REFUND_NONE || $rental->refund_amount > 0)
            <div class="rounded-xl border border-line bg-surface-raised p-5 space-y-3 text-xs">
                <h3 class="text-sm font-bold text-ink">Pengelolaan Pembatalan &amp; Refund</h3>
                @if ($rental->cancellation_requested_at)
                    <p class="text-warning font-semibold">Pelanggan mengajukan pembatalan pada {{ $rental->cancellation_requested_at->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB: "{{ $rental->cancellation_request_reason }}"</p>
                @endif
                <p>Status Refund: <strong>{{ $rental->refundStatusLabel() }}</strong> ({{ $rental->refund_percentage }}%) &middot; Nominal: <strong>Rp {{ number_format((float) $rental->refund_amount, 0, ',', '.') }}</strong></p>
                @if ($rental->refund_transferred_at)
                    <p>Direalisasikan pada {{ $rental->refund_transferred_at->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB &middot; Referensi: <strong class="font-mono">{{ $rental->refund_reference }}</strong></p>
                @endif

                @if ($rental->rental_status === \App\Models\Rental::STATUS_CANCELLED && $rental->refund_status === \App\Models\Rental::REFUND_PENDING)
                    <form method="POST" action="{{ route('admin.bookings.refund', $rental) }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3 pt-3 border-t border-line items-end">
                        @csrf
                        @method('PATCH')
                        <div>
                            <label for="transfer_amount" class="block font-semibold mb-1">Nominal Transfer (Rp)</label>
                            <input id="transfer_amount" name="transfer_amount" type="number" min="1" max="{{ (int) $rental->paid_amount }}" value="{{ (int) $rental->refund_amount }}" required class="w-full rounded-lg border border-field-line bg-surface p-2">
                        </div>
                        <div>
                            <label for="transferred_at" class="block font-semibold mb-1">Tanggal Transfer (WIB)</label>
                            <input id="transferred_at" name="transferred_at" type="datetime-local" value="{{ now('Asia/Jakarta')->format('Y-m-d\TH:i') }}" required class="w-full rounded-lg border border-field-line bg-surface p-2">
                        </div>
                        <div>
                            <label for="refund_reference" class="block font-semibold mb-1">Nomor Referensi</label>
                            <input id="refund_reference" name="refund_reference" required placeholder="TRX-REF-001" class="w-full rounded-lg border border-field-line bg-surface p-2">
                        </div>
                        <button type="submit" class="rounded-lg bg-action hover:bg-action-hover px-4 py-2 font-bold text-ink">Catat Transfer Refund</button>
                    </form>
                @endif
            </div>
        @endif

        @if (! $rental->canBeCancelledUnpaid() && in_array($rental->rental_status, [\App\Models\Rental::STATUS_PENDING, \App\Models\Rental::STATUS_CONFIRMED], true))
            <form method="POST" action="{{ route('admin.bookings.cancel-with-refund', $rental) }}" class="rounded-xl border border-danger-line bg-danger-surface/30 p-5 space-y-3 text-xs">
                @csrf
                @method('PATCH')
                <h3 class="text-sm font-bold text-danger">Batalkan Pesanan &amp; Tetapkan Refund</h3>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label for="cancellation_type" class="block font-semibold text-ink mb-1">Kategori Pembatalan</label>
                        <select id="cancellation_type" name="cancellation_type" class="w-full rounded-lg border border-field-line bg-surface p-2">
                            <option value="customer_request">Permintaan Pelanggan (Kebijakan Bertingkat)</option>
                            <option value="rental_fault">Kesalahan / Kendala Rental (100%)</option>
                            <option value="no_show">Pelanggan Tidak Hadir (0%)</option>
                        </select>
                    </div>
                    <div>
                        <label for="refund_amount_override" class="block font-semibold text-ink mb-1">Nominal Refund (Opsional, maks Rp {{ number_format((float) $rental->paid_amount, 0, ',', '.') }})</label>
                        <input id="refund_amount_override" name="refund_amount" type="number" min="0" max="{{ (int) $rental->paid_amount }}" placeholder="Otomatis sesuai kebijakan" class="w-full rounded-lg border border-field-line bg-surface p-2">
                    </div>
                    <div>
                        <label for="admin_cancel_reason" class="block font-semibold text-ink mb-1">Alasan Pembatalan</label>
                        <input id="admin_cancel_reason" name="cancellation_reason" required minlength="5" placeholder="Alasan pembatalan resmi" class="w-full rounded-lg border border-field-line bg-surface p-2">
                    </div>
                </div>
                <button type="submit" class="rounded-lg bg-danger-action px-4 py-2 font-bold text-white">Proses Pembatalan &amp; Refund</button>
            </form>
        @endif

        @if ($rental->histories->isNotEmpty())
            <div class="space-y-2 border-t border-line pt-4">
                <h3 class="text-sm font-bold text-ink">Jejak Audit Transaksi</h3>
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
