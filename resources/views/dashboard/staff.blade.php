@extends('layouts.portal')

@section('title', 'Dasbor Staf Operasional - DriveEase')

@section('content')
<div class="space-y-6">
    <!-- Welcome Banner & Operational Shortcuts -->
    <div class="bg-surface rounded-xl p-6 border border-line shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-ink">Selamat Bertugas, {{ auth()->user()->name }}!</h2>
            <p class="text-sm text-muted mt-1">
                Ringkasan jadwal pengambilan, pengembalian, keterlambatan, dan kesiapan armada operasional hari ini.
            </p>
        </div>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('staff.schedules.index') }}" class="rounded-lg bg-action hover:bg-action-hover px-4 py-2 text-xs font-bold text-ink">
                Jadwal Operasional
            </a>
            <a href="{{ route('staff.readiness.index') }}" class="rounded-lg border border-line bg-surface-raised hover:bg-surface-hover px-4 py-2 text-xs font-bold text-ink">
                Kesiapan Armada
            </a>
        </div>
    </div>

    @if ($impactedNextBookings->isNotEmpty())
        <div role="alert" class="rounded-xl border border-danger-line bg-danger-surface p-4 text-xs text-danger space-y-1.5">
            <strong class="block text-sm">Peringatan Pesanan Berikutnya Terdampak ({{ $impactedNextBookings->count() }})</strong>
            <ul class="list-disc pl-4 space-y-1">
                @foreach ($impactedNextBookings as $impact)
                    <li>
                        <strong class="font-mono">{{ $impact['rental']->rental_code }}</strong> ({{ $impact['car']->name }}) &mdash; {{ $impact['reason'] }}
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Operational Summary Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <div class="bg-surface rounded-xl p-5 border border-line shadow-sm">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted">Menunggu Pengambilan</span>
            <p class="text-3xl font-bold text-ink mt-2">{{ $upcomingPickups->count() }}</p>
            <p class="text-xs text-muted mt-1">Pesanan lunas &amp; terkonfirmasi</p>
        </div>
        <div class="bg-surface rounded-xl p-5 border border-line shadow-sm">
            <span class="text-xs font-semibold uppercase tracking-wider text-warning">Sedang Berjalan / Kembali</span>
            <p class="text-3xl font-bold text-warning mt-2">{{ $activeReturns->count() }}</p>
            <p class="text-xs text-muted mt-1">{{ $overdueTrips->count() }} terlambat melewati batas kembali</p>
        </div>
        <div class="bg-surface rounded-xl p-5 border border-line shadow-sm">
            <span class="text-xs font-semibold uppercase tracking-wider text-success">Unit Siap Serah-Terima</span>
            <p class="text-3xl font-bold text-success mt-2">{{ $availableCars }} / {{ $totalCars }}</p>
            <p class="text-xs text-muted mt-1">Unit aktif &amp; lolos persiapan</p>
        </div>
        <div class="bg-surface rounded-xl p-5 border border-line shadow-sm">
            <span class="text-xs font-semibold uppercase tracking-wider text-danger">Perlu Persiapan / Servis</span>
            <p class="text-3xl font-bold text-danger mt-2">{{ $carsNeedingAttention->count() }}</p>
            <p class="text-xs text-muted mt-1">Unit ditahan sebelum dinyatakan layak</p>
        </div>
    </div>

    <!-- Active Handovers & Returns -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-surface rounded-xl border border-line shadow-sm overflow-hidden">
            <div class="p-5 border-b border-line flex items-center justify-between">
                <h3 class="text-sm font-bold text-ink">Jadwal Pengambilan Terkonfirmasi</h3>
                <a href="{{ route('staff.schedules.index', ['status' => 'confirmed']) }}" class="text-xs font-semibold text-link hover:underline">Lihat semua &rarr;</a>
            </div>
            <div class="divide-y divide-line text-xs">
                @forelse ($upcomingPickups as $pickup)
                    <div class="p-4 flex items-center justify-between gap-3">
                        <div>
                            <p class="font-mono font-bold text-ink">{{ $pickup->rental_code }}</p>
                            <p class="font-semibold text-ink">{{ $pickup->car?->name }} ({{ $pickup->car?->plate_number }})</p>
                            <p class="text-muted">Penyewa: {{ $pickup->user?->name }} &middot; Ambil: {{ $pickup->pickup_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</p>
                        </div>
                        <a href="{{ route('staff.schedules.show', $pickup) }}" class="rounded-lg bg-action hover:bg-action-hover px-3 py-1.5 font-bold text-ink shrink-0">
                            Proses
                        </a>
                    </div>
                @empty
                    <p class="p-6 text-center text-muted">Belum ada jadwal pengambilan yang menunggu serah-terima.</p>
                @endforelse
            </div>
        </div>

        <div class="bg-surface rounded-xl border border-line shadow-sm overflow-hidden">
            <div class="p-5 border-b border-line flex items-center justify-between">
                <h3 class="text-sm font-bold text-ink">Kendaraan Sedang Disewa &amp; Jadwal Kembali</h3>
                <a href="{{ route('staff.schedules.index', ['status' => 'picked_up']) }}" class="text-xs font-semibold text-link hover:underline">Lihat semua &rarr;</a>
            </div>
            <div class="divide-y divide-line text-xs">
                @forelse ($activeReturns as $trip)
                    <div class="p-4 flex items-center justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-mono font-bold text-ink">{{ $trip->rental_code }}</span>
                                @if ($trip->isReturnOverdue())
                                    <span class="rounded-full bg-danger-surface px-2 py-0.5 font-bold text-danger">Terlambat</span>
                                @endif
                            </div>
                            <p class="font-semibold text-ink">{{ $trip->car?->name }} ({{ $trip->car?->plate_number }})</p>
                            <p class="text-muted">Penyewa: {{ $trip->user?->name }} &middot; Batas kembali: {{ $trip->return_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</p>
                        </div>
                        <a href="{{ route('staff.schedules.show', $trip) }}" class="rounded-lg border border-line bg-surface-raised hover:bg-surface-hover px-3 py-1.5 font-bold text-ink shrink-0">
                            Pengembalian
                        </a>
                    </div>
                @empty
                    <p class="p-6 text-center text-muted">Tidak ada kendaraan yang sedang disewa saat ini.</p>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Read-Only Fleet Overview + Readiness Summary -->
    <div class="bg-surface rounded-xl border border-line shadow-sm overflow-hidden">
        <div class="p-5 border-b border-line flex items-center justify-between">
            <div>
                <h3 class="text-base font-bold text-ink">Daftar Kesiapan &amp; Status Armada</h3>
                <p class="text-xs text-muted mt-0.5">Gunakan menu Kesiapan Armada untuk melaporkan kerusakan atau menandai unit siap disewakan kembali.</p>
            </div>
            <a href="{{ route('staff.readiness.index') }}" class="text-xs font-semibold text-link hover:underline">Kelola Kesiapan &rarr;</a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-raised text-[11px] font-semibold text-muted uppercase tracking-wider border-b border-line">
                        <th class="py-3 px-5">Nama Kendaraan</th>
                        <th class="py-3 px-5">Kategori</th>
                        <th class="py-3 px-5">Plat Nomor</th>
                        <th class="py-3 px-5">Status Operasional</th>
                        <th class="py-3 px-5">Kesiapan Fisik</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line text-sm">
                    @forelse ($cars as $car)
                        <tr class="hover:bg-surface-raised/80 transition-colors">
                            <td class="py-3 px-5">
                                <span class="font-bold text-ink block">{{ $car->name }}</span>
                                <span class="text-xs text-muted">{{ $car->brand }} &bull; {{ $car->model_year }}</span>
                            </td>
                            <td class="py-3 px-5 text-xs text-muted">{{ $car->carType?->name ?? '-' }}</td>
                            <td class="py-3 px-5 font-mono text-xs font-semibold text-ink">{{ $car->plate_number }}</td>
                            <td class="py-3 px-5 text-xs">
                                @if ($car->status === 'available')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full font-medium bg-success-surface text-success">Tersedia</span>
                                @elseif ($car->status === 'rented')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full font-medium bg-warning-surface text-warning">Sedang Disewa</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full font-medium bg-danger-surface text-danger">Perawatan</span>
                                @endif
                            </td>
                            <td class="py-3 px-5 text-xs font-semibold text-ink">{{ $car->readinessLabel() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-muted">Belum ada data kendaraan terdaftar.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
