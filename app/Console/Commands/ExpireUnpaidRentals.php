<?php

namespace App\Console\Commands;

use App\Services\RentalScheduleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExpireUnpaidRentals extends Command
{
    protected $signature = 'rentals:expire-unpaid';

    protected $description = 'Lepaskan penahanan jadwal pesanan belum dibayar yang melewati tenggat waktu';

    public function handle(RentalScheduleService $scheduleService): int
    {
        // Blok try-catch mencatat kegagalan proses kedaluwarsa ke log aplikasi dan
        // mengembalikan status FAILURE agar pengelola dapat mengetahui gangguan scheduler (NFR-10).
        try {
            $expiredCount = $scheduleService->releaseExpiredHolds();

            $this->info("{$expiredCount} pesanan kedaluwarsa dilepas.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            Log::error('Gagal menjalankan pelepasan pesanan kedaluwarsa.', [
                'message' => $exception->getMessage(),
            ]);
            $this->error('Gagal melepaskan pesanan kedaluwarsa.');

            return self::FAILURE;
        }
    }
}
