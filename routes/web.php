<?php

use App\Http\Controllers\LoyaltyCardController;
use App\Http\Controllers\Public\LoyaltyRegistrationController;
use App\Models\LoyaltyProgram;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

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
    $card = $service->createCard($program, 'Test', 'User');

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
// Formulario público para registro de clientes al programa
// ──────────────────────────────────────────────────────────────
Route::prefix('loyalty')->name('public.loyalty.')->group(function () {
    Route::get('/{slug}/{program}/register', [LoyaltyRegistrationController::class, 'show'])->name('register');
    Route::post('/{slug}/{program}/register', [LoyaltyRegistrationController::class, 'store'])->name('register.submit');
});

require __DIR__ . '/business.php';
require __DIR__ . '/auth.php';
