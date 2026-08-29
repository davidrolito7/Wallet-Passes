<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\LoyaltyCard;
use App\Services\LoyaltyService;
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

    public function sendMessage(Request $request)
    {
        $business = Auth::guard('business')->user();

        $data = $request->validate([
            'message'    => ['required', 'string', 'max:150'],
            'target'     => ['required', 'in:all,selected'],
            'card_ids'   => ['required_if:target,selected', 'array'],
            'card_ids.*' => ['integer'],
        ]);

        $query = LoyaltyCard::whereHas('loyaltyProgram', fn ($q) => $q->where('business_id', $business->id));

        if ($data['target'] === 'selected') {
            $query->whereIn('id', $data['card_ids'] ?? []);
        }

        $cards = $query->get();

        $loyalty = app(LoyaltyService::class);
        $sent    = 0;
        $skipped = 0;

        foreach ($cards as $card) {
            if (! $card->google_pass_id && ! $card->apple_pass_id) {
                $skipped++;
                continue;
            }

            $loyalty->sendMessage($card, $data['message']);
            $sent++;
        }

        $summary = 'Mensaje enviado a ' . $sent . ' ' . ($sent === 1 ? 'cliente' : 'clientes') . '.';
        if ($skipped > 0) {
            $summary .= ' ' . $skipped . ' ' . ($skipped === 1 ? 'no tiene' : 'no tienen') . ' tarjeta digital activa y se omitió.';
        }

        return redirect()->route('business.customers', $request->only('search'))->with('success', $summary);
    }
}
