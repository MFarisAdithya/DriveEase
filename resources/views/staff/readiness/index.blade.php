@extends('layouts.portal')

@section('title', 'Kesiapan Armada - DriveEase Staf')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-ink">Pemeriksaan &amp; Kesiapan Armada (S-05)</h1>
            <p class="text-xs text-muted mt-0.5">Laporkan kerusakan unit yang ditemukan atau nyatakan unit layak setelah pemeriksaan serta persiapan selesai.</p>
        </div>
        <a href="{{ route('staff.schedules.index') }}" class="rounded-lg border border-line bg-surface px-4 py-2 text-xs font-bold text-ink hover:bg-surface-hover">
            Jadwal Operasional &rarr;
        </a>
    </div>

    @if ($errors->any())
        <ul role="alert" class="rounded-xl border border-danger-line bg-danger-surface p-4 text-xs text-danger space-y-1">
            @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    @endif

    @if ($impactedNextBookings->isNotEmpty())
        <div role="alert" class="rounded-xl border border-danger-line bg-danger-surface p-4 text-xs text-danger space-y-1">
            <strong class="block">Peringatan Jadwal Berikutnya Terdampak</strong>
            @foreach ($impactedNextBookings as $impact)
                <p><strong class="font-mono">{{ $impact['rental']->rental_code }}</strong> ({{ $impact['car']->name }}) &mdash; {{ $impact['reason'] }}</p>
            @endforeach
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4">
        @foreach ($cars as $car)
            <div class="bg-surface rounded-xl border border-line p-5 shadow-sm flex flex-col lg:flex-row lg:items-center justify-between gap-4 text-xs">
                <div class="space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-base font-bold text-ink">{{ $car->name }} ({{ $car->plate_number }})</h2>
                        <span class="rounded-full border border-line bg-surface-raised px-2.5 py-0.5 font-semibold text-ink">{{ $car->readinessLabel() }}</span>
                    </div>
                    <p class="text-muted">{{ $car->carType?->name }} &middot; Status Unit: <strong class="text-ink">{{ ucfirst($car->status) }}</strong></p>
                    @if ($car->maintenance_reason)
                        <p class="text-danger"><strong>Catatan Kerusakan / Perawatan:</strong> {{ $car->maintenance_reason }}</p>
                    @endif
                    @if ($car->readiness_notes)
                        <p class="text-muted">Catatan kesiapan: {{ $car->readiness_notes }} @if($car->readinessChecker) (oleh {{ $car->readinessChecker->name }} pada {{ $car->readiness_checked_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB) @endif</p>
                    @endif
                </div>

                @if ($car->readiness_status !== \App\Models\Car::READINESS_IN_USE)
                    <div class="flex flex-col sm:flex-row gap-3 lg:w-1/2">
                        @if (! $car->isReadyForHandover())
                            <form method="POST" action="{{ route('staff.cars.mark-ready', $car) }}" class="flex-1 flex flex-col sm:flex-row gap-2">
                                @csrf
                                @method('PATCH')
                                <input name="readiness_notes" placeholder="Catatan persiapan unit (opsional)" class="flex-1 rounded-lg border border-field-line bg-surface-raised p-2">
                                <button type="submit" class="rounded-lg bg-action hover:bg-action-hover px-3 py-2 font-bold text-ink shrink-0">
                                    Nyatakan Siap Sewa
                                </button>
                            </form>
                        @endif

                        <form method="POST" action="{{ route('staff.cars.report-damage', $car) }}" class="flex-1 flex flex-col sm:flex-row gap-2">
                            @csrf
                            @method('PATCH')
                            <input name="maintenance_reason" required minlength="5" placeholder="Laporkan kerusakan / kendala unit" class="flex-1 rounded-lg border border-field-line bg-surface-raised p-2">
                            <button type="submit" class="rounded-lg border border-danger-line bg-danger-surface px-3 py-2 font-bold text-danger shrink-0">
                                Laporkan Kerusakan
                            </button>
                        </form>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
@endsection
