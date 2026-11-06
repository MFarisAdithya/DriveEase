<?php

use App\Http\Controllers\Admin\CarController as AdminCarController;
use App\Http\Controllers\Admin\CarTypeController as AdminCarTypeController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PublicCarController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Redirect root to dashboard if logged in, or cars catalog if guest
Route::get('/', function () {
    if (Auth::check()) {
        $user = Auth::user();
        return match ($user->role?->name) {
            'admin' => redirect()->route('admin.dashboard'),
            'staff' => redirect()->route('staff.dashboard'),
            'customer' => redirect()->route('customer.dashboard'),
            default => redirect()->route('login'),
        };
    }
    return redirect()->route('login');
})->name('home');

// Public Dynamic Vehicle Catalog
Route::get('/cars', [PublicCarController::class, 'index'])->name('cars.index');
Route::get('/cars/{car}', [PublicCarController::class, 'show'])->name('cars.show');

// Guest Authentication & Registration Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);

    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

// Authenticated Logout
Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// Admin Routes (Protected by auth and role:admin)
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'admin'])->name('dashboard');
    Route::resource('cars', AdminCarController::class);
    Route::resource('car-types', AdminCarTypeController::class)->except(['show']);
});

// Staff Routes (Protected by auth and role:staff)
Route::middleware(['auth', 'role:staff'])->prefix('staff')->name('staff.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'staff'])->name('dashboard');
});

// Customer Routes (Protected by auth and role:customer)
Route::middleware(['auth', 'role:customer'])->prefix('customer')->name('customer.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'customer'])->name('dashboard');
});
