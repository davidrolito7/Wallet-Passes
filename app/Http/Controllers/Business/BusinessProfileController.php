<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Business;
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
        $business->load('locations');
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
            // Ubicaciones (relevancia en pantalla de bloqueo) — una o varias por negocio.
            'locations'                  => ['nullable', 'array'],
            'locations.*.id'             => ['nullable', 'integer'],
            'locations.*.name'           => ['nullable', 'string', 'max:255'],
            'locations.*.maps_link'      => ['nullable', 'url'],
            'locations.*.relevant_text'  => ['nullable', 'string', 'max:128'],
            'locations.*.is_active'      => ['sometimes'],
            'locations.*.delete'         => ['sometimes'],
            // Imágenes para Wallet (viven en LoyaltyProgram, no en Business)
            'total_stamps'           => ['required', 'integer', 'min:1', 'max:50'],
            'background_mode'        => ['nullable', 'in:image,color'],
            'background_solid_custom'  => ['sometimes'],
            'background_solid_color'   => ['nullable', 'regex:/^#[0-9a-fA-F]{3,6}$/'],
            'pass_background_image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
            'filled_stamp_image'    => ['nullable', 'image', 'mimes:png,webp', 'max:2048'],
            'empty_stamp_image'     => ['nullable', 'image', 'mimes:png,webp', 'max:2048'],
        ]);

        // Ubicaciones: se resuelven todos los enlaces de Maps antes de tocar la base de datos,
        // para no dejar guardados solo algunos locales si uno de los enlaces es inválido.
        $locationRows = $data['locations'] ?? [];
        unset($data['locations']);

        try {
            $resolvedLocations = $this->resolveLocationRows($business, $locationRows);
        } catch (\Throwable $e) {
            return back()->withErrors(['locations' => $e->getMessage()])->withInput();
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

        $this->applyLocations($business, $resolvedLocations);

        // Las imágenes para Wallet pertenecen al programa de lealtad del negocio. Antes, si el
        // negocio todavía no había creado su programa (página "Programa de Lealtad" o Filament),
        // este bloque completo se saltaba EN SILENCIO — ninguna imagen (fondo, sello lleno,
        // sello vacío) tenía dónde guardarse, sin error ni aviso. El formulario igual mostraba
        // éxito porque $business->fill($data)->save() de arriba sí se guardaba. Ahora se crea
        // el programa con datos mínimos si todavía no existe, para que la primera vez también
        // funcione — el negocio puede completar nombre/premio real después en "Programa de
        // Lealtad" sin que eso afecte lo ya subido aquí.
        $program = $business->loyaltyPrograms()->first();

        if (! $program) {
            $program = $business->loyaltyPrograms()->create([
                'name'         => $business->name,
                'reward_title' => 'Premio',
            ]);
        }

        $programData = ['total_stamps' => $data['total_stamps']];

        // "Color sólido" (Versión 2): sin archivo que subir — se limpia la imagen guardada
        // para que el renderizador use un color de fondo en su lugar. Por defecto ese color
        // es el mismo "Fondo de la tarjeta" del negocio; si activó "Elegir otro color" se
        // guarda ese en su lugar (LoyaltyProgram::resolvedBackgroundColor()).
        if (($data['background_mode'] ?? 'image') === 'color') {
            if ($program->pass_background_image) {
                Storage::disk('public')->delete($program->pass_background_image);
            }
            $programData['pass_background_image'] = null;
            $programData['background_solid_color'] = ($request->boolean('background_solid_custom') && filled($data['background_solid_color'] ?? null))
                ? $data['background_solid_color']
                : null;
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

        return redirect()->route('business.profile')->with('success', 'Información del negocio actualizada.');
    }

    /**
     * Valida y resuelve cada fila de ubicación enviada desde el formulario (sin tocar la base
     * de datos todavía), para poder rechazar el guardado completo si algún enlace de Maps es
     * inválido en vez de dejar el negocio con locales a medio guardar.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{action: string, id: ?int, data: array<string, mixed>}>
     */
    private function resolveLocationRows(Business $business, array $rows): array
    {
        $resolver = app(GoogleMapsLinkResolver::class);
        $resolved = [];

        foreach ($rows as $row) {
            $id       = isset($row['id']) ? (int) $row['id'] : null;
            $delete   = filled($row['delete'] ?? null);
            $mapsLink = trim((string) ($row['maps_link'] ?? ''));

            if ($id && $delete) {
                $resolved[] = ['action' => 'delete', 'id' => $id, 'data' => []];
                continue;
            }

            // Fila nueva sin enlace: no hay nada que crear todavía, se ignora.
            if (! $id && $mapsLink === '') {
                continue;
            }

            $data = [
                'name'          => $row['name'] ?? null,
                'relevant_text' => $row['relevant_text'] ?? null,
                'is_active'     => filled($row['is_active'] ?? null),
            ];

            if ($mapsLink !== '') {
                try {
                    [$data['latitude'], $data['longitude']] = $resolver->extract($mapsLink);
                } catch (\Throwable $e) {
                    throw new \RuntimeException(
                        ($data['name'] ? "{$data['name']}: " : '').$e->getMessage()
                    );
                }
            }

            $resolved[] = ['action' => $id ? 'update' : 'create', 'id' => $id, 'data' => $data];
        }

        return $resolved;
    }

    /**
     * @param  array<int, array{action: string, id: ?int, data: array<string, mixed>}>  $resolvedLocations
     */
    private function applyLocations(Business $business, array $resolvedLocations): void
    {
        foreach ($resolvedLocations as $entry) {
            if ($entry['action'] === 'delete') {
                $business->locations()->where('id', $entry['id'])->delete();
                continue;
            }

            if ($entry['action'] === 'update') {
                $business->locations()->where('id', $entry['id'])->first()?->fill($entry['data'])->save();
                continue;
            }

            $business->locations()->create($entry['data']);
        }
    }
}
