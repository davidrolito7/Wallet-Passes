<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyPrizeSystem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoyaltyProgramController extends Controller
{
    public function index()
    {
        $business = Auth::guard('business')->user();
        $program  = LoyaltyProgram::with(['prizeSystems.milestones'])
            ->where('business_id', $business->id)
            ->first();

        return view('business.loyalty-program.index', compact('business', 'program'));
    }

    public function store(Request $request)
    {
        $business = Auth::guard('business')->user();

        // El tope de "Visita #" de un hito depende del total de sellos configurado en Mi
        // Negocio (versión 1 o 2 de la tarjeta) — un hito no puede caer en la última visita,
        // esa es la del premio final.
        $program       = LoyaltyProgram::where('business_id', $business->id)->first();
        $maxStampCount = max((($program?->total_stamps) ?? 10) - 1, 1);

        $data = $request->validate([
            'name'                 => ['required', 'string', 'max:255'],
            'description'          => ['nullable', 'string'],
            'validity_months'      => ['nullable', 'integer', 'min:1', 'max:60'],
            'is_active'            => ['boolean'],
            // Prize systems
            'prize_systems'                               => ['nullable', 'array'],
            'prize_systems.*.id'                          => ['nullable', 'integer'],
            'prize_systems.*.reward_title'                => ['required_with:prize_systems', 'string', 'max:255'],
            'prize_systems.*.reward_description'          => ['nullable', 'string'],
            'prize_systems.*.milestones'                  => ['nullable', 'array'],
            'prize_systems.*.milestones.*.stamp_count'    => ['required_with:prize_systems.*.milestones', 'integer', 'min:1', 'max:' . $maxStampCount],
            'prize_systems.*.milestones.*.reward_title'   => ['required_with:prize_systems.*.milestones', 'string', 'max:255'],
            'prize_systems.*.milestones.*.reward_description' => ['nullable', 'string'],
            'prize_systems.*.milestones.*.is_repeatable'  => ['boolean'],
            // Birthday
            'birthday_reward_enabled'     => ['boolean'],
            'birthday_reward_title'       => ['nullable', 'string', 'max:255'],
            'birthday_reward_description' => ['nullable', 'string'],
            // Notifications
            'visit_notification_title'        => ['nullable', 'string', 'max:100'],
            'visit_notification_message'      => ['nullable', 'string', 'max:500'],
            'google_wallet_notification_mode' => ['nullable', 'in:balance_update_only,custom_message_only,both'],
        ]);

        if (! $program) {
            $program = new LoyaltyProgram(['business_id' => $business->id]);
        }

        $data['business_id'] = $business->id;
        $data['is_active']   = $request->boolean('is_active', true);

        // No hay un toggle explícito en el formulario: el mensaje personalizado se activa
        // simplemente con que el campo tenga contenido.
        $data['visit_notification_enabled'] = filled($data['visit_notification_message'] ?? null);
        $data['birthday_reward_enabled']    = $request->boolean('birthday_reward_enabled');

        if (! $data['visit_notification_enabled']) {
            $data['google_wallet_notification_mode'] = 'balance_update_only';
        } elseif (empty($data['google_wallet_notification_mode'])) {
            $data['google_wallet_notification_mode'] = 'custom_message_only';
        }

        // Sync reward_title on the program from the first prize system for backwards compat
        $submittedSystems = $request->input('prize_systems', []);
        $firstSystem = reset($submittedSystems);
        if ($firstSystem) {
            $data['reward_title']       = $firstSystem['reward_title'];
            $data['reward_description'] = $firstSystem['reward_description'] ?? null;
        }

        unset($data['prize_systems']);
        $program->fill($data)->save();

        // Upsert prize systems
        $savedIds = [];
        foreach ($submittedSystems as $si => $systemData) {
            $systemId = ! empty($systemData['id']) ? (int) $systemData['id'] : null;

            $prizeSystem = $systemId
                ? $program->prizeSystems()->find($systemId)
                : null;

            if (! $prizeSystem) {
                $prizeSystem = new LoyaltyPrizeSystem(['loyalty_program_id' => $program->id]);
            }

            $prizeSystem->fill([
                'loyalty_program_id' => $program->id,
                'reward_title'       => $systemData['reward_title'],
                'reward_description' => $systemData['reward_description'] ?? null,
                'sort_order'         => $si + 1,
            ])->save();

            $savedIds[] = $prizeSystem->id;

            // Replace milestones for this system
            $prizeSystem->milestones()->delete();
            foreach ($systemData['milestones'] ?? [] as $milestone) {
                if (empty($milestone['stamp_count']) || empty($milestone['reward_title'])) {
                    continue;
                }
                $prizeSystem->milestones()->create([
                    'stamp_count'        => $milestone['stamp_count'],
                    'reward_title'       => $milestone['reward_title'],
                    'reward_description' => $milestone['reward_description'] ?? null,
                    'is_repeatable'      => isset($milestone['is_repeatable']),
                ]);
            }
        }

        // Remove prize systems that were deleted in the form
        if (! empty($savedIds)) {
            $program->prizeSystems()->whereNotIn('id', $savedIds)->delete();
        }

        return redirect()->route('business.loyalty-program')->with('success', 'Programa guardado correctamente.');
    }
}
