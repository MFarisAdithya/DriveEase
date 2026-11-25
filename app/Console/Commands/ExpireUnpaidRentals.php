<?php

namespace App\Console\Commands;

use App\Services\RentalScheduleService;
use Illuminate\Console\Command;

class ExpireUnpaidRentals extends Command
{
    protected $signature = 'rentals:expire-unpaid';

    protected $description = 'Lepaskan penahanan jadwal pesanan belum dibayar yang melewati tenggat waktu';

    public function handle(RentalScheduleService $scheduleService): int
    {
        // releaseExpiredHolds() memeriksa pesanan pending tanpa bukti bayar yang melewati
        // batas waktu, lalu menandainya sebagai expired tanpa menghapus baris transaksi.
        $expiredCount = $scheduleService->releaseExpiredHolds();

        $this->info("{$expiredCount} pesanan kedaluwarsa dilepas.");

        return self::SUCCESS;
    }
}
