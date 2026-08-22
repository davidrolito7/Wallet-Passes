<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\LoyaltyProgram;
use App\Services\GoogleMapsLinkResolver;
use App\Services\GoogleWalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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
            // Ubicación (relevancia en pantalla de bloqueo)
            'location_enabled'        => ['sometimes', 'boolean'],
            'maps_link'               => ['nullable', 'url'],
            'location_relevant_text'  => ['nullable', 'string', 'max:128'],
            // Imágenes para Wallet (viven en LoyaltyProgram, no en Business)
            'total_stamps'           => ['required', 'integer', 'min:1', 'max:50'],
            'background_mode'        => ['nullable', 'in:image,color'],
            'pass_background_image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
            'filled_stamp_image'    => ['nullable', 'image', 'mimes:png,webp', 'max:2048'],
            'empty_stamp_image'     => ['nullable', 'image', 'mimes:png,webp', 'max:2048'],
        ]);

        // Ubicación: el checkbox desactivado no viaja en el request, así que se normaliza
        // explícitamente. Un enlace de Maps reemplaza las coordenadas; si se deja vacío se
        // conservan las ya guardadas (siempre que existan).
        $data['location_enabled'] = $request->boolean('location_enabled');
        unset($data['maps_link']);

        if ($data['location_enabled']) {
            if ($request->filled('maps_link')) {
                try {
                    [$data['latitude'], $data['longitude']] = app(GoogleMapsLinkResolver::class)
                        ->extract($request->input('maps_link'));
                } catch (\Throwable $e) {
                    return back()->withErrors(['maps_link' => $e->getMessage()])->withInput();
                }
            } elseif (! $business->latitude || ! $business->longitude) {
                return back()
                    ->withErrors(['maps_link' => 'Pega el enlace de Google Maps de tu negocio para activar esta opción.'])
                    ->withInput();
            }
        }

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

            // "Color sólido de marca" (Versión 2): sin archivo que subir — se limpia la imagen
            // guardada para que el renderizador use el color primario del negocio, que ya es
            // su comportamiento por defecto cuando pass_background_image está vacío.
            if (($data['background_mode'] ?? 'image') === 'color') {
                if ($program->pass_background_image) {
                    Storage::disk('public')->delete($program->pass_background_image);
                }
                $programData['pass_background_image'] = null;
            } elseif ($request->hasFile('pass_background_image')) {
                if ($program->pass_background_image) {
                    Storage::disk('public')->delete($program->pass_background_image);
                }
                $programData['pass_background_image'] = $request->file('pass_background_image')->store('programs/stamps', 'public');
            }

            foreach (['filled_stamp_image', 'empty_stamp_image'] as $imageField) {
                if ($request->hasFile($imageField)) {
                    if ($program->$imageField) {
                        Storage::disk('public')->delete($program->$imageField);
                    }
                    $programData[$imageField] = $request->file($imageField)->store('programs/stamps', 'public');
                }
            }

            $program->fill($programData)->save();

            // La ubicación vive en el negocio, pero Google Wallet la guarda en la clase del
            // programa (merchantLocations). Se resincroniza aquí para que el cambio aplique
            // sin esperar a que se emita una tarjeta nueva. Un fallo aquí no debe tumbar el
            // guardado del perfil — solo se registra.
            try {
                app(GoogleWalletService::class)->ensureClass($program);
            } catch (\Throwable $e) {
                Log::warning('BusinessProfile: no se pudo sincronizar la ubicación con Google Wallet', [
                    'business_id' => $business->id,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        return redirect()->route('business.profile')->with('success', 'Información del negocio actualizada.');
    }
}
