<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\LoyaltyCard;
use App\Services\LoyaltyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomersController extends Controller
{
    /**
     * Períodos de inactividad disponibles para el filtro (clave => meses/semanas hacia atrás).
     */
    public const INACTIVE_PERIODS = [
        '2w'  => '2 semanas',
        '3w'  => '3 semanas',
        '2m'  => '2 meses',
        '4m'  => '4 meses',
        '6m'  => '6 meses',
        '12m' => '12 meses',
    ];

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

        $inactive = $request->input('inactive');

        if ($inactive && array_key_exists($inactive, self::INACTIVE_PERIODS)) {
            $cutoff = $this->inactiveCutoff($inactive);

            $query->where(function ($q) use ($cutoff) {
                $q->whereNull('last_stamp_at')->orWhere('last_stamp_at', '<=', $cutoff);
            });
        }

        $cards = $query->latest()->paginate(20)->withQueryString();

        return view('business.customers.index', compact('business', 'cards', 'search', 'inactive'));
    }

    private function inactiveCutoff(string $period): \Illuminate\Support\Carbon
    {
        return match ($period) {
            '2w'  => now()->subWeeks(2),
            '3w'  => now()->subWeeks(3),
            '2m'  => now()->subMonths(2),
            '4m'  => now()->subMonths(4),
            '6m'  => now()->subMonths(6),
            '12m' => now()->subMonths(12),
        };
    }

    public function sendMessage(Request $request)
    {
        $business = Auth::guard('business')->user();

        $data = $request->validate([
            'message'    => ['required', 'string', 'max:150'],
            'card_ids'   => ['required', 'array', 'min:1'],
            'card_ids.*' => ['integer'],
        ]);

        $cards = LoyaltyCard::whereHas('loyaltyProgram', fn ($q) => $q->where('business_id', $business->id))
            ->whereIn('id', $data['card_ids'])
            ->get();

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

        return redirect()->route('business.customers', $request->only(['search', 'inactive']))->with('success', $summary);
    }

    public function addVisit(Request $request, LoyaltyCard $card)
    {
        $this->authorizeCard($card);

        $data = $request->validate([
            'count' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        app(LoyaltyService::class)->addStamp($card, $data['count'], recordedBy: 'manual');

        return back()->with('success', 'Visita registrada para ' . $card->fullName() . '.');
    }

    public function destroy(LoyaltyCard $card)
    {
        $this->authorizeCard($card);

        $name = $card->fullName();
        $card->delete();

        return back()->with('success', 'Cliente ' . $name . ' eliminado.');
    }

    private function authorizeCard(LoyaltyCard $card): void
    {
        $business = Auth::guard('business')->user();

        abort_unless($card->loyaltyProgram->business_id === $business->id, 403);
    }
}
