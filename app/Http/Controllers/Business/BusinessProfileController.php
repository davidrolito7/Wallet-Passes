<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class BusinessProfileController extends Controller
{
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

        return redirect()->route('business.loyalty-program')->with('success', 'Información del negocio actualizada.');
    }
}
