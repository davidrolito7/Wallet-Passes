<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $business = Auth::guard('business')->user();
        $business->loadCount(['loyaltyPrograms', 'activePrograms']);

        $totalCards = \App\Models\LoyaltyCard::whereHas('loyaltyProgram', fn ($q) => $q->where('business_id', $business->id))
            ->count();

        $completedCards = \App\Models\LoyaltyCard::whereHas('loyaltyProgram', fn ($q) => $q->where('business_id', $business->id))
            ->where('is_completed', true)
            ->count();

        $recentCards = \App\Models\LoyaltyCard::with('loyaltyProgram')
            ->whereHas('loyaltyProgram', fn ($q) => $q->where('business_id', $business->id))
            ->latest()
            ->limit(5)
            ->get();

        return view('business.dashboard', compact('business', 'totalCards', 'completedCards', 'recentCards'));
    }
}
