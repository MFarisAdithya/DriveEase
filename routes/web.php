<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\BookingChangeController as AdminBookingChangeController;
use App\Http\Controllers\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Admin\CarController as AdminCarController;
use App\Http\Controllers\Admin\CarTypeController as AdminCarTypeController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\RentalSettingsController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\SettlementController as AdminSettlementController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\VerificationController as AdminVerificationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Customer\AdditionalChargeController as CustomerAdditionalChargeController;
use App\Http\Controllers\Customer\BookingChangeController as CustomerBookingChangeController;
use App\Http\Controllers\Customer\BookingController as CustomerBookingController;
use App\Http\Controllers\Customer\PaymentController as CustomerPaymentController;
use App\Http\Controllers\Customer\VerificationController as CustomerVerificationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PublicCarController;
use App\Http\Controllers\Staff\OperationController as StaffOperationController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Redirect root to dashboard if logged in, or landing page if guest
Route::get('/', function () {
    if (Auth::check()) {
        $user = Auth::user();

        return match ($user->role?->name) {
            'admin' => redirect()->route('admin.dashboard'),
            'staff' => redirect()->route('staff.dashboard'),
            'customer' => redirect()->route('customer.dashboard'),
            default => app(PublicCarController::class)->landing(),
        };
    }

    return app(PublicCarController::class)->landing();
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

    Route::get('/forgot-password', [PasswordResetController::class, 'requestLink'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:5,1')->name('password.update');
});

// Semua peran menggunakan formulir akun yang sama. Middleware auth memastikan
// controller mengambil profil dari pengguna yang sudah masuk melalui sesi.
Route::middleware('auth')->prefix('account')->name('account.')->group(function () {
    Route::get('/', [AccountController::class, 'edit'])->name('edit');
    Route::patch('/profile', [AccountController::class, 'updateProfile'])->name('profile');
    Route::patch('/password', [AccountController::class, 'updatePassword'])->name('password');
});

// Authenticated Logout
Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// Secure Private Document Streaming
Route::get('/documents/identity/{user}/{type}', [DocumentController::class, 'showIdentity'])
    ->middleware('auth')
    ->name('documents.identity.show');
Route::get('/documents/payments/{payment}', [DocumentController::class, 'showPaymentProof'])
    ->middleware('auth')
    ->name('documents.payment.show');
Route::get('/documents/extensions/{extension}', [DocumentController::class, 'showExtensionPaymentProof'])
    ->middleware('auth')
    ->name('documents.extension-payment.show');
Route::get('/documents/inspections/{inspection}', [DocumentController::class, 'showInspectionPhoto'])
    ->middleware('auth')
    ->name('documents.inspection-photo.show');
Route::get('/documents/rentals/{rental}/damage-evidence', [DocumentController::class, 'showDamageEvidence'])
    ->middleware('auth')
    ->name('documents.damage-evidence.show');
Route::get('/documents/rentals/{rental}/additional-charge-proof', [DocumentController::class, 'showAdditionalChargeProof'])
    ->middleware('auth')
    ->name('documents.additional-charge-proof.show');

// Admin Routes (Protected by auth and role:admin)
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'admin'])->name('dashboard');
    Route::get('/rental-settings', [RentalSettingsController::class, 'edit'])->name('rental-settings.edit');
    Route::patch('/rental-settings', [RentalSettingsController::class, 'update'])->name('rental-settings.update');
    Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
    Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
    Route::get('/staff/{member}/edit', [StaffController::class, 'edit'])->name('staff.edit');
    Route::patch('/staff/{member}', [StaffController::class, 'update'])->name('staff.update');
    Route::patch('/staff/{member}/deactivate', [StaffController::class, 'deactivate'])->name('staff.deactivate');

    Route::resource('cars', AdminCarController::class);
    Route::patch('/cars/{car}/report-damage', [AdminCarController::class, 'reportDamage'])->name('cars.report-damage');
    Route::patch('/cars/{car}/mark-ready', [AdminCarController::class, 'markReady'])->name('cars.mark-ready');
    Route::resource('car-types', AdminCarTypeController::class)->except(['show']);

    // Customer Identity Verification Management
    Route::get('/verifications', [AdminVerificationController::class, 'index'])->name('verifications.index');
    Route::get('/verifications/{user}', [AdminVerificationController::class, 'show'])->name('verifications.show');
    Route::patch('/verifications/{user}/approve', [AdminVerificationController::class, 'approve'])->name('verifications.approve');
    Route::patch('/verifications/{user}/walk-in-documents', [AdminVerificationController::class, 'correctWalkIn'])->name('verifications.correct-walk-in');
    Route::patch('/verifications/{user}/reject', [AdminVerificationController::class, 'reject'])->name('verifications.reject');

    // Admin Online & Walk-In Booking Management (A-06, A-15)
    Route::get('/bookings', [AdminBookingController::class, 'index'])->name('bookings.index');
    Route::get('/bookings/create', [AdminBookingController::class, 'create'])->name('bookings.create');
    Route::post('/bookings', [AdminBookingController::class, 'store'])->name('bookings.store');
    Route::post('/bookings/register-customer', [AdminBookingController::class, 'registerWalkInCustomer'])->name('bookings.register-customer');
    Route::get('/bookings/{rental}', [AdminBookingController::class, 'show'])->name('bookings.show');
    Route::patch('/bookings/{rental}/cancel', [AdminBookingController::class, 'cancel'])->name('bookings.cancel');
    Route::patch('/bookings/{rental}/cancel-with-refund', [AdminBookingController::class, 'cancelWithRefund'])->name('bookings.cancel-with-refund');
    Route::post('/bookings/{rental}/review-contact', [AdminBookingController::class, 'recordReviewContact'])->name('bookings.review-contact');
    Route::patch('/bookings/{rental}/refund', [AdminBookingController::class, 'realizeRefund'])->name('bookings.refund');
    Route::post('/bookings/{rental}/payments', [AdminPaymentController::class, 'storeWalkInProof'])->name('bookings.payments.store');
    Route::patch('/payments/{payment}/approve', [AdminPaymentController::class, 'approve'])->name('payments.approve');
    Route::patch('/payments/{payment}/reject', [AdminPaymentController::class, 'reject'])->name('payments.reject');

    // Admin Booking Change Proposals, Customer Reschedule Reviews & Extensions (A-06, A-08, SYS-09)
    Route::post('/bookings/{rental}/change-requests', [AdminBookingChangeController::class, 'proposeChange'])->name('bookings.change-requests.propose');
    Route::patch('/change-requests/{changeRequest}/respond', [AdminBookingChangeController::class, 'respondToRequest'])->name('change-requests.respond');
    Route::patch('/extensions/{extension}/approve', [AdminBookingChangeController::class, 'approveExtension'])->name('extensions.approve');
    Route::patch('/extensions/{extension}/reject', [AdminBookingChangeController::class, 'rejectExtension'])->name('extensions.reject');
    Route::patch('/extensions/{extension}/payment/approve', [AdminBookingChangeController::class, 'approveExtensionPayment'])->name('extensions.payment.approve');
    Route::patch('/extensions/{extension}/payment/reject', [AdminBookingChangeController::class, 'rejectExtensionPayment'])->name('extensions.payment.reject');
    Route::patch('/extensions/{extension}/refund', [AdminBookingChangeController::class, 'realizeExtensionRefund'])->name('extensions.refund');

    // Admin Additional Charges & Transaction Completion (A-09, SYS-11, SYS-12)
    Route::post('/bookings/{rental}/additional-charges', [AdminSettlementController::class, 'assessCharges'])->name('bookings.additional-charges.assess');
    Route::post('/bookings/{rental}/additional-charges/proof', [AdminSettlementController::class, 'storeChargeProof'])->name('bookings.additional-charges.proof');
    Route::patch('/bookings/{rental}/additional-charges/approve', [AdminSettlementController::class, 'approveChargePayment'])->name('bookings.additional-charges.approve');
    Route::patch('/bookings/{rental}/additional-charges/reject', [AdminSettlementController::class, 'rejectChargePayment'])->name('bookings.additional-charges.reject');
    Route::patch('/bookings/{rental}/complete', [AdminSettlementController::class, 'complete'])->name('bookings.complete');
    Route::get('/reports', [AdminReportController::class, 'index'])->name('reports.index');
});

