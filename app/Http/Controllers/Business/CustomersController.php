<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\LoyaltyCard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomersController extends Controller
{
    public function index(Request $request)
    {
        $business = Auth::guard('business')->user();

        $query = LoyaltyCard::with(['loyaltyProgram'])
            ->whereHas('loyaltyProgram', fn ($q) => $q->where('business_id', $business->id));

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        $cards = $query->latest()->paginate(20)->withQueryString();

        return view('business.customers.index', compact('business', 'cards', 'search'));
    }
}
