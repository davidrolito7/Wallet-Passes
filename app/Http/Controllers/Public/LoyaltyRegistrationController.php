<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\LoyaltyCard;
use App\Models\LoyaltyProgram;
use App\Services\LoyaltyService;
use Illuminate\Http\Request;

class LoyaltyRegistrationController extends Controller
{
    public function show(string $slug, int $program)
    {
        $business = Business::where('slug', $slug)->where('is_active', true)->firstOrFail();
        $program  = LoyaltyProgram::where('id', $program)
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->firstOrFail();

        return view('public.loyalty-register', compact('business', 'program'));
    }

    public function store(Request $request, string $slug, int $program)
    {
        $business = Business::where('slug', $slug)->where('is_active', true)->firstOrFail();
        $program  = LoyaltyProgram::where('id', $program)
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->firstOrFail();

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['required', 'string', 'max:100'],
            'birth_date' => ['required', 'date', 'before:today'],
        ]);

        $card = app(LoyaltyService::class)->createCard(
            program: $program,
            firstName: $data['first_name'],
            lastName: $data['last_name'],
            birthDate: $data['birth_date'],
        );

        $userAgent = strtolower($request->userAgent() ?? '');
        $isIos     = str_contains($userAgent, 'iphone') || str_contains($userAgent, 'ipad') || str_contains($userAgent, 'ipod');
        $isAndroid = str_contains($userAgent, 'android');

        if ($isIos && $card->apple_pass_id) {
            return redirect()->route('loyalty.apple', $card);
        }

        if ($isAndroid && $card->google_pass_id) {
            return redirect()->route('loyalty.google', $card);
        }

        return redirect()->route('loyalty.landing', $card);
    }
}
