@extends('layouts.admin')

@section('title', 'Laporan Keuangan Rental - DriveEase Admin')
@section('page_title', 'Laporan Transaksi & Penerimaan Rental')

@section('content')
<div class="space-y-6">
    @if ($errors->any())
        <ul role="alert" class="rounded-xl border border-danger-line bg-danger-surface p-4 text-xs text-danger space-y-1">
            @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    @endif

    <div class="bg-surface rounded-xl border border-line p-5 shadow-sm">
        <form method="GET" action="{{ route('admin.reports.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
            <div>
                <label for="report_date_from" class="block text-xs font-semibold text-ink mb-1">Dari Tanggal (WIB)</label>
                <input id="report_date_from" name="date_from" type="date" value="{{ $dateFrom }}" class="w-full rounded-lg border border-field-line bg-surface-raised px-3 py-2 text-xs text-ink">
            </div>
            <div>
                <label for="report_date_to" class="block text-xs font-semibold text-ink mb-1">Sampai Tanggal (WIB)</label>
                <input id="report_date_to" name="date_to" type="date" value="{{ $dateTo }}" class="w-full rounded-lg border border-field-line bg-surface-raised px-3 py-2 text-xs text-ink">
            </div>
            <div>
                <label for="report_rental_status" class="block text-xs font-semibold text-ink mb-1">Status Transaksi</label>
                <select id="report_rental_status" name="rental_status" class="w-full rounded-lg border border-field-line bg-surface-raised px-3 py-2 text-xs text-ink">
                    <option value="">Semua Status</option>
                    @foreach ($statusLabels as $key => $label)
                        <option value="{{ $key }}" @selected($rentalStatus === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="report_booking_channel" class="block text-xs font-semibold text-ink mb-1">Kanal Pemesanan</label>
                <select id="report_booking_channel" name="booking_channel" class="w-full rounded-lg border border-field-line bg-surface-raised px-3 py-2 text-xs text-ink">
                    <option value="">Semua Kanal</option>
                    <option value="online" @selected($bookingChannel === 'online')>Online</option>
                    <option value="walk_in" @selected($bookingChannel === 'walk_in')>Langsung (Admin)</option>
                </select>
            </div>
            <div class="flex items-center gap-2">
                <button type="submit" class="rounded-lg bg-action hover:bg-action-hover px-4 py-2 text-xs font-bold text-ink shadow-xs">
                    Tampilkan Laporan
                </button>
                <a href="{{ route('admin.reports.index') }}" class="rounded-lg border border-line px-3 py-2 text-xs font-semibold text-muted hover:text-ink">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="bg-surface rounded-xl border border-line p-5 shadow-sm">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted">Jumlah Transaksi</span>
            <p class="text-2xl font-bold text-ink mt-2">{{ $summary['total_transactions'] }}</p>
            <p class="text-xs text-muted mt-1">{{ $summary['completed_transactions'] }} selesai &middot; {{ $summary['cancelled_or_expired_transactions'] }} batal/kedaluwarsa</p>
        </div>
        <div class="bg-surface rounded-xl border border-line p-5 shadow-sm">
            <span class="text-xs font-semibold uppercase tracking-wider text-success">Penerimaan Kotor</span>
            <p class="text-2xl font-bold text-success mt-2">Rp {{ number_format($summary['gross_receipts'], 0, ',', '.') }}</p>
            <p class="text-xs text-muted mt-1">Sewa, perpanjangan, &amp; biaya tambahan</p>
        </div>
        <div class="bg-surface rounded-xl border border-line p-5 shadow-sm">
            <span class="text-xs font-semibold uppercase tracking-wider text-danger">Refund Terealisasi</span>
            <p class="text-2xl font-bold text-danger mt-2">Rp {{ number_format($summary['processed_refunds'], 0, ',', '.') }}</p>
            <p class="text-xs text-muted mt-1">Dana telah ditransfer kembali</p>
        </div>
        <div class="bg-surface rounded-xl border border-line p-5 shadow-sm">
            <span class="text-xs font-semibold uppercase tracking-wider text-warning">Refund Menunggu</span>
            <p class="text-2xl font-bold text-warning mt-2">Rp {{ number_format($summary['pending_refunds'], 0, ',', '.') }}</p>
            <p class="text-xs text-muted mt-1">Kewajiban refund belum ditransfer</p>
        </div>
        <div class="bg-surface rounded-xl border border-line p-5 shadow-sm">
            <span class="text-xs font-semibold uppercase tracking-wider text-ink">Penerimaan Bersih</span>
            <p class="text-2xl font-bold text-ink mt-2">Rp {{ number_format($summary['net_receipts'], 0, ',', '.') }}</p>
            <p class="text-xs text-muted mt-1">Penerimaan kotor dikurangi refund terealisasi</p>
        </div>
    </div>

    <div class="bg-surface rounded-xl border border-line shadow-sm overflow-hidden">
        <div class="p-5 border-b border-line flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
                <h2 class="text-base font-bold text-ink">Rincian Transaksi Periode {{ $dateFrom }} s.d. {{ $dateTo }}</h2>
                <p class="text-xs text-muted">Setiap angka pada kartu ringkasan dihitung langsung dari penjumlahan baris tabel berikut.</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-raised text-[11px] font-semibold text-muted uppercase tracking-wider border-b border-line">
                        <th class="py-3 px-4">Kode</th>
                        <th class="py-3 px-4">Pelanggan &amp; Unit</th>
                        <th class="py-3 px-4">Jadwal Ambil (WIB)</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 text-right">Penerimaan Kotor</th>
                        <th class="py-3 px-4 text-right">Refund Terealisasi</th>
                        <th class="py-3 px-4 text-right">Refund Menunggu</th>
                        <th class="py-3 px-4 text-right">Penerimaan Bersih</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line text-xs">
                    @forelse ($rows as $row)
                        @php($rental = $row['rental'])
                        <tr class="hover:bg-surface-raised/70">
                            <td class="py-3 px-4 font-mono font-bold text-ink">
                                <a href="{{ route('admin.bookings.show', $rental) }}" class="text-link hover:underline">{{ $rental->rental_code }}</a>
                            </td>
                            <td class="py-3 px-4">
                                <span class="font-semibold text-ink block">{{ $rental->user?->name ?? '-' }}</span>
                                <span class="text-muted">{{ $rental->car?->name ?? '-' }} &middot; {{ $rental->channelLabel() }}</span>
                            </td>
                            <td class="py-3 px-4 text-muted">
                                {{ $rental->pickup_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB
                            </td>
                            <td class="py-3 px-4">
                                <span class="font-semibold text-ink block">{{ $rental->rentalStatusLabel() }}</span>
                                <span class="text-muted">{{ $rental->paymentStatusLabel() }}</span>
                            </td>
                            <td class="py-3 px-4 text-right font-semibold text-success">Rp {{ number_format($row['gross_receipts'], 0, ',', '.') }}</td>
                            <td class="py-3 px-4 text-right font-semibold text-danger">Rp {{ number_format($row['processed_refunds'], 0, ',', '.') }}</td>
                            <td class="py-3 px-4 text-right font-semibold text-warning">Rp {{ number_format($row['pending_refunds'], 0, ',', '.') }}</td>
                            <td class="py-3 px-4 text-right font-bold text-ink">Rp {{ number_format($row['net_receipts'], 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-10 text-center text-sm text-muted">Tidak ada transaksi pada periode atau filter yang dipilih.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot>
                        <tr class="bg-surface-raised border-t border-line text-xs font-bold text-ink">
                            <td colspan="4" class="py-3 px-4 text-right uppercase tracking-wider">Total Periode ({{ $summary['total_transactions'] }} Transaksi)</td>
                            <td class="py-3 px-4 text-right text-success">Rp {{ number_format($summary['gross_receipts'], 0, ',', '.') }}</td>
                            <td class="py-3 px-4 text-right text-danger">Rp {{ number_format($summary['processed_refunds'], 0, ',', '.') }}</td>
                            <td class="py-3 px-4 text-right text-warning">Rp {{ number_format($summary['pending_refunds'], 0, ',', '.') }}</td>
                            <td class="py-3 px-4 text-right text-ink">Rp {{ number_format($summary['net_receipts'], 0, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection
