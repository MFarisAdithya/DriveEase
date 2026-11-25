@extends('layouts.portal')

@section('title', 'Buat Pemesanan Kendaraan - DriveEase')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div>
        <a href="{{ route('customer.bookings.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-muted hover:text-ink">
            &larr; Kembali ke Daftar Pesanan
        </a>
        <h1 class="mt-2 text-2xl font-bold text-ink">Pemesanan Kendaraan Online</h1>
        <p class="mt-1 text-xs text-muted">Seluruh jadwal menggunakan Waktu Indonesia Barat (WIB) dengan durasi 24 jam per hari sewa.</p>
    </div>

    @if (! ($hasValidPaymentAccount ?? true))
        <div role="alert" class="rounded-xl border border-warning-line bg-warning-surface p-4 text-xs text-warning">
            <strong>Rekening tujuan pembayaran belum siap.</strong> Admin belum mengonfigurasi rekening tujuan pembayaran yang valid sehingga pemesanan baru sementara tidak dapat diproses.
        </div>
    @endif

    @if ($errors->any())
        <ul role="alert" class="rounded-xl border border-danger-line bg-danger-surface p-4 text-xs text-danger space-y-1">
            @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('customer.bookings.store') }}" class="bg-surface rounded-xl border border-line p-6 sm:p-8 shadow-sm space-y-5">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">

        <div>
            <label for="car_id" class="block text-xs font-semibold text-ink mb-1">Pilih Kendaraan</label>
            <select id="car_id" name="car_id" required class="w-full rounded-lg border {{ $errors->has('car_id') ? 'border-danger-line' : 'border-field-line' }} bg-surface-raised p-2.5 text-sm">
                <option value="">-- Pilih Armada Siap Sewa --</option>
                @foreach ($cars as $car)
                    <option value="{{ $car->id }}"
                            data-price="{{ (int) $car->price_per_day }}"
                            @selected((int) old('car_id', $selectedCarId) === $car->id)>
                        {{ $car->name }} ({{ $car->carType?->name ?? 'Mobil' }} &middot; Rp {{ number_format((float) $car->price_per_day, 0, ',', '.') }}/hari)
                    </option>
                @endforeach
            </select>
            @error('car_id')
                <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
            @enderror
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="pickup_at" class="block text-xs font-semibold text-ink mb-1">Waktu Pengambilan (WIB)</label>
                <input id="pickup_at" name="pickup_at" type="datetime-local" value="{{ old('pickup_at', $defaultPickupAt) }}" required class="w-full rounded-lg border {{ $errors->has('pickup_at') ? 'border-danger-line' : 'border-field-line' }} bg-surface-raised p-2.5 text-sm">
                @error('pickup_at')
                    <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="total_days" class="block text-xs font-semibold text-ink mb-1">Jumlah Hari Sewa</label>
                <input id="total_days" name="total_days" type="number" min="1" max="365" value="{{ old('total_days', $defaultDays) }}" required class="w-full rounded-lg border {{ $errors->has('total_days') ? 'border-danger-line' : 'border-field-line' }} bg-surface-raised p-2.5 text-sm">
                @error('total_days')
                    <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="rounded-xl border border-info-line bg-info-surface p-4 text-xs text-info space-y-1">
            <p class="font-bold">Ringkasan Jadwal &amp; Perkiraan Biaya</p>
            <p id="booking-summary-text">
                @if ($preview)
                    Jadwal kembali: <strong>{{ $preview['return_at']->format('d M Y H:i') }} WIB</strong>
                    &middot; Tarif: Rp {{ number_format($preview['daily_price'], 0, ',', '.') }}/hari
                    &middot; Estimasi Total: <strong>Rp {{ number_format($preview['total_amount'], 0, ',', '.') }}</strong>
                @else
                    Pilih armada, waktu pengambilan, dan jumlah hari untuk menghitung batas kembali dan total biaya.
                @endif
            </p>
            <p class="text-[11px]">Batas unggah bukti pembayaran transfer adalah 2 jam sejak pemesanan (tidak melewati waktu pengambilan).</p>
        </div>

        <div>
            <label for="notes" class="block text-xs font-semibold text-ink mb-1">Catatan Tambahan (Opsional)</label>
            <textarea id="notes" name="notes" rows="2" class="w-full rounded-lg border border-field-line bg-surface-raised p-2.5 text-sm">{{ old('notes') }}</textarea>
        </div>

        <div class="rounded-xl border border-line bg-surface-raised p-4 space-y-2">
            <h2 class="text-xs font-bold uppercase tracking-wider text-ink">Ketentuan Rental &amp; Kebijakan Biaya</h2>
            <p class="whitespace-pre-line text-xs text-muted leading-relaxed">{{ $terms }}</p>
            <label class="flex items-start gap-2.5 pt-2 text-xs font-semibold text-ink cursor-pointer">
                <input id="accept_terms" name="accept_terms" type="checkbox" value="1" @checked(old('accept_terms')) required class="mt-0.5 rounded border-field-line">
                <span>Saya telah membaca dan menyetujui ketentuan pembayaran, pembatalan, denda keterlambatan, dan biaya tambahan DriveEase.</span>
            </label>
            @error('accept_terms')
                <p class="text-xs font-medium text-danger">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center justify-end gap-3 pt-2">
            <a href="{{ route('cars.index') }}" class="rounded-lg px-4 py-2 text-xs font-semibold text-ink hover:bg-surface-hover">Batal</a>
            <button type="submit" class="rounded-lg bg-action hover:bg-action-hover px-6 py-2.5 text-xs font-bold text-ink shadow-sm transition-colors">
                Buat Pemesanan Sekarang
            </button>
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const carSelect = document.getElementById('car_id');
        const pickupInput = document.getElementById('pickup_at');
        const daysInput = document.getElementById('total_days');
        const summaryEl = document.getElementById('booking-summary-text');

        function updateEstimate() {
            const option = carSelect?.options[carSelect.selectedIndex];
            const dailyPrice = Number(option?.dataset?.price || 0);
            const days = parseInt(daysInput?.value || '0', 10);
            const pickupRaw = pickupInput?.value;

            if (!dailyPrice || !days || days < 1 || !pickupRaw) {
                return;
            }

            const total = dailyPrice * days;
            const pickupDate = new Date(pickupRaw);
            let returnInfo = '';
            if (!Number.isNaN(pickupDate.getTime())) {
                const returnDate = new Date(pickupDate.getTime() + days * 24 * 60 * 60 * 1000);
                returnInfo = 'Jadwal kembali: ' + returnDate.toLocaleString('id-ID', {
                    day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit'
                }) + ' WIB · ';
            }

            summaryEl.textContent = returnInfo + 'Tarif: Rp ' + dailyPrice.toLocaleString('id-ID') + '/hari · Total: Rp ' + total.toLocaleString('id-ID');
        }

        carSelect?.addEventListener('change', updateEstimate);
        pickupInput?.addEventListener('input', updateEstimate);
        daysInput?.addEventListener('input', updateEstimate);
    });
</script>
@endsection
