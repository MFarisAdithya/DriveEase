<?php

namespace App\Http\Resources;

use App\Models\Car;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Car
 */
class AvailableCarResource extends JsonResource
{
    /**
     * Transform the vehicle model into a public-safe availability payload (SYS-15).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // max(1, ...) menjaga jumlah hari minimal 1 ketika dihitung dari parameter request.
        $totalDays = max(1, (int) $request->integer('total_days', 1));
        $pricePerDay = round((float) $this->price_per_day, 2);
        $photo = $this->resolvePhoto();

        // Respons JSON hanya memaparkan atribut katalog publik dan estimasi biaya;
        // informasi privat seperti rekening bank, catatan perawatan, atau riwayat tidak disertakan.
        return [
            'id' => $this->id,
            'name' => $this->name,
            'brand' => $this->brand,
            'category' => $this->carType?->name,
            'model_year' => (int) $this->model_year,
            'transmission' => $this->transmission,
            'capacity' => (int) $this->capacity,
            'price_per_day' => $pricePerDay,
            'total_days' => $totalDays,
            'estimated_total' => round($pricePerDay * $totalDays, 2),
            'status' => $this->status,
            'photo_url' => $photo['url'],
            'detail_url' => route('cars.show', [
                'car' => $this->id,
                'pickup_at' => $request->query('pickup_at'),
                'total_days' => $totalDays,
            ]),
        ];
    }
}
