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

        <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
            <div class="rounded-lg border border-line bg-surface-raised p-4">
                <dt class="text-muted font-semibold uppercase">Jadwal Pengambilan (WIB)</dt>
                <dd class="mt-1 text-sm font-bold text-ink">{{ $rental->pickup_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</dd>
            </div>
            <div class="rounded-lg border border-line bg-surface-raised p-4">
                <dt class="text-muted font-semibold uppercase">Batas Pengembalian (WIB)</dt>
                <dd class="mt-1 text-sm font-bold text-ink">{{ $rental->return_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</dd>
            </div>
            <div class="rounded-lg border border-line bg-surface-raised p-4">
                <dt class="text-muted font-semibold uppercase">Total Biaya Sewa</dt>
                <dd class="mt-1 text-base font-extrabold text-ink">Rp {{ number_format((float) $rental->total_amount, 0, ',', '.') }}</dd>
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

        @if ($rental->payment_status === \App\Models\Rental::PAYMENT_PAID)
            <div class="rounded-xl border border-success-line bg-success-surface p-5 text-xs space-y-2">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-sm font-bold text-success">Bukti Transaksi Terkonfirmasi</h2>
                    <button type="button" onclick="window.print()" class="rounded-lg border border-success-line bg-surface px-3 py-1.5 font-semibold text-ink hover:bg-surface-hover print:hidden">
                        Cetak Bukti Transaksi
                    </button>
                </div>
                <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <div>
                        <dt class="text-muted">Nomor Pesanan &amp; Kanal</dt>
                        <dd class="font-mono font-bold text-ink">{{ $rental->rental_code }} ({{ $rental->channelLabel() }})</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Tanggal Verifikasi Lunas</dt>
                        <dd class="font-semibold text-ink">{{ $rental->payment_verified_at?->timezone('Asia/Jakarta')->format('d M Y H:i') ?? $rental->created_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Rekening Pembayaran Tercatat</dt>
                        <dd class="font-semibold text-ink">{{ $rental->payment_bank_name }} &middot; {{ $rental->payment_account_number }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Total Sewa &amp; Tagihan Tambahan</dt>
                        <dd class="font-bold text-ink">Rp {{ number_format((float) $rental->total_amount + (float) $rental->additional_charges_total, 0, ',', '.') }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Total Dana Dibayar</dt>
                        <dd class="font-bold text-ink">Rp {{ number_format((float) $rental->paid_amount + (float) $rental->additional_charge_paid_amount, 0, ',', '.') }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Status Pengembalian Dana (Refund)</dt>
                        <dd class="font-semibold text-ink">
                            {{ $rental->refundStatusLabel() }}
                            @if ((float) $rental->refund_amount > 0)
                                (Rp {{ number_format((float) $rental->refund_amount, 0, ',', '.') }})
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>
        @endif

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
                <div>
                    <label for="payment_proof" class="block text-xs font-semibold text-ink mb-1">Foto Bukti Transfer (JPG/PNG/WEBP, maks 2 MB)</label>
                    <input id="payment_proof" name="payment_proof" type="file" accept=".jpg,.jpeg,.png,.webp" required class="w-full rounded-lg border border-field-line bg-surface p-2 text-xs">
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="rounded-lg bg-action hover:bg-action-hover px-5 py-2.5 text-xs font-bold text-ink shadow-xs">
                        Kirim Bukti Pembayaran
                    </button>
                </div>
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

        @if ($rental->refund_amount > 0 || $rental->refund_status !== \App\Models\Rental::REFUND_NONE || $rental->cancellation_requested_at)
            <div class="rounded-xl border border-line bg-surface-raised p-5 text-xs space-y-1.5">
                <h2 class="text-sm font-bold text-ink">Informasi Pengembalian Dana (Refund)</h2>
                @if ($rental->cancellation_requested_at)
                    <p>Pengajuan pembatalan dikirim pada {{ $rental->cancellation_requested_at->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB: "{{ $rental->cancellation_request_reason }}"</p>
                @endif
                <p>Status Refund: <strong>{{ $rental->refundStatusLabel() }}</strong> ({{ $rental->refund_percentage }}%)</p>
                <p>Nominal Refund: <strong>Rp {{ number_format((float) $rental->refund_amount, 0, ',', '.') }}</strong></p>
                @if ($rental->refund_transferred_at)
                    <p>Tanggal Transfer: <strong>{{ $rental->refund_transferred_at->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</strong> &middot; Referensi: <strong class="font-mono">{{ $rental->refund_reference }}</strong></p>
                @endif
            </div>
        @endif

        @if (! $rental->canBeCancelledUnpaid() && in_array($rental->rental_status, [\App\Models\Rental::STATUS_PENDING, \App\Models\Rental::STATUS_CONFIRMED], true) && $rental->cancellation_requested_at === null)
            <form method="POST" action="{{ route('customer.bookings.request-cancellation', $rental) }}" class="rounded-xl border border-line bg-surface-raised p-5 space-y-3">
                @csrf
                <h2 class="text-sm font-bold text-ink">Ajukan Pembatalan Pesanan</h2>
                <p class="text-xs text-muted">Kebijakan refund pelanggan: &ge; 48 jam (100%), 24 s.d. &lt; 48 jam (75%), 6 s.d. &lt; 24 jam (50%), &lt; 6 jam (0%).</p>
                <div>
                    <label for="cancellation_request_reason" class="block text-xs font-semibold text-ink mb-1">Alasan Pengajuan Pembatalan</label>
                    <input id="cancellation_request_reason" name="cancellation_request_reason" required minlength="5" placeholder="Jelaskan alasan pembatalan pesanan Anda" class="w-full rounded-lg border border-field-line bg-surface p-2 text-xs">
                </div>
                <button type="submit" class="rounded-lg border border-danger-line bg-danger-surface px-4 py-2 text-xs font-bold text-danger">
                    Kirim Pengajuan Pembatalan
                </button>
            </form>
        @endif

        @php
            $hasOpenChangeRequest = $rental->changeRequests->contains(
                fn ($cr) => in_array($cr->status, [\App\Models\RentalChangeRequest::STATUS_PENDING_ADMIN, \App\Models\RentalChangeRequest::STATUS_PENDING_CUSTOMER], true)
            );
            $hasOpenExtension = $rental->extensions->contains(fn ($ext) => $ext->isOpen());
        @endphp

        @if ($rental->changeRequests->isNotEmpty() || in_array($rental->rental_status, [\App\Models\Rental::STATUS_PENDING, \App\Models\Rental::STATUS_CONFIRMED], true))
            <div class="space-y-4 border-t border-line pt-4">
                <h2 class="text-sm font-bold text-ink">Perubahan Jadwal &amp; Penggantian Unit</h2>

                @foreach ($rental->changeRequests as $changeRequest)
                    <div class="rounded-xl border border-line bg-surface-raised p-4 text-xs space-y-2">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="font-bold text-ink">{{ $changeRequest->typeLabel() }}</span>
                            <span class="rounded-full bg-surface border border-line px-2.5 py-0.5 font-semibold text-ink">{{ $changeRequest->statusLabel() }}</span>
                        </div>
                        <p class="text-muted">
                            Jadwal usulan: <strong class="text-ink">{{ $changeRequest->proposed_pickup_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</strong>
                            s.d. <strong class="text-ink">{{ $changeRequest->proposed_return_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</strong>
                            ({{ $changeRequest->proposed_total_days }} hari)
                            @if ($changeRequest->proposedCar)
                                &middot; Unit: <strong class="text-ink">{{ $changeRequest->proposedCar->name }} ({{ $changeRequest->proposedCar->plate_number }})</strong>
                            @endif
                        </p>
                        <p class="text-muted">
                            Total tagihan usulan: <strong class="text-ink">Rp {{ number_format((float) $changeRequest->proposed_total_amount, 0, ',', '.') }}</strong>
                            @if ($changeRequest->waive_price_difference)
                                <span class="text-info font-semibold">(Selisih biaya dibebaskan admin)</span>
                            @endif
                        </p>
                        <p>Alasan: {{ $changeRequest->reason }}</p>
                        @if ($changeRequest->response_notes)
                            <p class="text-muted">Catatan tanggapan: {{ $changeRequest->response_notes }}</p>
                        @endif

                        @if ($changeRequest->status === \App\Models\RentalChangeRequest::STATUS_PENDING_CUSTOMER)
                            <div class="pt-2 flex flex-col sm:flex-row gap-3">
                                <form method="POST" action="{{ route('customer.change-requests.respond', $changeRequest) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="decision" value="approve">
                                    <button type="submit" class="rounded-lg bg-action hover:bg-action-hover px-4 py-2 text-xs font-bold text-ink">
                                        Setujui Usulan Admin
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('customer.change-requests.respond', $changeRequest) }}" class="flex-1 flex flex-col sm:flex-row gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="decision" value="reject">
                                    <input name="response_notes" required placeholder="Alasan menolak usulan admin" class="flex-1 rounded-lg border border-field-line bg-surface p-2 text-xs">
                                    <button type="submit" class="rounded-lg border border-danger-line bg-danger-surface px-4 py-2 text-xs font-bold text-danger shrink-0">
                                        Tolak Usulan
                                    </button>
                                </form>
                            </div>
                        @endif
                    </div>
                @endforeach

                @if (in_array($rental->rental_status, [\App\Models\Rental::STATUS_PENDING, \App\Models\Rental::STATUS_CONFIRMED], true) && ! $hasOpenChangeRequest)
                    <form method="POST" action="{{ route('customer.bookings.change-requests.store', $rental) }}" class="rounded-xl border border-line bg-surface-raised p-4 space-y-3 text-xs">
                        @csrf
                        <h3 class="font-bold text-ink">Ajukan Perubahan Jadwal</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label for="proposed_pickup_at" class="block font-semibold text-ink mb-1">Waktu Ambil Baru (WIB)</label>
                                <input id="proposed_pickup_at" name="proposed_pickup_at" type="datetime-local" required value="{{ old('proposed_pickup_at', $rental->pickup_at?->timezone('Asia/Jakarta')->format('Y-m-d\TH:i')) }}" class="w-full rounded-lg border border-field-line bg-surface p-2">
                            </div>
                            <div>
                                <label for="proposed_total_days" class="block font-semibold text-ink mb-1">Jumlah Hari</label>
                                <input id="proposed_total_days" name="proposed_total_days" type="number" min="1" max="365" required value="{{ old('proposed_total_days', $rental->total_days) }}" class="w-full rounded-lg border border-field-line bg-surface p-2">
                            </div>
                            <div>
                                <label for="change_reason" class="block font-semibold text-ink mb-1">Alasan Perubahan</label>
                                <input id="change_reason" name="reason" required placeholder="Contoh: Jadwal perjalanan bergeser" class="w-full rounded-lg border border-field-line bg-surface p-2">
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="rounded-lg border border-line bg-surface px-4 py-2 font-bold text-ink hover:bg-surface-hover">
                                Kirim Permohonan Perubahan Jadwal
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        @endif

        @if ($rental->extensions->isNotEmpty() || (in_array($rental->rental_status, [\App\Models\Rental::STATUS_CONFIRMED, \App\Models\Rental::STATUS_PICKED_UP], true) && $rental->payment_status === \App\Models\Rental::PAYMENT_PAID))
            <div class="space-y-4 border-t border-line pt-4">
                <h2 class="text-sm font-bold text-ink">Perpanjangan Masa Sewa</h2>

                @foreach ($rental->extensions as $extension)
                    <div class="rounded-xl border border-line bg-surface-raised p-4 text-xs space-y-2">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="font-bold text-ink">Tambahan {{ $extension->additional_days }} Hari &middot; Rp {{ number_format((float) $extension->additional_amount, 0, ',', '.') }}</span>
                            <span class="rounded-full bg-surface border border-line px-2.5 py-0.5 font-semibold text-ink">{{ $extension->statusLabel() }}</span>
                        </div>
                        <p class="text-muted">
                            Kembali awal: {{ $extension->previous_return_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB
                            &rarr; Usulan kembali baru: <strong class="text-ink">{{ $extension->proposed_return_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</strong>
                            @if ($extension->paid_amount > 0)
                                &middot; Dana masuk perpanjangan: <strong class="text-ink">Rp {{ number_format((float) $extension->paid_amount, 0, ',', '.') }}</strong>
                            @endif
                        </p>
                        @if ($extension->rejection_reason)
                            <p class="text-danger">Alasan penolakan: {{ $extension->rejection_reason }}</p>
                        @endif
                        @if ($extension->payment_rejection_reason)
                            <p class="text-danger">Alasan penolakan / pembatalan bayar: {{ $extension->payment_rejection_reason }}</p>
                        @endif
                        @if ($extension->refund_status !== \App\Models\RentalExtension::REFUND_NONE || $extension->refund_amount > 0)
                            <p class="text-ink">
                                Status Refund Perpanjangan: <strong>{{ $extension->refundStatusLabel() }}</strong>
                                (Rp {{ number_format((float) $extension->refund_amount, 0, ',', '.') }})
                                @if ($extension->refund_transferred_at)
                                    &middot; Ditransfer {{ $extension->refund_transferred_at->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB
                                    (Ref: <strong class="font-mono">{{ $extension->refund_reference }}</strong>)
                                @endif
                            </p>
                        @endif
                        @if ($extension->payment_proof)
                            <p>
                                <a href="{{ route('documents.extension-payment.show', $extension) }}" target="_blank" class="font-semibold text-link hover:underline">
                                    Lihat Bukti Pembayaran Perpanjangan (Privat)
                                </a>
                            </p>
                        @endif

                        @if ($extension->status === \App\Models\RentalExtension::STATUS_AWAITING_PAYMENT && ! $extension->isPaymentExpired())
                            <form method="POST" action="{{ route('customer.extensions.payment.store', $extension) }}" enctype="multipart/form-data" class="pt-2 space-y-2 border-t border-line">
                                @csrf
                                <p class="text-muted">
                                    Rekening tujuan perpanjangan: <strong class="text-ink">{{ $extension->payment_bank_name ?? $rental->payment_bank_name }} {{ $extension->payment_account_number ?? $rental->payment_account_number }}</strong> (a.n. {{ $extension->payment_account_holder ?? $rental->payment_account_holder }}).
                                </p>
                                <p class="text-muted">
                                    Tenggat pembayaran perpanjangan: <strong class="text-ink">{{ $extension->payment_deadline_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</strong>.
                                    Jadwal baru aktif setelah admin memverifikasi pembayaran tambahan Anda.
                                </p>
                                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                                    <input name="payment_proof" type="file" required accept=".jpg,.jpeg,.png,.pdf" class="flex-1 rounded-lg border border-field-line bg-surface p-2 text-xs">
                                    <button type="submit" class="rounded-lg bg-action hover:bg-action-hover px-4 py-2 text-xs font-bold text-ink shrink-0">
                                        Unggah Bukti Perpanjangan
                                    </button>
                                </div>
                            </form>
                        @endif
                    </div>
                @endforeach

                @if (in_array($rental->rental_status, [\App\Models\Rental::STATUS_CONFIRMED, \App\Models\Rental::STATUS_PICKED_UP], true) && $rental->payment_status === \App\Models\Rental::PAYMENT_PAID && ! $hasOpenExtension)
                    <form method="POST" action="{{ route('customer.bookings.extensions.store', $rental) }}" class="rounded-xl border border-line bg-surface-raised p-4 space-y-3 text-xs">
                        @csrf
                        <h3 class="font-bold text-ink">Ajukan Perpanjangan Hari Sewa</h3>
                        <p class="text-muted">Tarif harian berlaku: <strong>Rp {{ number_format((float) $rental->daily_price, 0, ',', '.') }}/hari</strong>. Perpanjangan memerlukan persetujuan admin dan pelunasan tambahan sebelum jadwal baru aktif.</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label for="additional_days" class="block font-semibold text-ink mb-1">Tambahan Hari (1–30)</label>
                                <input id="additional_days" name="additional_days" type="number" min="1" max="30" required value="{{ old('additional_days', 1) }}" class="w-full rounded-lg border border-field-line bg-surface p-2">
                            </div>
                            <div>
                                <label for="extension_notes" class="block font-semibold text-ink mb-1">Catatan (Opsional)</label>
                                <input id="extension_notes" name="notes" placeholder="Alasan atau catatan perpanjangan" class="w-full rounded-lg border border-field-line bg-surface p-2">
                            </div>
                        </div>
                        <button type="submit" class="rounded-lg bg-action hover:bg-action-hover px-4 py-2 font-bold text-ink">
                            Ajukan Perpanjangan Sewa
                        </button>
                    </form>
                @endif
            </div>
        @endif

        @if ($rental->inspections->isNotEmpty())
            <div class="space-y-3 border-t border-line pt-4">
                <h2 class="text-sm font-bold text-ink">Hasil Inspeksi Kendaraan (Pengambilan &amp; Pengembalian)</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                    @foreach ($rental->inspections as $inspection)
                        <div class="rounded-xl border border-line bg-surface-raised p-4 space-y-1.5">
                            <p class="font-bold text-ink">{{ $inspection->typeLabel() }}</p>
                            <p class="text-muted">Petugas: <strong class="text-ink">{{ $inspection->inspector?->name }}</strong> &middot; {{ $inspection->inspected_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</p>
                            <p>Kilometer: <strong>{{ number_format($inspection->odometer_km, 0, ',', '.') }} km</strong> &middot; BBM: <strong>{{ $inspection->fuelLevelLabel() }}</strong></p>
                            <p class="text-muted">{{ $inspection->condition_notes }}</p>
                            @if ($inspection->has_damage && $inspection->damage_description)
                                <p class="text-danger"><strong>Catatan Kerusakan:</strong> {{ $inspection->damage_description }}</p>
                            @endif
                            @if ($inspection->condition_photo)
                                <p><a href="{{ route('documents.inspection-photo.show', $inspection) }}" target="_blank" class="font-semibold text-link hover:underline">Lihat Foto Kondisi (Privat)</a></p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if (in_array($rental->rental_status, [\App\Models\Rental::STATUS_RETURNED, \App\Models\Rental::STATUS_COMPLETED], true) && ($rental->additional_charges_total > 0 || $rental->additional_charges_assessed_at !== null))
            <div class="space-y-4 border-t border-line pt-4">
                <h2 class="text-sm font-bold text-ink">Rincian Biaya Tambahan &amp; Penyelesaian</h2>
                <div class="rounded-xl border border-line bg-surface-raised p-4 text-xs space-y-2">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="font-bold text-ink">Total Tagihan Tambahan: Rp {{ number_format((float) $rental->additional_charges_total, 0, ',', '.') }}</span>
                        <span class="rounded-full bg-surface border border-line px-2.5 py-0.5 font-semibold text-ink">{{ $rental->additionalChargeStatusLabel() }}</span>
                    </div>
                    <p class="text-muted">
                        Denda keterlambatan ({{ $rental->late_minutes }} menit): <strong class="text-ink">Rp {{ number_format((float) $rental->late_fee_amount, 0, ',', '.') }}</strong>
                        &middot; Kekurangan BBM: <strong class="text-ink">Rp {{ number_format((float) $rental->fuel_fee_amount, 0, ',', '.') }}</strong>
                        &middot; Kerusakan: <strong class="text-ink">Rp {{ number_format((float) $rental->damage_fee_amount, 0, ',', '.') }}</strong>
                    </p>
                    <p class="text-muted">
                        Sudah dibayar: <strong class="text-ink">Rp {{ number_format((float) $rental->additional_charge_paid_amount, 0, ',', '.') }}</strong>
                        &middot; Sisa tagihan: <strong class="text-ink">Rp {{ number_format($rental->remainingAdditionalChargeBalance(), 0, ',', '.') }}</strong>
                    </p>
                    @if ($rental->fuel_fee_reason)
                        <p>Keterangan BBM: {{ $rental->fuel_fee_reason }}</p>
                    @endif
                    @if ($rental->damage_fee_reason)
                        <p>Keterangan Kerusakan: {{ $rental->damage_fee_reason }}</p>
                    @endif
                    @if ($rental->damage_evidence_path)
                        <p><a href="{{ route('documents.damage-evidence.show', $rental) }}" target="_blank" class="font-semibold text-link hover:underline">Lihat Bukti Pendukung Kerusakan (Privat)</a></p>
                    @endif
                    @if ($rental->additional_charge_proof)
                        <p><a href="{{ route('documents.additional-charge-proof.show', $rental) }}" target="_blank" class="font-semibold text-link hover:underline">Lihat Bukti Pembayaran Tagihan Tambahan Anda (Privat)</a></p>
                    @endif
                    @if ($rental->additional_charge_payment_reference)
                        <p class="text-ink">Referensi Pelunasan: <strong class="font-mono">{{ $rental->additional_charge_payment_reference }}</strong></p>
                    @endif
                    @if ($rental->additional_charge_rejection_reason)
                        <p class="text-danger">Alasan penolakan bukti bayar: {{ $rental->additional_charge_rejection_reason }}</p>
                    @endif
                </div>

                @if ($rental->rental_status === \App\Models\Rental::STATUS_RETURNED && in_array($rental->additional_charge_status, [\App\Models\Rental::ADDITIONAL_CHARGE_UNPAID, \App\Models\Rental::ADDITIONAL_CHARGE_REJECTED], true))
                    <form method="POST" action="{{ route('customer.bookings.additional-charges.proof', $rental) }}" enctype="multipart/form-data" class="rounded-xl border border-line bg-surface-raised p-4 space-y-2 text-xs">
                        @csrf
                        <p class="text-muted">Transfer sisa tagihan tambahan Rp <strong class="text-ink">{{ number_format($rental->remainingAdditionalChargeBalance(), 0, ',', '.') }}</strong> ke rekening <strong class="text-ink">{{ $rental->additional_charge_bank_name ?? $rental->payment_bank_name }} {{ $rental->additional_charge_account_number ?? $rental->payment_account_number }}</strong> (a.n. {{ $rental->additional_charge_account_holder ?? $rental->payment_account_holder }}).</p>
                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                            <input name="additional_charge_proof" type="file" required accept=".jpg,.jpeg,.png,.webp" class="flex-1 rounded-lg border border-field-line bg-surface p-2">
                            <button type="submit" class="rounded-lg bg-action hover:bg-action-hover px-4 py-2 font-bold text-ink shrink-0">
                                Kirim Bukti Pembayaran Tambahan
                            </button>
                        </div>
                    </form>
                @endif
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
