<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\LoyaltyMilestone;
use App\Models\LoyaltyProgram;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class LoyaltyProgramController extends Controller
{
    public function index()
    {
        $business = Auth::guard('business')->user();
        $program  = LoyaltyProgram::with('milestones')
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
            'reward_title'         => ['required', 'string', 'max:255'],
            'reward_description'   => ['nullable', 'string'],
            'is_active'            => ['boolean'],
            'pass_background_image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
            'filled_stamp_image'   => ['nullable', 'image', 'mimes:png,webp', 'max:2048'],
            'empty_stamp_image'    => ['nullable', 'image', 'mimes:png,webp', 'max:2048'],
            'reward_badge_image'   => ['nullable', 'image', 'mimes:png,webp', 'max:2048'],
            'milestones'           => ['nullable', 'array'],
            'milestones.*.stamp_count'        => ['required_with:milestones', 'integer', 'min:1'],
            'milestones.*.reward_title'       => ['required_with:milestones', 'string', 'max:255'],
            'milestones.*.reward_description' => ['nullable', 'string'],
            'milestones.*.is_repeatable'      => ['boolean'],
        ]);

        $program = LoyaltyProgram::firstOrNew(['business_id' => $business->id]);

        foreach (['pass_background_image', 'filled_stamp_image', 'empty_stamp_image', 'reward_badge_image'] as $imageField) {
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

        $program->fill($data)->save();

        if (isset($data['milestones'])) {
            $program->milestones()->delete();
            foreach ($data['milestones'] as $milestone) {
                $program->milestones()->create([
                    'stamp_count'        => $milestone['stamp_count'],
                    'reward_title'       => $milestone['reward_title'],
                    'reward_description' => $milestone['reward_description'] ?? null,
                    'is_repeatable'      => isset($milestone['is_repeatable']) ? (bool) $milestone['is_repeatable'] : false,
                ]);
            }
        }

        return redirect()->route('business.loyalty-program')->with('success', 'Programa guardado correctamente.');
    }
}
