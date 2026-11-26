@extends('layouts.portal')

@section('title', 'Jadwal Operasional Staf - DriveEase')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-ink">Jadwal Operasional Pengambilan &amp; Pengembalian</h1>
            <p class="text-xs text-muted mt-0.5">Menampilkan pesanan terkonfirmasi, sedang berjalan, dan pengembalian tanpa membuka dokumen atau bukti bayar privat.</p>
        </div>
        <a href="{{ route('staff.readiness.index') }}" class="ml-auto rounded-lg border border-line bg-surface px-4 py-2 text-xs font-bold text-ink hover:bg-surface-hover">
            Kesiapan Armada &rarr;
        </a>
    </div>

    @if ($impactedNextBookings->isNotEmpty())
        <div role="alert" class="rounded-xl border border-danger-line bg-danger-surface p-4 text-xs text-danger space-y-1">
            <strong class="block">Peringatan Pesanan Berikutnya Terdampak</strong>
            @foreach ($impactedNextBookings as $impact)
                <p><strong class="font-mono">{{ $impact['rental']->rental_code }}</strong> &mdash; {{ $impact['reason'] }}</p>
            @endforeach
        </div>
    @endif

    <form method="GET" action="{{ route('staff.schedules.index') }}" class="bg-surface rounded-xl border border-line p-4 grid grid-cols-2 items-end gap-3 text-sm lg:grid-cols-[1fr_1fr_1.25fr_auto]">
        <div>
            <label for="from" class="block font-semibold text-ink mb-1">Dari Tanggal</label>
            <input id="from" name="from" type="date" value="{{ request('from') }}" class="w-full min-w-0 rounded-lg border border-field-line bg-surface-raised p-2">
        </div>
        <div>
            <label for="to" class="block font-semibold text-ink mb-1">Sampai Tanggal</label>
            <input id="to" name="to" type="date" value="{{ request('to') }}" class="w-full min-w-0 rounded-lg border border-field-line bg-surface-raised p-2">
        </div>
        <div class="col-span-2 lg:col-span-1">
            <label for="status" class="block font-semibold text-ink mb-1">Status</label>
            <select id="status" name="status" class="w-full min-w-0 rounded-lg border border-field-line bg-surface-raised p-2">
                <option value="">Semua Status Operasional</option>
                <option value="confirmed" @selected(request('status') === 'confirmed')>Terkonfirmasi (Siap Ambil)</option>
                <option value="picked_up" @selected(request('status') === 'picked_up')>Sedang Berjalan</option>
                <option value="returned" @selected(request('status') === 'returned')>Sudah Dikembalikan</option>
            </select>
        </div>
        <div class="col-span-2 flex items-center justify-end gap-3 lg:col-span-1">
            @if (request()->anyFilled(['from', 'to', 'status']))
                <a href="{{ route('staff.schedules.index') }}" class="text-danger font-semibold hover:underline py-2">Reset</a>
            @endif
            <button type="submit" class="rounded-lg bg-action hover:bg-action-hover px-4 py-2 font-bold text-ink">Filter Jadwal</button>
        </div>
    </form>

    <div class="bg-surface rounded-xl border border-line shadow-sm overflow-hidden">
        <div role="region" aria-label="Jadwal pengambilan dan pengembalian" tabindex="0" class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-raised text-[11px] font-semibold text-muted uppercase tracking-wider border-b border-line">
                        <th scope="col" class="px-3 py-3 lg:px-4">Kode</th>
                        <th scope="col" class="px-3 py-3 lg:px-4">Kendaraan &amp; Kesiapan</th>
                        <th scope="col" class="px-3 py-3 lg:px-4">Penyewa &amp; Verifikasi</th>
                        <th scope="col" class="px-3 py-3 lg:px-4">Jadwal (WIB)</th>
                        <th scope="col" class="px-3 py-3 lg:px-4">Pembayaran</th>
                        <th scope="col" class="px-3 py-3 lg:px-4">Status</th>
                        <th scope="col" class="px-3 py-3 lg:px-4 text-right">Tindakan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line text-xs">
                    @forelse ($schedules as $item)
                        <tr class="hover:bg-surface-raised/80">
                            <td class="px-3 py-3 lg:px-4 font-mono font-bold text-ink">{{ $item->rental_code }}</td>
                            <td class="px-3 py-3 lg:px-4">
                                <span class="font-bold text-ink block">{{ $item->car?->name }} ({{ $item->car?->plate_number }})</span>
                                <span class="text-muted">{{ $item->car?->readinessLabel() }}</span>
                            </td>
                            <td class="px-3 py-3 lg:px-4">
                                <span class="font-semibold text-ink block">{{ $item->user?->name }}</span>
                                <span class="text-muted">{{ $item->user?->phone ?? '-' }} &middot; Identitas: {{ $item->user?->isVerified() ? 'Terverifikasi' : 'Belum Terverifikasi' }}</span>
                            </td>
                            <td class="px-3 py-3 lg:px-4 text-muted">
                                <span>Ambil: <strong class="text-ink">{{ $item->pickup_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }}</strong></span>
                                <span class="block">Kembali: <strong class="text-ink">{{ $item->return_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }}</strong></span>
                            </td>
                            <td class="px-3 py-3 lg:px-4 font-semibold text-ink">{{ $item->paymentStatusLabel() }}</td>
                            <td class="px-3 py-3 lg:px-4">
                                <span class="font-semibold text-ink block">{{ $item->rentalStatusLabel() }}</span>
                                @if ($item->isReturnOverdue())
                                    <span class="text-danger font-bold">Terlambat Kembali</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 lg:px-4 text-right">
                                <a href="{{ route('staff.schedules.show', $item) }}" aria-label="Buka tugas {{ $item->rental_code }}" class="inline-flex min-h-10 items-center whitespace-nowrap font-semibold text-link hover:underline">Buka Tugas &rarr;</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-10 text-center text-muted">Tidak ada jadwal operasional pada periode ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($schedules->hasPages())
            <div class="border-t border-line p-4">{{ $schedules->links('layouts.pagination') }}</div>
        @endif
    </div>
</div>
@endsection
