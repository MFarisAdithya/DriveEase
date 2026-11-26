<section id="pesanan" aria-labelledby="pesanan-title" class="scroll-mt-24 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 id="pesanan-title" class="text-xl font-bold text-ink">Pesanan</h2>
        </div>
    </div>

    @if ($errors->any())
        <ul role="alert" class="rounded-xl border border-danger-line bg-danger-surface p-4 text-xs text-danger space-y-1">
            @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    @endif

    <div class="bg-surface rounded-xl border border-line p-5 shadow-sm">
        <form method="GET" action="{{ route('customer.dashboard') }}#pesanan" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-3 items-end">
            <div>
                <label for="customer_history_search" class="block text-xs font-semibold text-ink mb-1">Cari Kode / Kendaraan</label>
                <input id="customer_history_search" name="search" value="{{ request('search') }}" placeholder="Mis. DE- atau Avanza..." class="w-full rounded-lg border border-field-line bg-surface-raised px-3 py-2 text-xs text-ink">
            </div>
            <div>
                <label for="customer_history_status" class="block text-xs font-semibold text-ink mb-1">Status Transaksi</label>
                <select id="customer_history_status" name="rental_status" class="w-full rounded-lg border border-field-line bg-surface-raised px-3 py-2 text-xs text-ink">
                    <option value="">Semua Status</option>
                    @foreach (['pending' => 'Menunggu Konfirmasi', 'confirmed' => 'Terkonfirmasi', 'picked_up' => 'Sedang Berjalan', 'returned' => 'Sudah Kembali', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan', 'expired' => 'Kedaluwarsa'] as $key => $label)
                        <option value="{{ $key }}" @selected(request('rental_status') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="customer_history_from" class="block text-xs font-semibold text-ink mb-1">Dari Tanggal (WIB)</label>
                <input id="customer_history_from" name="date_from" type="date" value="{{ request('date_from') }}" class="w-full rounded-lg border border-field-line bg-surface-raised px-3 py-2 text-xs text-ink">
            </div>
            <div>
                <label for="customer_history_to" class="block text-xs font-semibold text-ink mb-1">Sampai Tanggal (WIB)</label>
                <input id="customer_history_to" name="date_to" type="date" value="{{ request('date_to') }}" class="w-full rounded-lg border border-field-line bg-surface-raised px-3 py-2 text-xs text-ink">
            </div>
            <div class="flex flex-wrap items-center justify-end gap-2">
                <button type="submit" class="rounded-lg bg-action hover:bg-action-hover px-4 py-2 text-xs font-bold text-ink shadow-xs">
                    Cari Riwayat
                </button>
                @if (request()->anyFilled(['search', 'rental_status', 'date_from', 'date_to']))
                    <a href="{{ route('customer.dashboard') }}#pesanan" class="rounded-lg border border-line px-3 py-2 text-xs font-semibold text-danger hover:underline">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    <div class="bg-surface rounded-xl border border-line shadow-sm overflow-hidden">
        @forelse ($bookings as $booking)
            <div class="p-5 sm:p-6 border-b border-line last:border-b-0 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="space-y-1">
                    <div class="flex flex-wrap items-center gap-2 text-xs font-semibold">
                        <span class="font-mono text-ink bg-surface-hover px-2 py-0.5 rounded">{{ $booking->rental_code }}</span>
                        <span class="px-2.5 py-0.5 rounded-full bg-info-surface text-info">{{ $booking->rentalStatusLabel() }}</span>
                        <span class="px-2.5 py-0.5 rounded-full bg-warning-surface text-warning">{{ $booking->paymentStatusLabel() }}</span>
                        @if ($booking->payment_status === \App\Models\Rental::PAYMENT_PAID)
                            <span class="px-2.5 py-0.5 rounded-full bg-success-surface text-success">Bukti Transaksi Tersedia</span>
                        @endif
                    </div>
                    <h3 class="text-base font-bold text-ink">{{ $booking->car?->name ?? 'Kendaraan' }} ({{ $booking->car?->plate_number ?? '-' }})</h3>
                    <p class="text-xs text-muted">
                        Ambil: {{ $booking->pickup_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB &middot;
                        Kembali: {{ $booking->return_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB ({{ $booking->total_days }} hari)
                    </p>
                    <p class="text-xs text-muted">{{ $booking->nextActionNotice() }}</p>
                </div>
                <div class="text-right space-y-2 shrink-0">
                    <p class="text-base font-extrabold text-ink">Rp {{ number_format((float) $booking->total_amount + (float) $booking->additional_charges_total, 0, ',', '.') }}</p>
                    <a href="{{ route('customer.bookings.show', ['rental' => $booking] + request()->only('search', 'rental_status', 'date_from', 'date_to', 'page')) }}" class="inline-flex items-center justify-center rounded-lg border border-field-line bg-surface-raised hover:bg-surface-hover px-4 py-2 text-xs font-semibold text-ink">
                        Detail &amp; Bukti Transaksi &rarr;
                    </a>
                </div>
            </div>
        @empty
            <div class="p-5 sm:p-6 space-y-3">
                <p class="text-sm text-muted">{{ request()->anyFilled(['search', 'rental_status', 'date_from', 'date_to']) ? 'Tidak ada pesanan yang cocok dengan filter ini.' : 'Belum ada pesanan.' }}</p>
                <div class="flex justify-end">
                    <a href="{{ route('cars.index') }}" class="ui-button ui-secondary">Lihat Katalog Kendaraan</a>
                </div>
            </div>
        @endforelse
    </div>

    @if ($bookings->hasPages())
        <div>{{ $bookings->links('layouts.pagination') }}</div>
    @endif
</section>
