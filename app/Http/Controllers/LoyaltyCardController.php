<?php

namespace App\Http\Controllers;

use App\Models\LoyaltyCard;
use App\Models\LoyaltyProgram;
use App\Services\LoyaltyService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LoyaltyCardController extends Controller
{
    public function __construct(private LoyaltyService $loyalty) {}

    /**
     * Issue a new loyalty card for a program and redirect to Google Wallet.
     * Called when a user scans the QR code at the business.
     */
    public function issue(Request $request, LoyaltyProgram $program)
    {
        $identifier = $request->get('id') ?? Str::uuid()->toString();
        $name = $request->get('name', 'Cliente');
        $email = $request->get('email');

        $existing = LoyaltyCard::where('loyalty_program_id', $program->id)
            ->where('holder_identifier', $identifier)
            ->whereNull('deleted_at')
            ->first();

        $card = $existing ?? $this->loyalty->createCard(
            program: $program,
            holderName: $name,
            holderEmail: $email,
            holderIdentifier: $identifier,
        );

        $platform = $request->get('platform', 'google');

        if ($platform === 'apple') {
            return $this->redirectToApple($card);
        }

        return $this->redirectToGoogle($card);
    }

    /**
     * Redirect to Google Wallet add-to-wallet URL.
     */
    public function addToGoogle(LoyaltyCard $card)
    {
        $pass = $card->googlePass();

        if (! $pass) {
            abort(404, 'Google Wallet pass not found for this card.');
        }

        return redirect()->away($pass->addToWalletUrl());
    }

    /**
     * Download the Apple Wallet .pkpass file.
     */
    public function addToApple(LoyaltyCard $card, Request $request)
    {
        $pass = $card->applePass();

        if (! $pass) {
            abort(503, 'Apple Wallet not yet configured. Check back soon!');
        }

        return $pass->toResponse($request);
    }

    /**
     * QR landing page — shows both wallet options.
     */
    public function landing(LoyaltyCard $card)
    {
        $card->load('loyaltyProgram.business');

        return view('loyalty.landing', compact('card'));
    }

    private function redirectToGoogle(LoyaltyCard $card)
    {
        $pass = $card->googlePass();

        if (! $pass) {
            abort(500, 'Failed to generate Google Wallet pass.');
        }

        return redirect()->away($pass->addToWalletUrl());
    }

    private function redirectToApple(LoyaltyCard $card)
    {
        $pass = $card->applePass();

        if (! $pass) {
            return response('Apple Wallet not yet configured.', 503);
        }

        return redirect()->route('loyalty.apple', $card);
    }
}
