<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\LoyaltyCard;
use App\Services\LoyaltyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScannerController extends Controller
{
    public function index()
    {
        return view('business.scanner.index');
    }

    public function scan(Request $request)
    {
        $qr = $request->validate(['qr' => ['required', 'string']])['qr'];

        if (! preg_match('/^loyalty:(\d+):([a-f0-9]{32})$/', $qr, $matches)) {
            return response()->json(['success' => false, 'error' => 'QR no reconocido. Asegúrate de escanear la tarjeta de lealtad del cliente.']);
        }

        $card = LoyaltyCard::with('loyaltyProgram.business', 'loyaltyProgram.prizeSystems.milestones')->find((int) $matches[1]);

        if (! $card || md5($card->id . $card->created_at) !== $matches[2]) {
            return response()->json(['success' => false, 'error' => 'QR no válido o expirado.']);
        }

        $business = Auth::guard('business')->user();
        if ($card->loyaltyProgram->business_id !== $business->id) {
            return response()->json(['success' => false, 'error' => 'Esta tarjeta no pertenece a tu negocio.']);
        }

        $program = $card->loyaltyProgram;

        // Detectar cumpleaños antes de agregar el sello
        $isBirthday = $card->birth_date
            && $card->birth_date->format('m-d') === now()->format('m-d');

        $result     = app(LoyaltyService::class)->addStamp($card, 1, recordedBy: 'scanner');
        $card       = $result['card'];
        $milestones = $result['milestones'];

        return response()->json([
            'success'      => true,
            'name'         => $card->fullName(),
            'stamps'       => $card->stamps_collected,
            'total'        => $program->total_stamps,
            'is_completed' => $card->is_completed,
            'birthday'     => ($isBirthday && $program->birthday_reward_enabled) ? [
                'title'       => $program->birthday_reward_title,
                'description' => $program->birthday_reward_description,
            ] : null,
            'milestone'    => $milestones->isNotEmpty() ? [
                'title' => $milestones->first()->reward_title,
            ] : null,
            'main_reward'  => $card->is_completed
                ? ($card->resolvedPrizeSystem()?->reward_title ?? $program->reward_title)
                : null,
        ]);
    }
}
