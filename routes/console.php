<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('identities:prune-temporary')->daily()->withoutOverlapping();
Schedule::command('driveease:backup')
    ->dailyAt('02:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->onFailure(function (): void {
        Log::error('Jadwal pencadangan harian DriveEase (driveease:backup) gagal dijalankan.');
    });
Schedule::command('rentals:expire-unpaid')
    ->everyMinute()
    ->withoutOverlapping()
    ->onFailure(function (): void {
        Log::error('Jadwal pelepasan pesanan kedaluwarsa (rentals:expire-unpaid) gagal dijalankan.');
    });
