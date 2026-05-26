<?php

use App\Http\Controllers\LoyaltyCardController;
use App\Http\Controllers\ProfileController;
use App\Models\LoyaltyProgram;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// ──────────────────────────────────────────────────────────────
// Apple & Google Wallet webservice routes (Spatie package)
// Apple Wallet usa estas rutas para registrar dispositivos y
// servir el .pkpass actualizado cuando hay cambios.
// ──────────────────────────────────────────────────────────────
Route::mobilePass();

// ──────────────────────────────────────────────────────────────
// Loyalty card public routes (no auth required — scannable QR)
// ──────────────────────────────────────────────────────────────
Route::prefix('loyalty')->name('loyalty.')->group(function () {
    // Issue/retrieve a card for a program and redirect to wallet
    Route::get('/{program}/join', [LoyaltyCardController::class, 'issue'])->name('issue');

    // Landing page showing stamp progress + wallet buttons
    Route::get('/card/{card}', [LoyaltyCardController::class, 'landing'])->name('landing');

    // Wallet redirects
    Route::get('/card/{card}/google', [LoyaltyCardController::class, 'addToGoogle'])->name('google');
    Route::get('/card/{card}/apple', [LoyaltyCardController::class, 'addToApple'])->name('apple');
});

// ──────────────────────────────────────────────────────────────
// Legacy test routes (kept for manual testing)
// ──────────────────────────────────────────────────────────────
Route::get('/google/test-loyalty-pass', function () {
    $program = LoyaltyProgram::with('business')->first();

    if (! $program) {
        return response()->json(['error' => 'No loyalty programs found. Create one in /admin first.'], 404);
    }

    $service = app(\App\Services\LoyaltyService::class);
    $card = $service->createCard($program, 'Test User', 'test@example.com');

    $pass = $card->googlePass();

    return response()->json([
        'success'        => true,
        'card_id'        => $card->id,
        'progress'       => $card->progressText(),
        'stamps_visual'  => $card->stampVisual(),
        'google_pass_id' => $card->google_pass_id,
        'add_to_wallet'  => $pass?->addToWalletUrl(),
        'landing_url'    => route('loyalty.landing', $card),
    ]);
});

// ──────────────────────────────────────────────────────────────
// Auth routes (Breeze)
// ──────────────────────────────────────────────────────────────
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
