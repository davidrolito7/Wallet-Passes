<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\LoyaltyProgram;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class BusinessProfileController extends Controller
{
    public function show()
    {
        $business = Auth::guard('business')->user();
        $program  = $business->loyaltyPrograms()->first();

        return view('business.profile.index', compact('business', 'program'));
    }

    public function update(Request $request)
    {
        $business = Auth::guard('business')->user();

        $data = $request->validate([
            'name'            => ['required', 'string', 'max:255'],
            'primary_color'   => ['nullable', 'regex:/^#[0-9a-fA-F]{3,6}$/'],
            'secondary_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{3,6}$/'],
            'label_color'     => ['nullable', 'regex:/^#[0-9a-fA-F]{3,6}$/'],
            'contact_phone'   => ['nullable', 'string', 'max:50'],
            'instagram_url'   => ['nullable', 'url', 'max:255'],
            'logo_url'        => ['nullable', 'image', 'mimes:png,webp', 'max:2048'],
            // Imágenes para Wallet (viven en LoyaltyProgram, no en Business)
            'total_stamps'           => ['required', 'integer', 'min:1', 'max:50'],
            'pass_background_image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
            'filled_stamp_image'    => ['nullable', 'image', 'mimes:png,webp', 'max:2048'],
            'empty_stamp_image'     => ['nullable', 'image', 'mimes:png,webp', 'max:2048'],
        ]);

        if ($request->hasFile('logo_url')) {
            if ($business->logo_url && ! str_starts_with($business->logo_url, 'http')) {
                Storage::disk('public')->delete($business->logo_url);
            }
            $data['logo_url'] = $request->file('logo_url')->store('businesses/logos', 'public');
        } else {
            unset($data['logo_url']);
        }

        $business->fill($data)->save();

        // Las imágenes para Wallet pertenecen al programa de lealtad del negocio. Si el
        // negocio todavía no crea su programa (página "Programa de Lealtad"), no hay dónde
        // guardarlas todavía — se ignoran silenciosamente hasta que el programa exista.
        $program = $business->loyaltyPrograms()->first();

        if ($program) {
            $programData = ['total_stamps' => $data['total_stamps']];

            foreach (['pass_background_image', 'filled_stamp_image', 'empty_stamp_image'] as $imageField) {
                if ($request->hasFile($imageField)) {
                    if ($program->$imageField) {
                        Storage::disk('public')->delete($program->$imageField);
                    }
                    $programData[$imageField] = $request->file($imageField)->store('programs/stamps', 'public');
                }
            }

            $program->fill($programData)->save();
        }

        return redirect()->route('business.profile')->with('success', 'Información del negocio actualizada.');
    }
}
