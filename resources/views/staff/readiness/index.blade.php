@extends('layouts.portal')

@section('title', 'Kesiapan Armada - DriveEase Staf')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-ink">Pemeriksaan &amp; Kesiapan Armada</h1>
            <p class="text-xs text-muted mt-0.5">Laporkan kerusakan unit yang ditemukan atau nyatakan unit layak setelah pemeriksaan serta persiapan selesai.</p>
        </div>
        <a href="{{ route('staff.schedules.index') }}" class="ml-auto rounded-lg border border-line bg-surface px-4 py-2 text-xs font-bold text-ink hover:bg-surface-hover">
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
                    <p class="text-muted">{{ $car->carType?->name }} &middot; Status Unit: <strong class="text-ink">{{ $car->statusLabel() }}</strong></p>
                    @if ($car->maintenance_reason)
                        <p class="text-danger"><strong>Catatan Kerusakan / Perawatan:</strong> {{ $car->maintenance_reason }}</p>
                    @endif
                    @if ($car->readiness_notes)
                        <p class="text-muted">Catatan kesiapan: {{ $car->readiness_notes }} @if($car->readinessChecker) (oleh {{ $car->readinessChecker->name }} pada {{ $car->readiness_checked_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB) @endif</p>
                    @endif
                </div>

                @if ($car->readiness_status !== \App\Models\Car::READINESS_IN_USE)
                    <div class="grid min-w-0 flex-1 gap-3 lg:max-w-lg">
                        @if (! $car->isReadyForHandover())
                            <form method="POST" action="{{ route('staff.cars.mark-ready', $car) }}" class="flex flex-wrap items-end justify-end gap-2">
                                @csrf
                                @method('PATCH')
                                <label for="readiness_notes_{{ $car->id }}" class="sr-only">Catatan persiapan {{ $car->name }}</label>
                                <input id="readiness_notes_{{ $car->id }}" name="readiness_notes" placeholder="Catatan persiapan unit (opsional)" class="min-w-0 flex-1 basis-60 rounded-lg border border-field-line bg-surface-raised p-2">
                                <button type="submit" class="min-h-10 shrink-0 rounded-lg bg-action px-4 py-2 text-sm font-bold text-ink hover:bg-action-hover">
                                    Nyatakan Siap Sewa
                                </button>
                            </form>
                        @endif

                        <form method="POST" action="{{ route('staff.cars.report-damage', $car) }}" class="flex flex-wrap items-end justify-end gap-2">
                            @csrf
                            @method('PATCH')
                            <label for="maintenance_reason_{{ $car->id }}" class="sr-only">Alasan kerusakan {{ $car->name }}</label>
                            <input id="maintenance_reason_{{ $car->id }}" name="maintenance_reason" required minlength="5" placeholder="Laporkan kerusakan / kendala unit" class="min-w-0 flex-1 basis-60 rounded-lg border border-field-line bg-surface-raised p-2">
                            <button type="submit" class="min-h-10 shrink-0 rounded-lg border border-danger-line bg-danger-surface px-4 py-2 text-sm font-bold text-danger">
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
