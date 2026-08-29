<?php

use App\Http\Controllers\Business\AuthController;
use App\Http\Controllers\Business\BusinessProfileController;
use App\Http\Controllers\Business\ScannerController;
use App\Http\Controllers\Business\CustomersController;
use App\Http\Controllers\Business\DashboardController;
use App\Http\Controllers\Business\LoyaltyProgramController;
use App\Http\Controllers\Business\QrController;
use App\Http\Middleware\BusinessAuthenticated;
use Illuminate\Support\Facades\Route;

Route::prefix('business')->name('business.')->group(function () {
    // Autenticación (sin middleware de negocio)
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Panel protegido
    Route::middleware(BusinessAuthenticated::class)->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/profile', [BusinessProfileController::class, 'show'])->name('profile');
        Route::post('/profile', [BusinessProfileController::class, 'update'])->name('profile.save');

        Route::get('/loyalty-program', [LoyaltyProgramController::class, 'index'])->name('loyalty-program');
        Route::post('/loyalty-program', [LoyaltyProgramController::class, 'store'])->name('loyalty-program.save');

        Route::get('/scanner', [ScannerController::class, 'index'])->name('scanner');
        Route::post('/scanner/scan', [ScannerController::class, 'scan'])->name('scanner.scan');

        Route::get('/customers', [CustomersController::class, 'index'])->name('customers');
        Route::post('/customers/message', [CustomersController::class, 'sendMessage'])->name('customers.message');

        Route::get('/qr', [QrController::class, 'index'])->name('qr');
    });
});
