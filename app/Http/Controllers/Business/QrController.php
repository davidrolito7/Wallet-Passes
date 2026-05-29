<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\LoyaltyProgram;
use Illuminate\Support\Facades\Auth;

class QrController extends Controller
{
    public function index()
    {
        $business = Auth::guard('business')->user();
        $program  = LoyaltyProgram::where('business_id', $business->id)
            ->where('is_active', true)
            ->first();

        $registerUrl = $program
            ? route('public.loyalty.register', ['slug' => $business->slug, 'program' => $program->id])
            : null;

        return view('business.qr.index', compact('business', 'program', 'registerUrl'));
    }
}