// Staff Routes (Protected by auth and role:staff)
Route::middleware(['auth', 'role:staff'])->prefix('staff')->name('staff.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'staff'])->name('dashboard');
    Route::get('/schedules', [StaffOperationController::class, 'index'])->name('schedules.index');
    Route::get('/schedules/{rental}', [StaffOperationController::class, 'show'])->name('schedules.show');
    Route::post('/schedules/{rental}/pickup', [StaffOperationController::class, 'storePickup'])->name('schedules.pickup');
    Route::post('/schedules/{rental}/return', [StaffOperationController::class, 'storeReturn'])->name('schedules.return');
    Route::get('/readiness', [StaffOperationController::class, 'readinessIndex'])->name('readiness.index');
    Route::patch('/cars/{car}/report-damage', [StaffOperationController::class, 'reportDamage'])->name('cars.report-damage');
    Route::patch('/cars/{car}/mark-ready', [StaffOperationController::class, 'markReady'])->name('cars.mark-ready');
});

// Customer Routes (Protected by auth and role:customer)
Route::middleware(['auth', 'role:customer'])->prefix('customer')->name('customer.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'customer'])->name('dashboard');
    Route::get('/verification', [CustomerVerificationController::class, 'create'])->name('verification.create');
    Route::post('/verification', [CustomerVerificationController::class, 'store'])->name('verification.store');

    // Customer Online Vehicle Booking (C-05, C-07, C-08, C-09, C-10)
    Route::get('/bookings', [CustomerBookingController::class, 'index'])->name('bookings.index');
    Route::get('/bookings/create', [CustomerBookingController::class, 'create'])->name('bookings.create');
    Route::post('/bookings', [CustomerBookingController::class, 'store'])->name('bookings.store');
    Route::get('/bookings/{rental}', [CustomerBookingController::class, 'show'])->name('bookings.show');
    Route::patch('/bookings/{rental}/cancel', [CustomerBookingController::class, 'cancel'])->name('bookings.cancel');
    Route::post('/bookings/{rental}/request-cancellation', [CustomerBookingController::class, 'requestCancellation'])->name('bookings.request-cancellation');
    Route::post('/bookings/{rental}/payments', [CustomerPaymentController::class, 'store'])->name('bookings.payments.store');
    Route::post('/bookings/{rental}/change-requests', [CustomerBookingChangeController::class, 'storeChangeRequest'])->name('bookings.change-requests.store');
    Route::patch('/change-requests/{changeRequest}/respond', [CustomerBookingChangeController::class, 'respondToProposal'])->name('change-requests.respond');
    Route::post('/bookings/{rental}/extensions', [CustomerBookingChangeController::class, 'requestExtension'])->name('bookings.extensions.store');
    Route::post('/extensions/{extension}/payment', [CustomerBookingChangeController::class, 'submitExtensionPayment'])->name('extensions.payment.store');
    Route::post('/bookings/{rental}/additional-charges/proof', [CustomerAdditionalChargeController::class, 'storeProof'])->name('bookings.additional-charges.proof');
});
