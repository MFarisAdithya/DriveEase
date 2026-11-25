@extends('layouts.admin')

@section('title', 'Pemesanan Langsung - DriveEase Admin')
@section('page_title', 'Pemesanan Langsung Pelanggan (Walk-In)')

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

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <form method="POST" action="{{ route('admin.bookings.store') }}" class="lg:col-span-7 bg-surface rounded-xl border border-line p-6 shadow-sm space-y-4">
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">

            <div class="border-b border-line pb-3">
                <h2 class="text-base font-bold text-ink">Buat Pesanan Langsung</h2>
                <p class="text-xs text-muted mt-0.5">Memakai aturan harga 24 jam (WIB), jeda 1 jam, persetujuan ketentuan, dan verifikasi identitas yang sama dengan pemesanan online.</p>
            </div>

            <div>
                <label for="customer_id" class="block text-xs font-semibold text-ink mb-1">Pelanggan Terdaftar</label>
                <select id="customer_id" name="customer_id" required class="w-full rounded-lg border border-field-line bg-surface-raised p-2.5 text-sm">
                    <option value="">-- Pilih Pelanggan --</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}" @selected((int) old('customer_id', $selectedCustomerId) === $customer->id)>
                            {{ $customer->name }} ({{ $customer->email }}) &mdash; {{ $customer->isVerified() ? 'Terverifikasi' : 'Belum Terverifikasi' }}
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
                <p class="text-xs font-bold text-ink">Ketentuan Rental</p>
                <p class="whitespace-pre-line text-xs text-muted">{{ $terms }}</p>
                <label class="flex items-start gap-2 pt-1 text-xs font-semibold text-ink">
                    <input id="accept_terms" name="accept_terms" type="checkbox" value="1" @checked(old('accept_terms')) required class="mt-0.5 rounded border-field-line">
                    <span>Pelanggan menyetujui ketentuan pembayaran transfer, pembatalan, dan denda DriveEase.</span>
                </label>
            </div>

            <button type="submit" class="rounded-lg bg-action hover:bg-action-hover px-5 py-2.5 text-xs font-bold text-ink shadow-xs">
                Buat Pemesanan Langsung
            </button>
        </form>

        <form method="POST" action="{{ route('admin.bookings.register-customer') }}" class="lg:col-span-5 bg-surface rounded-xl border border-line p-6 shadow-sm space-y-4 self-start">
            @csrf
            <div class="border-b border-line pb-3">
                <h2 class="text-base font-bold text-ink">Bantu Daftarkan Pelanggan Baru</h2>
                <p class="text-xs text-muted mt-0.5">Pelanggan baru yang datang langsung didaftarkan berstatus menunggu verifikasi sebelum KTP/SIM disetujui.</p>
            </div>

            <div>
                <label for="reg_name" class="block text-xs font-semibold text-ink mb-1">Nama Lengkap</label>
                <input id="reg_name" name="name" value="{{ old('name') }}" required class="w-full rounded-lg border border-field-line bg-surface-raised p-2.5 text-sm">
            </div>
            <div>
                <label for="reg_email" class="block text-xs font-semibold text-ink mb-1">Email</label>
                <input id="reg_email" name="email" type="email" value="{{ old('email') }}" required class="w-full rounded-lg border border-field-line bg-surface-raised p-2.5 text-sm">
            </div>
            <div>
                <label for="reg_phone" class="block text-xs font-semibold text-ink mb-1">Nomor Telepon / WhatsApp</label>
                <input id="reg_phone" name="phone" type="tel" value="{{ old('phone') }}" required class="w-full rounded-lg border border-field-line bg-surface-raised p-2.5 text-sm">
            </div>
            <div>
                <label for="reg_address" class="block text-xs font-semibold text-ink mb-1">Alamat (Opsional)</label>
                <input id="reg_address" name="address" value="{{ old('address') }}" class="w-full rounded-lg border border-field-line bg-surface-raised p-2.5 text-sm">
            </div>
            <div>
                <label for="reg_password" class="block text-xs font-semibold text-ink mb-1">Kata Sandi Awal</label>
                <input id="reg_password" name="password" type="password" minlength="8" required class="w-full rounded-lg border border-field-line bg-surface-raised p-2.5 text-sm">
            </div>
            <div>
                <label for="reg_password_confirmation" class="block text-xs font-semibold text-ink mb-1">Konfirmasi Kata Sandi</label>
                <input id="reg_password_confirmation" name="password_confirmation" type="password" required class="w-full rounded-lg border border-field-line bg-surface-raised p-2.5 text-sm">
            </div>

            <button type="submit" class="w-full rounded-lg border border-field-line bg-surface-raised hover:bg-surface-hover px-4 py-2.5 text-xs font-bold text-ink">
                Daftarkan &amp; Lanjut ke Verifikasi
            </button>
        </form>
    </div>
</div>
@endsection
