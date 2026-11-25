@extends('layouts.portal')

@section('title', 'Operasional ' . $rental->rental_code . ' - DriveEase Staf')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('staff.schedules.index') }}" class="text-xs font-semibold text-muted hover:text-ink">&larr; Kembali ke Jadwal Operasional</a>
        <span class="font-mono text-xs font-bold px-3 py-1 rounded-full bg-surface border border-line text-ink">{{ $rental->rental_code }}</span>
    </div>

    @if ($errors->any())
        <ul role="alert" class="rounded-xl border border-danger-line bg-danger-surface p-4 text-xs text-danger space-y-1">
            @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    @endif

    @if ($carImpacts->isNotEmpty())
        <div role="alert" class="rounded-xl border border-danger-line bg-danger-surface p-4 text-xs text-danger space-y-1">
            <strong class="block">Peringatan Jadwal Pesanan Berikutnya Terdampak</strong>
            @foreach ($carImpacts as $impact)
                <p><strong class="font-mono">{{ $impact['rental']->rental_code }}</strong> &mdash; {{ $impact['reason'] }}</p>
            @endforeach
        </div>
    @endif

    <div class="bg-surface rounded-xl border border-line shadow-sm p-6 space-y-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-line pb-4">
            <div>
                <h1 class="text-xl font-bold text-ink">{{ $rental->car?->name }} ({{ $rental->car?->plate_number }})</h1>
                <p class="text-xs text-muted mt-0.5">
                    Penyewa: <strong class="text-ink">{{ $rental->user?->name }}</strong> ({{ $rental->user?->phone ?? '-' }})
                    &middot; Verifikasi Identitas: <strong>{{ $rental->user?->isVerified() ? 'Terverifikasi' : 'Belum Terverifikasi' }}</strong>
                    &middot; Status Bayar: <strong>{{ $rental->paymentStatusLabel() }}</strong>
                </p>
            </div>
            <div class="text-xs text-right">
                <span class="block font-bold text-ink">{{ $rental->rentalStatusLabel() }}</span>
                <span class="text-muted">{{ $rental->car?->readinessLabel() }}</span>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
            <div class="rounded-lg border border-line bg-surface-raised p-3">
                <span class="text-muted block">Jadwal Pengambilan</span>
                <strong class="text-ink">{{ $rental->pickup_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</strong>
            </div>
            <div class="rounded-lg border border-line bg-surface-raised p-3">
                <span class="text-muted block">Batas Pengembalian (Tetap)</span>
                <strong class="text-ink">{{ $rental->return_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</strong>
            </div>
            <div class="rounded-lg border border-line bg-surface-raised p-3">
                <span class="text-muted block">Ambil / Kembali Aktual</span>
                <strong class="text-ink block">{{ $rental->actual_pickup_at?->timezone('Asia/Jakarta')->format('d M Y H:i') ?? 'Belum diambil' }}</strong>
                @if ($rental->actual_return_at)
                    <span class="text-muted">Kembali: {{ $rental->actual_return_at->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</span>
                @endif
            </div>
        </div>

        @if ($rental->rental_status === \App\Models\Rental::STATUS_CONFIRMED)
            @if (! $handoverCheck['ready'])
                <div role="alert" class="rounded-xl border border-warning-line bg-warning-surface p-4 text-xs text-warning space-y-1">
                    <strong>Serah-Terima Belum Dapat Dilakukan:</strong>
                    <ul class="list-disc pl-4">
                        @foreach ($handoverCheck['reasons'] as $reason)
                            <li>{{ $reason }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('staff.schedules.pickup', $rental) }}" enctype="multipart/form-data" class="rounded-xl border border-line bg-surface-raised p-5 space-y-4 text-xs">
                @csrf
                <h2 class="text-sm font-bold text-ink">Inspeksi Awal &amp; Konfirmasi Pengambilan Kendaraan (S-03)</h2>
                <label class="inline-flex items-center gap-2 text-ink font-semibold">
                    <input type="checkbox" name="identity_checked" value="1" required class="rounded border-field-line">
                    <span>Saya telah memeriksa KTP &amp; SIM asli penyewa sesuai akun terverifikasi.</span>
                </label>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label for="actual_pickup_at" class="block font-semibold text-ink mb-1">Waktu Ambil Aktual (WIB)</label>
                        <input id="actual_pickup_at" name="actual_pickup_at" type="datetime-local" value="{{ old('actual_pickup_at', now('Asia/Jakarta')->format('Y-m-d\TH:i')) }}" class="w-full rounded-lg border border-field-line bg-surface p-2">
                    </div>
                    <div>
                        <label for="pickup_odometer_km" class="block font-semibold text-ink mb-1">Kilometer Awal (km)</label>
                        <input id="pickup_odometer_km" name="odometer_km" type="number" min="0" required value="{{ old('odometer_km') }}" class="w-full rounded-lg border border-field-line bg-surface p-2">
                    </div>
                    <div>
                        <label for="pickup_fuel_level" class="block font-semibold text-ink mb-1">Kondisi Bahan Bakar Awal</label>
                        <select id="pickup_fuel_level" name="fuel_level" required class="w-full rounded-lg border border-field-line bg-surface p-2">
                            @foreach ($fuelLevels as $key => $label)
                                <option value="{{ $key }}" @selected(old('fuel_level', 'full') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label for="pickup_condition_notes" class="block font-semibold text-ink mb-1">Catatan Kondisi Fisik Awal</label>
                    <textarea id="pickup_condition_notes" name="condition_notes" rows="2" placeholder="Catat goresan lama, ban serep, kelengkapan surat kendaraan..." class="w-full rounded-lg border border-field-line bg-surface p-2">{{ old('condition_notes') }}</textarea>
                </div>
                <div>
                    <label for="pickup_photo" class="block font-semibold text-ink mb-1">Foto Kondisi Fisik Awal (Wajib)</label>
                    <input id="pickup_photo" name="condition_photo" type="file" required accept=".jpg,.jpeg,.png,.webp" class="w-full rounded-lg border border-field-line bg-surface p-2">
                </div>
                <button type="submit" class="rounded-lg bg-action hover:bg-action-hover px-5 py-2.5 font-bold text-ink">
                    Konfirmasi Serah-Terima Kendaraan
                </button>
            </form>
        @elseif ($rental->rental_status === \App\Models\Rental::STATUS_PICKED_UP)
            @php $pickupData = $rental->pickupInspection; @endphp
            <form method="POST" action="{{ route('staff.schedules.return', $rental) }}" enctype="multipart/form-data" class="rounded-xl border border-line bg-surface-raised p-5 space-y-4 text-xs">
                @csrf
                <h2 class="text-sm font-bold text-ink">Inspeksi Akhir &amp; Konfirmasi Pengembalian Kendaraan (S-04)</h2>
                @if ($pickupData)
                    <p class="text-muted">Pembanding inspeksi awal: <strong class="text-ink">{{ number_format($pickupData->odometer_km, 0, ',', '.') }} km</strong> &middot; BBM: <strong class="text-ink">{{ $pickupData->fuelLevelLabel() }}</strong></p>
                @endif
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label for="actual_return_at" class="block font-semibold text-ink mb-1">Waktu Kembali Aktual (WIB)</label>
                        <input id="actual_return_at" name="actual_return_at" type="datetime-local" value="{{ old('actual_return_at', now('Asia/Jakarta')->format('Y-m-d\TH:i')) }}" class="w-full rounded-lg border border-field-line bg-surface p-2">
                    </div>
                    <div>
                        <label for="return_odometer_km" class="block font-semibold text-ink mb-1">Kilometer Akhir (min {{ $pickupData?->odometer_km ?? 0 }} km)</label>
                        <input id="return_odometer_km" name="odometer_km" type="number" min="{{ $pickupData?->odometer_km ?? 0 }}" required value="{{ old('odometer_km', $pickupData?->odometer_km) }}" class="w-full rounded-lg border border-field-line bg-surface p-2">
                    </div>
                    <div>
                        <label for="return_fuel_level" class="block font-semibold text-ink mb-1">Kondisi Bahan Bakar Akhir</label>
                        <select id="return_fuel_level" name="fuel_level" required class="w-full rounded-lg border border-field-line bg-surface p-2">
                            @foreach ($fuelLevels as $key => $label)
                                <option value="{{ $key }}" @selected(old('fuel_level', $pickupData?->fuel_level ?? 'full') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label for="return_condition_notes" class="block font-semibold text-ink mb-1">Catatan Kondisi Pengembalian</label>
                    <textarea id="return_condition_notes" name="condition_notes" rows="2" placeholder="Catatan kebersihan, kelengkapan, atau kondisi kendaraan saat dikembalikan" class="w-full rounded-lg border border-field-line bg-surface p-2">{{ old('condition_notes') }}</textarea>
                </div>
                <label class="inline-flex items-center gap-2 text-ink font-semibold">
                    <input type="checkbox" name="has_damage" value="1" @checked(old('has_damage')) class="rounded border-field-line">
                    <span>Ditemukan kerusakan baru pada kendaraan (tahan unit pada status perawatan)</span>
                </label>
                <div>
                    <label for="damage_description" class="block font-semibold text-ink mb-1">Deskripsi Kerusakan (Wajib jika ada kerusakan)</label>
                    <textarea id="damage_description" name="damage_description" rows="2" placeholder="Jelaskan bagian yang rusak dan dampaknya" class="w-full rounded-lg border border-field-line bg-surface p-2">{{ old('damage_description') }}</textarea>
                </div>
                <div>
                    <label for="return_photo" class="block font-semibold text-ink mb-1">Foto Kondisi Akhir Kendaraan (Wajib)</label>
                    <input id="return_photo" name="condition_photo" type="file" required accept=".jpg,.jpeg,.png,.webp" class="w-full rounded-lg border border-field-line bg-surface p-2">
                </div>
                <button type="submit" class="rounded-lg bg-action hover:bg-action-hover px-5 py-2.5 font-bold text-ink">
                    Simpan Pengembalian &amp; Inspeksi Akhir
                </button>
            </form>
        @endif

        @if ($rental->inspections->isNotEmpty())
            <div class="space-y-3 border-t border-line pt-4">
                <h2 class="text-sm font-bold text-ink">Perbandingan Hasil Inspeksi Awal &amp; Akhir</h2>
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
                                <p><a href="{{ route('documents.inspection-photo.show', $inspection) }}" target="_blank" class="font-semibold text-link hover:underline">Lihat Foto Kondisi (Privat)</a></p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
