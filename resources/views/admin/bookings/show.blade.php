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
                <p>Status Refund: <strong>{{ $rental->refundStatusLabel() }}</strong> ({{ $rental->refund_percentage }}%) &middot; Total Hak Refund: <strong>Rp {{ number_format((float) $rental->refund_amount, 0, ',', '.') }}</strong> &middot; Sudah Ditransfer: <strong>Rp {{ number_format((float) $rental->refund_realized_amount, 0, ',', '.') }}</strong></p>
                @if ($rental->refund_transferred_at)
                    <p>Direalisasikan pada {{ $rental->refund_transferred_at->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB &middot; Referensi: <strong class="font-mono">{{ $rental->refund_reference }}</strong></p>
                @endif

                @if ($rental->refund_status === \App\Models\Rental::REFUND_PENDING)
                    <form method="POST" action="{{ route('admin.bookings.refund', $rental) }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3 pt-3 border-t border-line items-end">
                        @csrf
                        @method('PATCH')
                        <div>
                            <label for="transfer_amount" class="block font-semibold mb-1">Nominal Transfer (Rp)</label>
                            <input id="transfer_amount" name="transfer_amount" type="number" step="0.01" min="1" value="{{ number_format($rental->pendingRefundBalance(), 2, '.', '') }}" required class="w-full rounded-lg border border-field-line bg-surface p-2">
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
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="cancellation_type" class="block font-semibold text-ink mb-1">Kategori Pembatalan</label>
                        <select id="cancellation_type" name="cancellation_type" class="w-full rounded-lg border border-field-line bg-surface p-2">
                            <option value="customer_request">Permintaan Pelanggan (Kebijakan Bertingkat)</option>
                            <option value="rental_fault">Kesalahan / Kendala Rental (100%)</option>
                            <option value="no_show">Pelanggan Tidak Hadir &ge; 1 Jam (0%)</option>
                        </select>
                    </div>
                    <div>
                        <label for="admin_cancel_reason" class="block font-semibold text-ink mb-1">Alasan Pembatalan</label>
                        <input id="admin_cancel_reason" name="cancellation_reason" required minlength="5" placeholder="Alasan pembatalan resmi" class="w-full rounded-lg border border-field-line bg-surface p-2">
                    </div>
                </div>
                <label class="inline-flex items-center gap-2 text-ink">
                    <input type="checkbox" name="contact_attempted" value="1" class="rounded border-field-line">
                    <span>Admin telah mencoba menghubungi pelanggan (wajib sebelum menetapkan tidak hadir / no-show)</span>
                </label>
                <div>
                    <button type="submit" class="rounded-lg bg-danger-action px-4 py-2 font-bold text-white">Proses Pembatalan &amp; Refund</button>
                </div>
            </form>
        @endif

        @php
            $adminHasOpenChange = $rental->changeRequests->contains(
                fn ($cr) => in_array($cr->status, [\App\Models\RentalChangeRequest::STATUS_PENDING_ADMIN, \App\Models\RentalChangeRequest::STATUS_PENDING_CUSTOMER], true)
            );
        @endphp

        @if ($rental->changeRequests->isNotEmpty() || in_array($rental->rental_status, [\App\Models\Rental::STATUS_PENDING, \App\Models\Rental::STATUS_CONFIRMED], true))
            <div class="space-y-4 border-t border-line pt-4">
                <h3 class="text-sm font-bold text-ink">Perubahan Jadwal &amp; Penggantian Unit (A-06)</h3>

                @foreach ($rental->changeRequests as $changeRequest)
                    <div class="rounded-xl border border-line bg-surface-raised p-4 text-xs space-y-2">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="font-bold text-ink">{{ $changeRequest->typeLabel() }} ({{ $changeRequest->initiated_by_role === 'admin' ? 'Usulan Admin' : 'Permohonan Pelanggan' }})</span>
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
                                <span class="font-semibold text-info">(Selisih biaya 0 / ditanggung rental)</span>
                            @endif
                        </p>
                        <p>Alasan: {{ $changeRequest->reason }}</p>
                        @if ($changeRequest->response_notes)
                            <p class="text-muted">Catatan keputusan: {{ $changeRequest->response_notes }}</p>
                        @endif

                        @if ($changeRequest->status === \App\Models\RentalChangeRequest::STATUS_PENDING_ADMIN)
                            <div class="pt-2 flex flex-col sm:flex-row gap-3">
                                <form method="POST" action="{{ route('admin.change-requests.respond', $changeRequest) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="decision" value="approve">
                                    <button type="submit" class="rounded-lg bg-action hover:bg-action-hover px-4 py-2 font-bold text-ink">
                                        Setujui Perubahan
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.change-requests.respond', $changeRequest) }}" class="flex-1 flex flex-col sm:flex-row gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="decision" value="reject">
                                    <input name="response_notes" required placeholder="Alasan penolakan perubahan" class="flex-1 rounded-lg border border-field-line bg-surface p-2">
                                    <button type="submit" class="rounded-lg border border-danger-line bg-danger-surface px-4 py-2 font-bold text-danger shrink-0">
                                        Tolak Perubahan
                                    </button>
                                </form>
                            </div>
                        @endif
                    </div>
                @endforeach

                @if (in_array($rental->rental_status, [\App\Models\Rental::STATUS_PENDING, \App\Models\Rental::STATUS_CONFIRMED], true) && ! $adminHasOpenChange)
                    <form method="POST" action="{{ route('admin.bookings.change-requests.propose', $rental) }}" class="rounded-xl border border-line bg-surface-raised p-4 space-y-3 text-xs">
                        @csrf
                        <h4 class="font-bold text-ink">Usulkan Perubahan Jadwal / Penggantian Kendaraan kepada Pelanggan</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label for="admin_request_type" class="block font-semibold text-ink mb-1">Jenis Penyesuaian</label>
                                <select id="admin_request_type" name="request_type" class="w-full rounded-lg border border-field-line bg-surface p-2">
                                    <option value="car_replacement">Penggantian Kendaraan</option>
                                    <option value="reschedule">Penjadwalan Ulang</option>
                                </select>
                            </div>
                            <div>
                                <label for="proposed_car_id" class="block font-semibold text-ink mb-1">Kendaraan Pengganti</label>
                                <select id="proposed_car_id" name="proposed_car_id" class="w-full rounded-lg border border-field-line bg-surface p-2">
                                    @foreach ($availableReplacementCars as $carOption)
                                        <option value="{{ $carOption->id }}" @selected($carOption->id === $rental->car_id)>
                                            {{ $carOption->name }} ({{ $carOption->plate_number }}) &mdash; Rp {{ number_format((float) $carOption->price_per_day, 0, ',', '.') }}/hari
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="admin_proposed_pickup_at" class="block font-semibold text-ink mb-1">Waktu Ambil Usulan (WIB)</label>
                                <input id="admin_proposed_pickup_at" name="proposed_pickup_at" type="datetime-local" value="{{ $rental->pickup_at?->timezone('Asia/Jakarta')->format('Y-m-d\TH:i') }}" class="w-full rounded-lg border border-field-line bg-surface p-2">
                            </div>
                            <div>
                                <label for="admin_proposed_total_days" class="block font-semibold text-ink mb-1">Jumlah Hari Usulan</label>
                                <input id="admin_proposed_total_days" name="proposed_total_days" type="number" min="1" max="365" value="{{ $rental->total_days }}" class="w-full rounded-lg border border-field-line bg-surface p-2">
                            </div>
                        </div>
                        <div>
                            <label for="admin_change_reason" class="block font-semibold text-ink mb-1">Alasan Resmi Penyesuaian</label>
                            <input id="admin_change_reason" name="reason" required placeholder="Contoh: Unit sebelumnya terlambat kembali / perlu penggantian setara" class="w-full rounded-lg border border-field-line bg-surface p-2">
                        </div>
                        <label class="inline-flex items-center gap-2 text-ink">
                            <input type="checkbox" name="waive_price_difference" value="1" class="rounded border-field-line">
                            <span>Bebaskan selisih biaya (biaya tambahan 0 untuk pesanan terdampak kendala rental)</span>
                        </label>
                        <div>
                            <button type="submit" class="rounded-lg bg-action hover:bg-action-hover px-4 py-2 font-bold text-ink">
                                Kirim Usulan ke Pelanggan
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        @endif

        @if ($rental->extensions->isNotEmpty())
            <div class="space-y-4 border-t border-line pt-4">
                <h3 class="text-sm font-bold text-ink">Pengajuan Perpanjangan Sewa (A-08)</h3>
                @foreach ($rental->extensions as $extension)
                    <div class="rounded-xl border border-line bg-surface-raised p-4 text-xs space-y-2">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="font-bold text-ink">+{{ $extension->additional_days }} Hari &middot; Biaya Tambahan Rp {{ number_format((float) $extension->additional_amount, 0, ',', '.') }}</span>
                            <span class="rounded-full bg-surface border border-line px-2.5 py-0.5 font-semibold text-ink">{{ $extension->statusLabel() }}</span>
                        </div>
                        <p class="text-muted">
                            Kembali awal: {{ $extension->previous_return_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB
                            &rarr; Target kembali baru: <strong class="text-ink">{{ $extension->proposed_return_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</strong>
                        </p>
                        @if ($extension->notes)
                            <p>Catatan: {{ $extension->notes }}</p>
                        @endif
                        @if ($extension->payment_proof)
                            <p>
                                <a href="{{ route('documents.extension-payment.show', $extension) }}" target="_blank" class="font-semibold text-link hover:underline">
                                    Buka Bukti Pembayaran Perpanjangan (Privat)
                                </a>
                            </p>
                        @endif

                        @if ($extension->status === \App\Models\RentalExtension::STATUS_PENDING_APPROVAL)
                            <div class="pt-2 flex flex-col sm:flex-row gap-3">
                                <form method="POST" action="{{ route('admin.extensions.approve', $extension) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="rounded-lg bg-action hover:bg-action-hover px-4 py-2 font-bold text-ink">
                                        Setujui Perpanjangan (Tunggu Bayar)
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.extensions.reject', $extension) }}" class="flex-1 flex flex-col sm:flex-row gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <input name="rejection_reason" required placeholder="Alasan penolakan perpanjangan" class="flex-1 rounded-lg border border-field-line bg-surface p-2">
                                    <button type="submit" class="rounded-lg border border-danger-line bg-danger-surface px-4 py-2 font-bold text-danger shrink-0">
                                        Tolak Perpanjangan
                                    </button>
                                </form>
                            </div>
                        @elseif ($extension->status === \App\Models\RentalExtension::STATUS_PAYMENT_SUBMITTED)
                            <div class="pt-2 space-y-3 border-t border-line">
                                <p class="text-muted">
                                    Dana terverifikasi masuk perpanjangan: <strong class="text-ink">Rp {{ number_format((float) $extension->paid_amount, 0, ',', '.') }}</strong>
                                    &middot; Sisa tagihan: <strong class="text-ink">Rp {{ number_format($extension->remainingExtensionBalance(), 0, ',', '.') }}</strong>
                                </p>
                                <form method="POST" action="{{ route('admin.extensions.payment.approve', $extension) }}" class="flex flex-col sm:flex-row gap-2 items-stretch sm:items-end">
                                    @csrf
                                    @method('PATCH')
                                    <div class="sm:w-56">
                                        <label for="ext_verified_amount_{{ $extension->id }}" class="block font-semibold text-ink mb-1">Nominal Aktual Diterima (Rp)</label>
                                        <input id="ext_verified_amount_{{ $extension->id }}" name="verified_amount" type="number" step="0.01" min="1" value="{{ number_format($extension->remainingExtensionBalance(), 2, '.', '') }}" required class="w-full rounded-lg border border-field-line bg-surface p-2">
                                    </div>
                                    <button type="submit" class="rounded-lg bg-action hover:bg-action-hover px-4 py-2 font-bold text-ink shrink-0">
                                        Verifikasi Lunas &amp; Aktifkan Jadwal Baru
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.extensions.payment.reject', $extension) }}" class="space-y-2 border-t border-line pt-2">
                                    @csrf
                                    @method('PATCH')
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                        <input name="rejection_reason" required placeholder="Alasan penolakan / pembatalan perpanjangan" class="rounded-lg border border-field-line bg-surface p-2">
                                        <input name="received_amount" type="number" step="0.01" min="0" placeholder="Dana masuk (opsional, Rp)" class="rounded-lg border border-field-line bg-surface p-2">
                                    </div>
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <label class="inline-flex items-center gap-2 text-ink">
                                            <input type="checkbox" name="cancel_extension" value="1" class="rounded border-field-line">
                                            <span>Batalkan perpanjangan (jadwal bentrok/batal) &amp; tetapkan refund 100% atas dana masuk</span>
                                        </label>
                                        <button type="submit" class="rounded-lg border border-danger-line bg-danger-surface px-4 py-2 font-bold text-danger shrink-0">
                                            Tolak / Batalkan Pembayaran Perpanjangan
                                        </button>
                                    </div>
                                </form>
                            </div>
                        @elseif (in_array($extension->status, [\App\Models\RentalExtension::STATUS_REJECTED, \App\Models\RentalExtension::STATUS_EXPIRED, \App\Models\RentalExtension::STATUS_COMPLETED], true) && $extension->refund_status === \App\Models\RentalExtension::REFUND_PENDING)
                            <form method="POST" action="{{ route('admin.extensions.refund', $extension) }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3 pt-3 border-t border-line items-end">
                                @csrf
                                @method('PATCH')
                                <div>
                                    <label for="ext_refund_amount_{{ $extension->id }}" class="block font-semibold mb-1">Nominal Refund Perpanjangan</label>
                                    <input id="ext_refund_amount_{{ $extension->id }}" name="transfer_amount" type="number" step="0.01" min="1" value="{{ number_format((float) $extension->refund_amount, 2, '.', '') }}" required class="w-full rounded-lg border border-field-line bg-surface p-2">
                                </div>
                                <div>
                                    <label for="ext_refund_at_{{ $extension->id }}" class="block font-semibold mb-1">Tanggal Transfer (WIB)</label>
                                    <input id="ext_refund_at_{{ $extension->id }}" name="transferred_at" type="datetime-local" value="{{ now('Asia/Jakarta')->format('Y-m-d\TH:i') }}" required class="w-full rounded-lg border border-field-line bg-surface p-2">
                                </div>
                                <div>
                                    <label for="ext_refund_ref_{{ $extension->id }}" class="block font-semibold mb-1">Nomor Referensi</label>
                                    <input id="ext_refund_ref_{{ $extension->id }}" name="refund_reference" required placeholder="REF-EXT-001" class="w-full rounded-lg border border-field-line bg-surface p-2">
                                </div>
                                <button type="submit" class="rounded-lg bg-action hover:bg-action-hover px-4 py-2 font-bold text-ink">Catat Transfer Refund</button>
                            </form>
                        @endif
                        @if ($extension->refund_status === \App\Models\RentalExtension::REFUND_PROCESSED)
                            <p class="text-ink">
                                Refund Perpanjangan Selesai: <strong>Rp {{ number_format((float) $extension->refund_amount, 0, ',', '.') }}</strong>
                                pada {{ $extension->refund_transferred_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB
                                (Ref: <strong class="font-mono">{{ $extension->refund_reference }}</strong>)
                            </p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        @if (($impactedNextBookings ?? collect())->isNotEmpty())
            <div role="alert" class="rounded-xl border border-danger-line bg-danger-surface p-4 text-xs text-danger space-y-1">
                <strong class="block">Peringatan Dampak pada Pesanan Berikutnya (SYS-10)</strong>
                @foreach ($impactedNextBookings as $impact)
                    <p><strong class="font-mono">{{ $impact['rental']->rental_code }}</strong> &mdash; {{ $impact['reason'] }}</p>
                @endforeach
            </div>
        @endif

        @if ($rental->inspections->isNotEmpty())
            <div class="space-y-3 border-t border-line pt-4">
                <h3 class="text-sm font-bold text-ink">Hasil Inspeksi Pengambilan &amp; Pengembalian (S-03, S-04)</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                    @foreach ($rental->inspections as $inspection)
                        <div class="rounded-xl border border-line bg-surface-raised p-4 space-y-1.5">
                            <p class="font-bold text-ink">{{ $inspection->typeLabel() }}</p>
                            <p class="text-muted">Pemeriksa: <strong class="text-ink">{{ $inspection->inspector?->name }}</strong> &middot; {{ $inspection->inspected_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</p>
                            <p>Kilometer: <strong>{{ number_format($inspection->odometer_km, 0, ',', '.') }} km</strong> &middot; BBM: <strong>{{ $inspection->fuelLevelLabel() }}</strong></p>
                            <p class="text-muted">{{ $inspection->condition_notes }}</p>
                            @if ($inspection->has_damage && $inspection->damage_description)
                                <p class="text-danger"><strong>Kerusakan:</strong> {{ $inspection->damage_description }}</p>
                            @endif
                            @if ($inspection->penalty_fee > 0)
                                <p class="text-warning"><strong>Denda Keterlambatan:</strong> Rp {{ number_format((float) $inspection->penalty_fee, 0, ',', '.') }}</p>
                            @endif
                            @if ($inspection->condition_photo)
                                <p><a href="{{ route('documents.inspection-photo.show', $inspection) }}" target="_blank" class="font-semibold text-link hover:underline">Buka Foto Kondisi (Privat)</a></p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if (in_array($rental->rental_status, [\App\Models\Rental::STATUS_RETURNED, \App\Models\Rental::STATUS_COMPLETED], true))
            <div class="space-y-4 border-t border-line pt-4">
                <h3 class="text-sm font-bold text-ink">Biaya Tambahan &amp; Penyelesaian Transaksi (A-09, SYS-12)</h3>

                <div class="rounded-xl border border-line bg-surface-raised p-4 text-xs space-y-2">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="font-bold text-ink">Total Tagihan Tambahan: Rp {{ number_format((float) $rental->additional_charges_total, 0, ',', '.') }}</span>
                        <span class="rounded-full bg-surface border border-line px-2.5 py-0.5 font-semibold text-ink">{{ $rental->additionalChargeStatusLabel() }}</span>
                    </div>
                    <p class="text-muted">
                        Denda telat ({{ $rental->late_minutes }} menit): <strong class="text-ink">Rp {{ number_format((float) $rental->late_fee_amount, 0, ',', '.') }}</strong>
                        &middot; BBM: <strong class="text-ink">Rp {{ number_format((float) $rental->fuel_fee_amount, 0, ',', '.') }}</strong>
                        &middot; Kerusakan: <strong class="text-ink">Rp {{ number_format((float) $rental->damage_fee_amount, 0, ',', '.') }}</strong>
                    </p>
                    <p class="text-muted">
                        Sudah dibayar: <strong class="text-ink">Rp {{ number_format((float) $rental->additional_charge_paid_amount, 0, ',', '.') }}</strong>
                        &middot; Sisa tagihan tambahan: <strong class="text-ink">Rp {{ number_format($rental->remainingAdditionalChargeBalance(), 0, ',', '.') }}</strong>
                    </p>
                    @if ($rental->fuel_fee_reason)
                        <p>Alasan BBM: {{ $rental->fuel_fee_reason }}</p>
                    @endif
                    @if ($rental->damage_fee_reason)
                        <p>Alasan Kerusakan: {{ $rental->damage_fee_reason }}</p>
                    @endif
                    @if ($rental->damage_evidence_path)
                        <p><a href="{{ route('documents.damage-evidence.show', $rental) }}" target="_blank" class="font-semibold text-link hover:underline">Buka Bukti Kerusakan (Privat)</a></p>
                    @endif
                    @if ($rental->additional_charge_proof)
                        <p><a href="{{ route('documents.additional-charge-proof.show', $rental) }}" target="_blank" class="font-semibold text-link hover:underline">Buka Bukti Bayar Tagihan Tambahan (Privat)</a></p>
                    @endif
                    @if ($rental->additional_charge_payment_reference)
                        <p class="text-ink">Referensi Pelunasan Tambahan: <strong class="font-mono">{{ $rental->additional_charge_payment_reference }}</strong></p>
                    @endif
                    @if ($rental->additional_charge_rejection_reason)
                        <p class="text-danger">Alasan penolakan bukti terakhir: {{ $rental->additional_charge_rejection_reason }}</p>
                    @endif
                    @if ($rental->completed_at)
                        <p class="text-success font-semibold">Transaksi ditutup selesai pada {{ $rental->completed_at->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB.</p>
                    @endif
                </div>

                @if ($rental->rental_status === \App\Models\Rental::STATUS_RETURNED && ! in_array($rental->additional_charge_status, [\App\Models\Rental::ADDITIONAL_CHARGE_PAID, \App\Models\Rental::ADDITIONAL_CHARGE_PENDING_APPROVAL], true))
                    <form method="POST" action="{{ route('admin.bookings.additional-charges.assess', $rental) }}" enctype="multipart/form-data" class="rounded-xl border border-line bg-surface-raised p-4 space-y-3 text-xs">
                        @csrf
                        <h4 class="font-bold text-ink">Tetapkan / Konfirmasi Biaya Tambahan Pascapengembalian (A-09)</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label for="fuel_fee_amount" class="block font-semibold text-ink mb-1">Biaya Kekurangan BBM (Rp)</label>
                                <input id="fuel_fee_amount" name="fuel_fee_amount" type="number" min="0" step="1000" required value="{{ old('fuel_fee_amount', (int) $rental->fuel_fee_amount) }}" class="w-full rounded-lg border border-field-line bg-surface p-2">
                            </div>
                            <div>
                                <label for="fuel_fee_reason" class="block font-semibold text-ink mb-1">Alasan Biaya BBM</label>
                                <input id="fuel_fee_reason" name="fuel_fee_reason" value="{{ old('fuel_fee_reason', $rental->fuel_fee_reason) }}" placeholder="Wajib jika biaya BBM > 0" class="w-full rounded-lg border border-field-line bg-surface p-2">
                            </div>
                            <div>
                                <label for="damage_fee_amount" class="block font-semibold text-ink mb-1">Biaya Kerusakan (Rp)</label>
                                <input id="damage_fee_amount" name="damage_fee_amount" type="number" min="0" step="1000" required value="{{ old('damage_fee_amount', (int) $rental->damage_fee_amount) }}" class="w-full rounded-lg border border-field-line bg-surface p-2">
                            </div>
                            <div>
                                <label for="damage_fee_reason" class="block font-semibold text-ink mb-1">Alasan Biaya Kerusakan</label>
                                <input id="damage_fee_reason" name="damage_fee_reason" value="{{ old('damage_fee_reason', $rental->damage_fee_reason) }}" placeholder="Wajib jika biaya kerusakan > 0" class="w-full rounded-lg border border-field-line bg-surface p-2">
                            </div>
                        </div>
                        <div>
                            <label for="damage_evidence" class="block font-semibold text-ink mb-1">Foto Bukti Kerusakan Tambahan (Opsional jika foto inspeksi akhir sudah ada)</label>
                            <input id="damage_evidence" name="damage_evidence" type="file" accept=".jpg,.jpeg,.png,.webp" class="w-full rounded-lg border border-field-line bg-surface p-2">
                        </div>
                        <button type="submit" class="rounded-lg bg-action hover:bg-action-hover px-4 py-2 font-bold text-ink">
                            Simpan Pemeriksaan Biaya Tambahan
                        </button>
                    </form>
                @endif

                @if ($rental->rental_status === \App\Models\Rental::STATUS_RETURNED && in_array($rental->additional_charge_status, [\App\Models\Rental::ADDITIONAL_CHARGE_UNPAID, \App\Models\Rental::ADDITIONAL_CHARGE_REJECTED], true))
                    <form method="POST" action="{{ route('admin.bookings.additional-charges.proof', $rental) }}" enctype="multipart/form-data" class="rounded-xl border border-line bg-surface-raised p-4 flex flex-col sm:flex-row items-stretch sm:items-end gap-3 text-xs">
                        @csrf
                        <div class="flex-1">
                            <label for="admin_add_charge_proof" class="block font-semibold text-ink mb-1">Unggah Bukti Bayar Tagihan Tambahan (Walk-In / Dibantu Admin)</label>
                            <input id="admin_add_charge_proof" name="additional_charge_proof" type="file" required accept=".jpg,.jpeg,.png,.webp" class="w-full rounded-lg border border-field-line bg-surface p-2">
                        </div>
                        <button type="submit" class="rounded-lg border border-line bg-surface px-4 py-2 font-bold text-ink shrink-0">Kirim Bukti Tambahan</button>
                    </form>
                @endif

                @if ($rental->rental_status === \App\Models\Rental::STATUS_RETURNED && $rental->additional_charge_status === \App\Models\Rental::ADDITIONAL_CHARGE_PENDING_APPROVAL)
                    <div class="rounded-xl border border-line bg-surface-raised p-4 space-y-3 text-xs">
                        <form method="POST" action="{{ route('admin.bookings.additional-charges.approve', $rental) }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
                            @csrf
                            @method('PATCH')
                            <div>
                                <label for="add_verified_amount" class="block font-semibold mb-1">Nominal Diterima (Rp)</label>
                                <input id="add_verified_amount" name="verified_amount" type="number" step="0.01" min="1" value="{{ number_format($rental->remainingAdditionalChargeBalance(), 2, '.', '') }}" required class="w-full rounded-lg border border-field-line bg-surface p-2">
                            </div>
                            <div>
                                <label for="add_payment_ref" class="block font-semibold mb-1">Referensi Pembayaran</label>
                                <input id="add_payment_ref" name="payment_reference" required placeholder="TRF-ADD-001" class="w-full rounded-lg border border-field-line bg-surface p-2">
                            </div>
                            <button type="submit" class="rounded-lg bg-action hover:bg-action-hover px-4 py-2 font-bold text-ink">Verifikasi Pelunasan Tambahan</button>
                        </form>
                        <form method="POST" action="{{ route('admin.bookings.additional-charges.reject', $rental) }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end border-t border-line pt-3">
                            @csrf
                            @method('PATCH')
                            <div>
                                <label for="add_reject_reason" class="block font-semibold mb-1">Alasan Penolakan</label>
                                <input id="add_reject_reason" name="rejection_reason" required minlength="5" placeholder="Nominal transfer kurang atau bukti buram" class="w-full rounded-lg border border-field-line bg-surface p-2">
                            </div>
                            <div>
                                <label for="add_received_amount" class="block font-semibold mb-1">Dana Masuk Parsial (Opsional)</label>
                                <input id="add_received_amount" name="received_amount" type="number" step="0.01" min="0" placeholder="0" class="w-full rounded-lg border border-field-line bg-surface p-2">
                            </div>
                            <button type="submit" class="rounded-lg border border-danger-line bg-danger-surface px-4 py-2 font-bold text-danger">Tolak Bukti Tambahan</button>
                        </form>
                    </div>
                @endif

                @if ($rental->rental_status === \App\Models\Rental::STATUS_RETURNED)
                    <form method="POST" action="{{ route('admin.bookings.complete', $rental) }}" class="rounded-xl border border-line bg-surface-raised p-4 flex flex-col sm:flex-row items-stretch sm:items-end gap-3 text-xs">
                        @csrf
                        @method('PATCH')
                        <div class="flex-1">
                            <label for="completion_notes" class="block font-semibold text-ink mb-1">Catatan Penyelesaian Transaksi (SYS-12)</label>
                            <input id="completion_notes" name="completion_notes" placeholder="Catatan penutupan transaksi (status kesiapan mobil tetap dicatat terpisah)" class="w-full rounded-lg border border-field-line bg-surface p-2">
                        </div>
                        <button type="submit" class="rounded-lg bg-action hover:bg-action-hover px-5 py-2 font-bold text-ink shrink-0">
                            Tutup &amp; Selesaikan Transaksi
                        </button>
                    </form>
                @endif
            </div>
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
