<?php

use App\Http\Controllers\Api\AvailabilityController;
use Illuminate\Support\Facades\Route;

// Endpoint publik untuk mengecek ketersediaan armada dan estimasi tarif berdasarkan
// periode sewa (WIB) menggunakan logika jadwal yang sama dengan katalog web (SYS-15).
Route::get('/v1/availability', [AvailabilityController::class, 'index'])->name('api.v1.availability');
Route::get('/availability', [AvailabilityController::class, 'index'])->name('api.availability');
