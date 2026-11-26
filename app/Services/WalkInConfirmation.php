<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class WalkInConfirmation
{
    private function __construct(private string $method, private CarbonImmutable $at) {}

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): self
    {
        $validated = Validator::make($input, [
            'confirmation_method' => ['required', 'in:in_person,phone,message'],
            'confirmed_at' => ['required', 'date'],
        ])->validate();

        // Input datetime-local dari admin dibaca sebagai WIB. Waktu persetujuan
        // adalah saat penyewa memberi jawaban, sedangkan histori mencatat waktu simpan.
        $at = CarbonImmutable::parse($validated['confirmed_at'], RentalScheduleService::TIMEZONE);
        if ($at->isFuture()) {
            throw ValidationException::withMessages(['confirmed_at' => 'Waktu konfirmasi pelanggan belum terjadi.']);
        }

        return new self($validated['confirmation_method'], $at);
    }

    public function occurredAt(): CarbonImmutable
    {
        return $this->at;
    }

    /** @return array{confirmation_method: string, confirmed_at: string} */
    public function metadata(): array
    {
        return ['confirmation_method' => $this->method, 'confirmed_at' => $this->at->toIso8601String()];
    }
}
