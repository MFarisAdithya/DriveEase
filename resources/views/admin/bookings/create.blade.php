@extends('layouts.admin')

@section('title', 'Pemesanan Langsung - DriveEase Admin')
@section('page_title', 'Pemesanan langsung')

@section('content')
<div class="mx-auto max-w-5xl space-y-6">
    <div>
        <a href="{{ route('admin.bookings.index') }}" class="text-xs font-semibold text-muted hover:text-ink">&larr; Kembali ke Daftar Pemesanan</a>
    </div>

    @if (! ($hasValidPaymentAccount ?? true))
        <div role="alert" class="rounded-xl border border-warning-line bg-warning-surface p-4 text-xs text-warning">
            <strong>Rekening tujuan pembayaran belum valid.</strong> Lengkapi rekening di <a href="{{ route('admin.rental-settings.edit') }}" class="underline font-bold">Pengaturan Rental</a> sebelum membuat pesanan.
        </div>
    @endif

    @if ($errors->any())
        <ul role="alert" class="rounded-xl border border-danger-line bg-danger-surface p-4 text-xs text-danger space-y-1">
            @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    @endif

    <div class="grid grid-cols-1 items-start gap-4 lg:grid-cols-12">
        {{-- Form identitas berada sebelum pemesanan dalam urutan tab.
             Pesan validasi pada kolom identitas membuka kembali form ini. --}}
        <details class="rounded-xl border border-line bg-surface shadow-sm lg:col-span-5"
                 @if ($errors->hasAny(['name', 'phone', 'address', 'ktp_number', 'ktp_image', 'sim_number', 'sim_image'])) open @endif>
            <summary class="cursor-pointer rounded-xl px-4 py-3 text-base font-bold text-ink">Catat Identitas Walk-in</summary>
            <form method="POST" action="{{ route('admin.bookings.register-customer') }}" enctype="multipart/form-data" class="space-y-4 border-t border-line p-4 sm:p-5">
                @csrf
                <p class="text-sm text-muted">Untuk pelanggan baru, catat KTP dan SIM agar admin dapat memeriksa identitas. Pelanggan tidak perlu membuat akun login.</p>

                <div>
                    <label for="reg_name" class="block text-xs font-semibold text-ink mb-1">Nama Lengkap</label>
                    <input id="reg_name" name="name" value="{{ old('name') }}" required class="w-full rounded-lg border border-field-line bg-surface-raised p-2.5 text-sm">
                </div>
                <div>
                    <label for="reg_phone" class="block text-xs font-semibold text-ink mb-1">Nomor Telepon / WhatsApp</label>
                    <input id="reg_phone" name="phone" type="tel" value="{{ old('phone') }}" required class="w-full rounded-lg border border-field-line bg-surface-raised p-2.5 text-sm">
                </div>
                <div>
                    <label for="reg_address" class="block text-xs font-semibold text-ink mb-1">Alamat (Opsional)</label>
                    <input id="reg_address" name="address" value="{{ old('address') }}" class="w-full rounded-lg border border-field-line bg-surface-raised p-2.5 text-sm">
                </div>
                {{-- Setiap label menunjuk ke input dengan id yang sama. Atribut accept
                    memandu pemilihan berkas; validasi format juga berjalan di server. --}}
                @foreach (['ktp' => 16, 'sim' => 14] as $type => $digits)
                    <div>
                        <label for="reg_{{ $type }}_number" class="block text-xs font-semibold text-ink mb-1">Nomor {{ strtoupper($type) }} ({{ $digits }} digit)</label>
                        <input id="reg_{{ $type }}_number" name="{{ $type }}_number" type="text" inputmode="numeric" pattern="{{ '[0-9]{'.$digits.'}' }}" maxlength="{{ $digits }}" required class="w-full rounded-lg border border-field-line bg-surface-raised p-2.5 text-sm">
                    </div>
                    <div>
                        <label for="reg_{{ $type }}_image" class="block text-xs font-semibold text-ink mb-1">Foto {{ strtoupper($type) }} (JPG/JPEG/PNG, maks 2 MB)</label>
                        <input id="reg_{{ $type }}_image" name="{{ $type }}_image" type="file" accept=".jpg,.jpeg,.png" required class="w-full rounded-lg border border-field-line bg-surface-raised p-2.5 text-sm">
                    </div>
                @endforeach

                <div class="flex justify-end">
                    <button type="submit" class="min-h-10 rounded-lg bg-action px-4 py-2 text-sm font-bold text-ink hover:bg-action-hover">
                        Simpan &amp; Lanjut ke Verifikasi
                    </button>
                </div>
            </form>
        </details>

        <form method="POST" action="{{ route('admin.bookings.store') }}" class="lg:col-span-7 bg-surface rounded-xl border border-line p-4 shadow-sm space-y-4 sm:p-6">
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">

            <div class="border-b border-line pb-3">
                <h2 class="text-base font-bold text-ink">Buat Pesanan Langsung</h2>
                <p class="text-xs text-muted mt-0.5">Pilih pelanggan dengan KTP dan SIM lengkap, lalu tentukan kendaraan dan jadwal (WIB). Tarif berlaku per 24 jam dengan jeda 1 jam antar pesanan.</p>
            </div>

            <div>
                <label for="customer_id" class="block text-xs font-semibold text-ink mb-1">Pelanggan</label>
                <select id="customer_id" name="customer_id" required class="w-full rounded-lg border border-field-line bg-surface-raised p-2.5 text-sm">
                    <option value="">-- Pilih Pelanggan --</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}" @selected((int) old('customer_id', $selectedCustomerId) === $customer->id)>
                            {{ $customer->name }} ({{ $customer->email ?? 'Tanpa akun login' }}) &mdash; {{ $customer->isVerified() ? 'Terverifikasi' : 'Belum Terverifikasi' }}
                        </option>
                    @endforeach
                </select>
                @error('customer_id')
                    <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="car_id" class="block text-xs font-semibold text-ink mb-1">Kendaraan</label>
                <select id="car_id" name="car_id" required class="w-full rounded-lg border border-field-line bg-surface-raised p-2.5 text-sm">
                    <option value="">-- Pilih Armada Siap Sewa --</option>
                    @foreach ($cars as $car)
                        <option value="{{ $car->id }}" @selected((int) old('car_id', $selectedCarId) === $car->id)>
                            {{ $car->name }} ({{ $car->plate_number }} &middot; Rp {{ number_format((float) $car->price_per_day, 0, ',', '.') }}/hari)
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
                    <input id="pickup_at" name="pickup_at" type="datetime-local" value="{{ old('pickup_at', $defaultPickupAt) }}" required class="w-full rounded-lg border border-field-line bg-surface-raised p-2.5 text-sm">
                </div>
                <div>
                    <label for="total_days" class="block text-xs font-semibold text-ink mb-1">Jumlah Hari</label>
                    <input id="total_days" name="total_days" type="number" min="1" max="365" value="{{ old('total_days', $defaultDays) }}" required class="w-full rounded-lg border border-field-line bg-surface-raised p-2.5 text-sm">
                </div>
            </div>

            <div>
                <label for="notes" class="block text-xs font-semibold text-ink mb-1">Catatan (Opsional)</label>
                <textarea id="notes" name="notes" rows="2" class="w-full rounded-lg border border-field-line bg-surface-raised p-2.5 text-sm">{{ old('notes') }}</textarea>
            </div>

            <div class="rounded-lg border border-line bg-surface-raised p-4 space-y-2">
                <details>
                    <summary class="w-fit cursor-pointer rounded py-1 text-sm font-bold text-link">Baca ketentuan rental</summary>
                    <p class="mt-2 whitespace-pre-line text-sm text-muted">{{ $terms }}</p>
                </details>
                <label class="flex items-start gap-2 pt-1 text-xs font-semibold text-ink">
                    <input id="accept_terms" name="accept_terms" type="checkbox" value="1" @checked(old('accept_terms')) required class="mt-0.5 rounded border-field-line">
                    <span>Pelanggan telah menyetujui ketentuan pembayaran transfer, pembatalan, dan denda DriveEase.</span>
                </label>
            </div>

            <label for="confirmation_method" class="block text-xs font-semibold">Metode Persetujuan Penyewa</label>
            <select id="confirmation_method" name="confirmation_method" aria-describedby="walk-in-confirmation-help" class="w-full rounded-lg border border-field-line bg-surface-raised p-2 text-sm">
                <option value="">Pilih untuk penyewa tanpa akun login</option>
                @foreach (['in_person' => 'Tatap muka', 'phone' => 'Telepon', 'message' => 'Pesan'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('confirmation_method') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <p id="walk-in-confirmation-help" class="text-xs text-muted">Wajib untuk penyewa tanpa akun login. Waktu persetujuan dan petugas dicatat saat pesanan dibuat.</p>
            <div class="flex justify-end">
                <button type="submit" class="min-h-10 rounded-lg bg-action px-5 py-2.5 text-sm font-bold text-ink hover:bg-action-hover">
                    Buat Pemesanan Langsung
                </button>
            </div>
        </form>

    </div>
</div>
@endsection
