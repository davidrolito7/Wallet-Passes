@extends('business.layout')

@section('title', 'Mi Negocio')
@section('heading', 'Mi Negocio')

@section('content')
@php
    $defaultImgVersion = ($program?->filled_stamp_image || $program?->empty_stamp_image) ? '2' : '1';
    $imgVersion = old('image_version', $defaultImgVersion);
    // Versión 2 siempre usa una cuadrícula fija de 10 sellos, así que ese modo no
    // pide el total — solo la Versión 1 (contador de texto) lo necesita.
    $totalStamps = old('total_stamps', $imgVersion === '2' ? 10 : ($program?->total_stamps ?? 10));
    // Versión 2: fondo con imagen o color sólido de marca (por defecto, el primario).
    $bgModeV2 = old('background_mode', $program?->pass_background_image ? 'image' : 'color');
@endphp
<form method="POST" action="{{ route('business.profile.save') }}" enctype="multipart/form-data" class="space-y-8 max-w-3xl">
    @csrf

    {{-- Identidad --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="text-base font-semibold text-gray-900 mb-5">Información del Negocio</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nombre del negocio *</label>
                <input type="text" name="name" value="{{ old('name', $business->name) }}" required
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('name') border-red-400 @enderror">
                @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Logo</label>
                <input type="file" name="logo_url" accept="image/png,image/webp"
                       class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-3 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                @if($business->logo_url)
                    <div class="mt-2 flex items-center gap-2">
                        <img src="{{ $business->logoPublicUrl() }}" alt="Logo actual" class="h-10 object-contain rounded border border-gray-200 p-0.5 bg-gray-50">
                        <span class="text-xs text-gray-400">Logo actual</span>
                    </div>
                @endif
                <p class="mt-1 text-xs text-gray-400">PNG o WebP · Recomendado 320×100 px con fondo transparente</p>
            </div>

        </div>
    </div>

    {{-- Colores de marca --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="text-base font-semibold text-gray-900 mb-1">Colores de Marca</h2>
        <p class="text-xs text-gray-500 mb-5">Estos colores se aplican a las tarjetas de lealtad en Apple Wallet y Google Wallet.</p>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Color primario</label>
                <div class="flex items-center gap-2">
                    <input type="color" value="{{ old('primary_color', $business->primary_color ?? '#1a1a2e') }}"
                           oninput="document.getElementById('txt_primary').value=this.value"
                           class="h-10 w-14 rounded-lg border border-gray-300 cursor-pointer p-0.5 bg-white">
                    <input type="text" id="txt_primary" name="primary_color"
                           value="{{ old('primary_color', $business->primary_color ?? '#1a1a2e') }}"
                           oninput="this.previousElementSibling.value=this.value"
                           maxlength="7"
                           class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <p class="mt-1 text-xs text-gray-400">Fondo de la tarjeta</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Color secundario</label>
                <div class="flex items-center gap-2">
                    <input type="color" value="{{ old('secondary_color', $business->secondary_color ?? '#ffffff') }}"
                           oninput="document.getElementById('txt_secondary').value=this.value"
                           class="h-10 w-14 rounded-lg border border-gray-300 cursor-pointer p-0.5 bg-white">
                    <input type="text" id="txt_secondary" name="secondary_color"
                           value="{{ old('secondary_color', $business->secondary_color ?? '#ffffff') }}"
                           oninput="this.previousElementSibling.value=this.value"
                           maxlength="7"
                           class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <p class="mt-1 text-xs text-gray-400">Texto principal</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Color de etiquetas</label>
                <div class="flex items-center gap-2">
                    <input type="color" value="{{ old('label_color', $business->label_color ?? '#cccccc') }}"
                           oninput="document.getElementById('txt_label').value=this.value"
                           class="h-10 w-14 rounded-lg border border-gray-300 cursor-pointer p-0.5 bg-white">
                    <input type="text" id="txt_label" name="label_color"
                           value="{{ old('label_color', $business->label_color ?? '#cccccc') }}"
                           oninput="this.previousElementSibling.value=this.value"
                           maxlength="7"
                           class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <p class="mt-1 text-xs text-gray-400">Labels de la tarjeta</p>
            </div>

        </div>

       
    </div>

    {{-- Contacto --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="text-base font-semibold text-gray-900 mb-5">Contacto</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                <input type="tel" name="contact_phone" value="{{ old('contact_phone', $business->contact_phone) }}"
                       placeholder="+52 55 0000 0000"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <p class="mt-1 text-xs text-gray-400">Aparece en el reverso de la tarjeta de lealtad</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Instagram</label>
                <input type="url" name="instagram_url" value="{{ old('instagram_url', $business->instagram_url) }}"
                       placeholder="https://instagram.com/tu_negocio"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('instagram_url') border-red-400 @enderror">
                @error('instagram_url') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                <p class="mt-1 text-xs text-gray-400">Aparece como enlace en el reverso de la tarjeta</p>
            </div>

        </div>
    </div>

    {{-- Ubicación --}}
    @php $locationEnabled = old('location_enabled', $business->location_enabled); @endphp
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="text-base font-semibold text-gray-900 mb-1">Ubicación</h2>
        <p class="text-xs text-gray-500 mb-5">
            Actívala para que la tarjeta aparezca sola en la pantalla de bloqueo cuando el cliente se acerca a tu negocio
            (Apple Wallet y Google Wallet). Apple limita esto a un radio real de ~100 m sin importar la ubicación configurada.
        </p>

        <label class="flex items-center gap-3 cursor-pointer select-none mb-5">
            <input type="checkbox" name="location_enabled" value="1"
                   {{ $locationEnabled ? 'checked' : '' }}
                   onchange="document.getElementById('location-fields').classList.toggle('hidden', !this.checked)"
                   class="h-5 w-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
            <span class="text-sm font-medium text-gray-800">Activar notificación automática al acercarse</span>
        </label>

        <div id="location-fields" class="grid grid-cols-1 gap-5 {{ $locationEnabled ? '' : 'hidden' }}">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Enlace de Google Maps</label>
                <input type="url" name="maps_link" value="{{ old('maps_link') }}"
                       placeholder="https://maps.app.goo.gl/xxxxx"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('maps_link') border-red-400 @enderror">
                @error('maps_link') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                <p class="mt-1 text-xs text-gray-400">
                    Abre tu negocio en
                    <a href="https://www.google.com/maps/search/{{ urlencode($business->name) }}" target="_blank" rel="noopener" class="text-indigo-600 hover:underline">Google Maps</a>,
                    toca "Compartir" → "Copiar enlace" y pégalo aquí. Extraemos las coordenadas automáticamente.
                </p>
                @if($business->latitude && $business->longitude)
                    <p class="mt-1 text-xs text-gray-400">
                        Ubicación actual guardada: {{ $business->latitude }}, {{ $business->longitude }} — deja este campo vacío para conservarla.
                    </p>
                @endif
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Mensaje al acercarse</label>
                <input type="text" name="location_relevant_text"
                       value="{{ old('location_relevant_text', $business->location_relevant_text) }}"
                       placeholder="¡Bienvenido! Muestra tu tarjeta de lealtad."
                       maxlength="128"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('location_relevant_text') border-red-400 @enderror">
                <p class="mt-1 text-xs text-gray-400">Solo aplica a Apple Wallet. Máximo 128 caracteres.</p>
                @error('location_relevant_text') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    {{-- Imágenes para Wallet --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="text-base font-semibold text-gray-900 mb-1">Imágenes para Wallet</h2>
        <p class="text-xs text-gray-500 mb-5">
            Solo sube las imágenes que deseas cambiar. Las actuales se conservan si no seleccionas un archivo nuevo.
        </p>

        {{-- v1 / v2 switch --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-6">
            <label for="img_v1"
                   id="label_v1"
                   class="relative flex items-start gap-3 p-4 rounded-xl border-2 cursor-pointer transition-all
                          {{ $imgVersion === '1' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300' }}">
                <input type="radio" id="img_v1" name="image_version" value="1"
                       {{ $imgVersion === '1' ? 'checked' : '' }}
                       class="mt-0.5 h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500"
                       onchange="switchImgVersion('1')">
                <div>
                    <p class="text-sm font-semibold text-gray-800">Versión 1 — Imagen de fondo</p>
                    <p class="text-xs text-gray-500 mt-0.5">Una sola imagen de fondo para la tarjeta. Sin sellos personalizados.</p>
                </div>
            </label>

            <label for="img_v2"
                   id="label_v2"
                   class="relative flex items-start gap-3 p-4 rounded-xl border-2 cursor-pointer transition-all
                          {{ $imgVersion === '2' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300' }}">
                <input type="radio" id="img_v2" name="image_version" value="2"
                       {{ $imgVersion === '2' ? 'checked' : '' }}
                       class="mt-0.5 h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500"
                       onchange="switchImgVersion('2')">
                <div>
                    <p class="text-sm font-semibold text-gray-800">Versión 2 — Sellos visuales</p>
                    <p class="text-xs text-gray-500 mt-0.5">Cuadrícula de sellos con sello lleno y vacío.</p>
                </div>
            </label>
        </div>

        {{-- Panel Versión 1 --}}
        <div id="panel-v1" class="{{ $imgVersion === '2' ? 'hidden' : '' }}">
            <div class="max-w-xs mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Total de visitas/sellos <span class="text-red-500">*</span>
                </label>
                <input type="number"
                       id="total-stamps-input"
                       name="total_stamps"
                       value="{{ $totalStamps }}"
                       min="1" max="50"
                       {{ $imgVersion === '1' ? 'required' : 'disabled' }}
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('total_stamps') border-red-400 @enderror">
                @error('total_stamps')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="max-w-sm">
                <label class="block text-sm font-medium text-gray-700 mb-1">Imagen de Fondo</label>
                <input type="file" id="bg-image-v1" name="pass_background_image" accept="image/png,image/jpeg,image/webp"
                       {{ $imgVersion === '1' ? '' : 'disabled' }}
                       class="block w-full text-sm text-gray-500
                              file:mr-4 file:py-2 file:px-3 file:rounded-md file:border-0
                              file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700
                              hover:file:bg-indigo-100">
                @if($program?->pass_background_image)
                    <div class="mt-2 flex items-center gap-2">
                        <img src="{{ Storage::disk('public')->url($program->pass_background_image) }}"
                             alt="Fondo" class="h-12 w-20 object-cover rounded border border-gray-200">
                        <span class="text-xs text-gray-400">Imagen actual</span>
                    </div>
                @endif
                <p class="mt-1 text-xs text-gray-400">PNG/JPEG/WebP · Recomendado 1032×450 px</p>
            </div>
        </div>

        {{-- Panel Versión 2 --}}
        <div id="panel-v2" class="{{ $imgVersion === '1' ? 'hidden' : '' }}">
            <input type="hidden"
                   id="total-stamps-v2"
                   name="total_stamps"
                   value="10"
                   {{ $imgVersion === '2' ? '' : 'disabled' }}>
            <p class="text-xs text-gray-400 mb-4">Este diseño siempre usa 10 sellos fijos.</p>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Fondo</label>

                <div class="flex gap-4 mb-3">
                    <label class="flex items-center gap-1.5 text-sm text-gray-700 cursor-pointer select-none">
                        <input type="radio" id="bg_mode_image" name="background_mode" value="image"
                               {{ $bgModeV2 === 'image' ? 'checked' : '' }}
                               {{ $imgVersion === '2' ? '' : 'disabled' }}
                               class="h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500"
                               onchange="switchBgMode('image')">
                        Imagen
                    </label>
                    <label class="flex items-center gap-1.5 text-sm text-gray-700 cursor-pointer select-none">
                        <input type="radio" id="bg_mode_color" name="background_mode" value="color"
                               {{ $bgModeV2 === 'color' ? 'checked' : '' }}
                               {{ $imgVersion === '2' ? '' : 'disabled' }}
                               class="h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500"
                               onchange="switchBgMode('color')">
                        Color sólido de marca
                    </label>
                </div>

                <div id="bg-mode-image-fields" class="max-w-sm {{ $bgModeV2 === 'color' ? 'hidden' : '' }}">
                    <input type="file" id="bg-image-v2" name="pass_background_image" accept="image/png,image/jpeg,image/webp"
                           {{ ($imgVersion === '2' && $bgModeV2 === 'image') ? '' : 'disabled' }}
                           class="block w-full text-sm text-gray-500
                                  file:mr-4 file:py-2 file:px-3 file:rounded-md file:border-0
                                  file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700
                                  hover:file:bg-indigo-100">
                    @if($program?->pass_background_image)
                        <div class="mt-2 flex items-center gap-2">
                            <img src="{{ Storage::disk('public')->url($program->pass_background_image) }}"
                                 alt="Fondo" class="h-12 w-20 object-cover rounded border border-gray-200">
                            <span class="text-xs text-gray-400">Imagen actual</span>
                        </div>
                    @endif
                    <p class="mt-1 text-xs text-gray-400">PNG/JPEG/WebP · Recomendado 1032×450 px · Se muestra detrás de la cuadrícula de sellos</p>
                </div>

                <div id="bg-mode-color-fields" class="{{ $bgModeV2 === 'image' ? 'hidden' : '' }}">
                    <div class="flex items-center gap-2">
                        <span class="h-8 w-8 rounded-lg border border-gray-200" style="background-color: {{ $business->primary_color ?? '#1a1a2e' }}"></span>
                        <span class="text-sm text-gray-600">Usa tu color primario de marca ({{ $business->primary_color ?? '#1a1a2e' }})</span>
                    </div>
                    <p class="mt-1 text-xs text-gray-400">Se ajusta solo si cambias el color primario en "Colores de Marca".</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                @foreach([
                    ['filled_stamp_image', 'Sello Lleno', 'Aparece en visitas ya registradas · PNG transparente · 200×200 px'],
                    ['empty_stamp_image',  'Sello Vacío', 'Aparece en visitas pendientes · PNG transparente · 200×200 px'],
                ] as [$field, $label, $hint])
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ $label }}</label>
                        <input type="file" name="{{ $field }}" accept="image/png,image/webp"
                               class="block w-full text-sm text-gray-500
                                      file:mr-4 file:py-2 file:px-3 file:rounded-md file:border-0
                                      file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700
                                      hover:file:bg-indigo-100">
                        @if($program?->$field)
                            <div class="mt-2 flex items-center gap-2">
                                <img src="{{ Storage::disk('public')->url($program->$field) }}"
                                     alt="{{ $label }}" class="h-12 w-12 object-cover rounded border border-gray-200">
                                <span class="text-xs text-gray-400">Imagen actual</span>
                            </div>
                        @endif
                        <p class="mt-1 text-xs text-gray-400">{{ $hint }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="flex justify-end">
        <button type="submit"
                class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 px-8 rounded-lg text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
            Guardar cambios
        </button>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    const primary   = document.getElementById('txt_primary');
    const secondary = document.getElementById('txt_secondary');
    const label     = document.getElementById('txt_label');
    const preview   = document.getElementById('color-preview');
    const pName     = document.getElementById('preview-name');
    const pLabel    = document.getElementById('preview-label');

    function refreshPreview() {
        if (preview) preview.style.backgroundColor = primary.value;
        if (pName)   pName.style.color             = secondary.value;
        if (pLabel)  pLabel.style.color             = label.value;
    }

    [primary, secondary, label].forEach(el => el?.addEventListener('input', refreshPreview));
})();

// ── Image version switch ──────────────────────────────────────────────────────

function switchImgVersion(v) {
    const v1Panel = document.getElementById('panel-v1');
    const v2Panel = document.getElementById('panel-v2');
    const labelV1 = document.getElementById('label_v1');
    const labelV2 = document.getElementById('label_v2');

    // Both panels have a "pass_background_image" file input sharing the same name. An empty
    // file input still submits a (blank) part under that name, and the one later in the DOM
    // wins in $_FILES — so clearing .value is not enough, the inactive one must be disabled
    // (disabled inputs are excluded from submission entirely).
    const bgV1 = document.getElementById('bg-image-v1');
    const bgV2 = document.getElementById('bg-image-v2');

    // Total de sellos: Versión 1 lo pide (contador de texto libre); Versión 2 lo fija en 10
    // (cuadrícula fija). Solo el input de la versión activa debe enviarse/validarse.
    const stampsV1 = document.getElementById('total-stamps-input');
    const stampsV2 = document.getElementById('total-stamps-v2');

    const bgModeImage = document.getElementById('bg_mode_image');
    const bgModeColor = document.getElementById('bg_mode_color');

    if (v === '1') {
        v1Panel.classList.remove('hidden');
        v2Panel.classList.add('hidden');
        labelV1.classList.add('border-indigo-500', 'bg-indigo-50');
        labelV1.classList.remove('border-gray-200');
        labelV2.classList.remove('border-indigo-500', 'bg-indigo-50');
        labelV2.classList.add('border-gray-200');
        if (bgV2) { bgV2.value = ''; bgV2.disabled = true; }
        if (bgV1) bgV1.disabled = false;
        if (bgModeImage) bgModeImage.disabled = true;
        if (bgModeColor) bgModeColor.disabled = true;
        if (stampsV1) { stampsV1.disabled = false; stampsV1.required = true; }
        if (stampsV2) stampsV2.disabled = true;
    } else {
        v2Panel.classList.remove('hidden');
        v1Panel.classList.add('hidden');
        labelV2.classList.add('border-indigo-500', 'bg-indigo-50');
        labelV2.classList.remove('border-gray-200');
        labelV1.classList.remove('border-indigo-500', 'bg-indigo-50');
        labelV1.classList.add('border-gray-200');
        if (bgV1) { bgV1.value = ''; bgV1.disabled = true; }
        if (bgModeImage) bgModeImage.disabled = false;
        if (bgModeColor) bgModeColor.disabled = false;
        // bg-image-v2 only re-enables if "Imagen" is the selected background mode.
        if (bgV2) bgV2.disabled = !!bgModeColor?.checked;
        if (stampsV1) { stampsV1.disabled = true; stampsV1.required = false; }
        if (stampsV2) stampsV2.disabled = false;
    }
}

// ── Background mode switch (Versión 2 only) ────────────────────────────────────

function switchBgMode(mode) {
    const imageFields = document.getElementById('bg-mode-image-fields');
    const colorFields = document.getElementById('bg-mode-color-fields');
    const bgV2 = document.getElementById('bg-image-v2');

    if (mode === 'image') {
        imageFields?.classList.remove('hidden');
        colorFields?.classList.add('hidden');
        if (bgV2) bgV2.disabled = false;
    } else {
        imageFields?.classList.add('hidden');
        colorFields?.classList.remove('hidden');
        if (bgV2) { bgV2.value = ''; bgV2.disabled = true; }
    }
}
</script>
@endpush
