<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AvailableCarResource;
use App\Services\RentalScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class AvailabilityController extends Controller
{
    /**
     * Return JSON list of vehicles available for the requested WIB rental period (SYS-15).
     */
    public function index(Request $request, RentalScheduleService $scheduleService): JsonResponse
    {
        // Validator::make() memvalidasi parameter query GET secara eksplisit agar masukan
        // periode tidak valid atau masa lampau menghasilkan respons HTTP 422 JSON.
        $validator = Validator::make($request->query(), [
            'pickup_at' => ['required', 'date'],
            'total_days' => ['required', 'integer', 'min:1', 'max:'.RentalScheduleService::MAX_DAYS],
            'car_id' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:100'],
            'car_type_id' => ['nullable', 'integer', 'exists:car_types,id'],
            'transmission' => ['nullable', 'in:manual,automatic'],
        ]);

        // after() menambahkan pemeriksaan lanjutan setelah tipe tanggal dan integer lolos,
        // yaitu memastikan waktu pengambilan berada di masa mendatang menurut WIB.
        $validator->after(function ($validator) use ($request, $scheduleService): void {
            if ($validator->errors()->has('pickup_at')) {
                return;
            }

            try {
                $pickupAt = $scheduleService->parsePickupAt((string) $request->query('pickup_at'));
                if ($pickupAt->lessThanOrEqualTo(CarbonImmutable::now(RentalScheduleService::TIMEZONE))) {
                    $validator->errors()->add('pickup_at', 'Waktu pengambilan kendaraan harus berada di masa mendatang (WIB).');
                }
            } catch (Throwable) {
                $validator->errors()->add('pickup_at', 'Format waktu pengambilan tidak valid.');
            }
        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();
        $pickupAt = $scheduleService->parsePickupAt((string) $validated['pickup_at']);
        $totalDays = (int) $validated['total_days'];
        $returnAt = $scheduleService->calculateReturnAt($pickupAt, $totalDays);

        // Lepaskan pesanan belum dibayar yang kedaluwarsa sebelum memfilter ketersediaan,
        // lalu pakai query builder bersama availableCarsQuery() agar hasil API identik dengan web.
        $scheduleService->releaseExpiredHolds($validated['car_id'] ?? null);

        $cars = $scheduleService->availableCarsQuery(
            $pickupAt,
            $returnAt,
            [
                'search' => $validated['search'] ?? null,
                'car_type_id' => $validated['car_type_id'] ?? null,
                'transmission' => $validated['transmission'] ?? null,
            ]
        )
            // Pratinjau satu kendaraan memakai kueri yang sama dan tidak mengirim
            // tarif kendaraan yang gagal melewati filter ketersediaan.
            ->when(isset($validated['car_id']), fn ($query) => $query->whereKey($validated['car_id']))
            ->latest()->get();

        return response()->json([
            'period' => [
                'timezone' => RentalScheduleService::TIMEZONE,
                'pickup_at' => $pickupAt->toIso8601String(),
                'return_at' => $returnAt->toIso8601String(),
                'total_days' => $totalDays,
                'buffer_minutes' => RentalScheduleService::BUFFER_MINUTES,
            ],
            'total' => $cars->count(),
            'data' => AvailableCarResource::collection($cars)->resolve($request),
        ]);
    }
}
