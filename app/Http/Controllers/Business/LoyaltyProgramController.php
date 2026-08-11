<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyPrizeSystem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

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

        $data = $request->validate([
            'name'                 => ['required', 'string', 'max:255'],
            'description'          => ['nullable', 'string'],
            'total_stamps'         => ['required', 'integer', 'min:1', 'max:50'],
            'is_active'            => ['boolean'],
            // Images
            'pass_background_image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
            'filled_stamp_image'   => ['nullable', 'image', 'mimes:png,webp', 'max:2048'],
            'empty_stamp_image'    => ['nullable', 'image', 'mimes:png,webp', 'max:2048'],
            // Prize systems
            'prize_systems'                               => ['nullable', 'array'],
            'prize_systems.*.id'                          => ['nullable', 'integer'],
            'prize_systems.*.reward_title'                => ['required_with:prize_systems', 'string', 'max:255'],
            'prize_systems.*.reward_description'          => ['nullable', 'string'],
            'prize_systems.*.milestones'                  => ['nullable', 'array'],
            'prize_systems.*.milestones.*.stamp_count'    => ['required_with:prize_systems.*.milestones', 'integer', 'min:1'],
            'prize_systems.*.milestones.*.reward_title'   => ['required_with:prize_systems.*.milestones', 'string', 'max:255'],
            'prize_systems.*.milestones.*.reward_description' => ['nullable', 'string'],
            'prize_systems.*.milestones.*.is_repeatable'  => ['boolean'],
            // Birthday
            'birthday_reward_enabled'     => ['boolean'],
            'birthday_reward_title'       => ['nullable', 'string', 'max:255'],
            'birthday_reward_description' => ['nullable', 'string'],
            // Notifications
            'visit_notification_enabled'      => ['boolean'],
            'visit_notification_title'        => ['nullable', 'string', 'max:100'],
            'visit_notification_message'      => ['nullable', 'string', 'max:500'],
            'google_wallet_notification_mode' => ['nullable', 'in:balance_update_only,custom_message_only,both'],
            // Google Wallet
            'google_class_suffix' => ['nullable', 'string', 'max:255'],
        ]);

        $program = LoyaltyProgram::firstOrNew(['business_id' => $business->id]);

        // Handle image uploads
        foreach (['pass_background_image', 'filled_stamp_image', 'empty_stamp_image'] as $imageField) {
            if ($request->hasFile($imageField)) {
                if ($program->$imageField) {
                    Storage::disk('public')->delete($program->$imageField);
                }
                $data[$imageField] = $request->file($imageField)->store('programs/stamps', 'public');
            } else {
                unset($data[$imageField]);
            }
        }

        $data['business_id'] = $business->id;
        $data['is_active']   = $request->boolean('is_active', true);

        $data['visit_notification_enabled'] = $request->boolean('visit_notification_enabled');
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
