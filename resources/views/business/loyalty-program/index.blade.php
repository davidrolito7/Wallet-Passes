@extends('business.layout')

@section('title', 'Programa de Lealtad')
@section('heading', 'Programa de Lealtad')

@section('content')
@php
    $defaultImgVersion = ($program?->filled_stamp_image || $program?->empty_stamp_image || $program?->reward_badge_image) ? '2' : '1';
    $imgVersion        = old('image_version', $defaultImgVersion);
    $birthdayEnabled   = old('birthday_reward_enabled', $program?->birthday_reward_enabled ?? false);
    $notifEnabled      = old('visit_notification_enabled', $program?->visit_notification_enabled ?? false);
@endphp
<div class="space-y-8">

    <form method="POST" action="{{ route('business.loyalty-program.save') }}" enctype="multipart/form-data" class="space-y-8">
        @csrf

        {{-- Información general --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-5">Información General</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nombre del programa *</label>
                    <input type="text" name="name" value="{{ old('name', $program?->name) }}" required
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('name') border-red-400 @enderror">
                    @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
                    <textarea name="description" rows="3"
                              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none">{{ old('description', $program?->description) }}</textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Total de visitas/sellos *</label>
                    <input type="number" name="total_stamps" value="{{ old('total_stamps', $program?->total_stamps ?? 10) }}" min="1" max="50" required
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    @error('total_stamps') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-center pt-6">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" id="is_active" name="is_active" value="1"
                           {{ old('is_active', $program?->is_active ?? true) ? 'checked' : '' }}
                           class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                    <label for="is_active" class="ml-2 text-sm font-medium text-gray-700">Programa activo</label>
                </div>
            </div>
        </div>

        {{-- Premio principal --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-5">Premio Principal</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Premio *</label>
                    <input type="text" name="reward_title" value="{{ old('reward_title', $program?->reward_title) }}" required
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('reward_title') border-red-400 @enderror">
                    @error('reward_title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Descripción del premio</label>
                    <input type="text" name="reward_description" value="{{ old('reward_description', $program?->reward_description) }}"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>
        </div>

        {{-- Premios intermedios --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-5">
                <h2 class="text-base font-semibold text-gray-900">Premios Intermedios (Milestones)</h2>
                <button type="button" id="add-milestone"
                        data-milestone-count="{{ count(old('milestones', $program?->milestones?->toArray() ?? [])) }}"
                        class="text-sm text-indigo-600 hover:text-indigo-800 font-medium flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Agregar milestone
                </button>
            </div>

            <div id="milestones-container" class="space-y-4">
                @php $milestones = old('milestones', $program?->milestones?->toArray() ?? []); @endphp
                @foreach($milestones as $i => $milestone)
                    <div class="milestone-row grid grid-cols-1 md:grid-cols-4 gap-3 p-4 bg-gray-50 rounded-lg border border-gray-200">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Visita #</label>
                            <input type="number" name="milestones[{{ $i }}][stamp_count]" value="{{ $milestone['stamp_count'] }}" min="1"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Premio *</label>
                            <input type="text" name="milestones[{{ $i }}][reward_title]" value="{{ $milestone['reward_title'] }}"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Descripción</label>
                            <input type="text" name="milestones[{{ $i }}][reward_description]" value="{{ $milestone['reward_description'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div class="flex items-end gap-3">
                            <label class="flex items-center gap-1.5 text-xs text-gray-600 mb-2">
                                <input type="checkbox" name="milestones[{{ $i }}][is_repeatable]" value="1"
                                       {{ ($milestone['is_repeatable'] ?? false) ? 'checked' : '' }}
                                       class="h-3.5 w-3.5 rounded border-gray-300 text-indigo-600">
                                Repetible
                            </label>
                            <button type="button" class="remove-milestone mb-2 text-red-500 hover:text-red-700">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>

            @if(empty($milestones))
                <p id="no-milestones-hint" class="text-sm text-gray-400 text-center py-4">
                    Sin premios intermedios. Agrega uno si quieres premiar al cliente antes del objetivo final.
                </p>
            @endif
        </div>

        {{-- Imágenes para Wallet — v1 / v2 --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-1">Imágenes para Wallet</h2>
            <p class="text-xs text-gray-500 mb-5">
                Solo sube las imágenes que deseas cambiar. Las actuales se conservan si no seleccionas un archivo nuevo.
            </p>

            {{-- Switch v1 / v2 --}}
            <div class="grid grid-cols-2 gap-3 mb-6">
                <label for="img_v1"
                       class="relative flex items-start gap-3 p-4 rounded-xl border-2 cursor-pointer transition-all
                              {{ $imgVersion === '1' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300' }}"
                       id="label_v1">
                    <input type="radio" id="img_v1" name="image_version" value="1"
                           {{ $imgVersion === '1' ? 'checked' : '' }}
                           class="mt-0.5 h-4 w-4 text-indigo-600 border-gray-300"
                           onchange="switchImgVersion('1')">
                    <div>
                        <p class="text-sm font-semibold text-gray-800">Versión 1 — Imagen de fondo</p>
                        <p class="text-xs text-gray-500 mt-0.5">Una sola imagen de fondo para la tarjeta. Sin sellos personalizados.</p>
                    </div>
                </label>

                <label for="img_v2"
                       class="relative flex items-start gap-3 p-4 rounded-xl border-2 cursor-pointer transition-all
                              {{ $imgVersion === '2' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300' }}"
                       id="label_v2">
                    <input type="radio" id="img_v2" name="image_version" value="2"
                           {{ $imgVersion === '2' ? 'checked' : '' }}
                           class="mt-0.5 h-4 w-4 text-indigo-600 border-gray-300"
                           onchange="switchImgVersion('2')">
                    <div>
                        <p class="text-sm font-semibold text-gray-800">Versión 2 — Sellos visuales</p>
                        <p class="text-xs text-gray-500 mt-0.5">Cuadrícula de sellos con sello lleno, vacío y badge de premio.</p>
                    </div>
                </label>
            </div>

            {{-- Panel Versión 1 --}}
            <div id="panel-v1" class="{{ $imgVersion === '2' ? 'hidden' : '' }}">
                <div class="max-w-sm">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Imagen de Fondo</label>
                    <input type="file" name="pass_background_image" accept="image/png,image/jpeg,image/webp"
                           class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-3 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                    @if($program?->pass_background_image)
                        <div class="mt-2 flex items-center gap-2">
                            <img src="{{ Storage::disk('public')->url($program->pass_background_image) }}" alt="Fondo" class="h-12 w-20 object-cover rounded border border-gray-200">
                            <span class="text-xs text-gray-400">Imagen actual</span>
                        </div>
                    @endif
                    <p class="mt-1 text-xs text-gray-400">PNG/JPEG/WebP · Recomendado 1032×300 px</p>
                </div>
            </div>

            {{-- Panel Versión 2 --}}
            <div id="panel-v2" class="{{ $imgVersion === '1' ? 'hidden' : '' }}">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    @foreach([
                        ['filled_stamp_image', 'Sello Lleno',    'Aparece en visitas ya registradas · PNG transparente · 200×200 px'],
                        ['empty_stamp_image',  'Sello Vacío',    'Aparece en visitas pendientes · PNG transparente · 200×200 px'],
                        ['reward_badge_image', 'Badge de Premio','Se superpone en milestones y sello final · PNG transparente · 100×100 px'],
                    ] as [$field, $label, $hint])
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $label }}</label>
                            <input type="file" name="{{ $field }}" accept="image/png,image/webp"
                                   class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-3 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                            @if($program?->$field)
                                <div class="mt-2 flex items-center gap-2">
                                    <img src="{{ Storage::disk('public')->url($program->$field) }}" alt="{{ $label }}" class="h-12 w-12 object-cover rounded border border-gray-200">
                                    <span class="text-xs text-gray-400">Imagen actual</span>
                                </div>
                            @endif
                            <p class="mt-1 text-xs text-gray-400">{{ $hint }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Premio de Cumpleaños --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-1">Premio de Cumpleaños</h2>
            <p class="text-xs text-gray-500 mb-5">El cliente verá su regalo especial en la tarjeta el día de su cumpleaños.</p>

            <div class="space-y-5">
                <div class="flex items-start gap-3">
                    <input type="hidden" name="birthday_reward_enabled" value="0">
                    <input type="checkbox"
                           id="birthday_reward_enabled"
                           name="birthday_reward_enabled"
                           value="1"
                           @checked($birthdayEnabled)
                           class="mt-0.5 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                           onchange="toggleBirthdayFields(this.checked)">
                    <div>
                        <label for="birthday_reward_enabled" class="text-sm font-medium text-gray-700">
                            Activar premio de cumpleaños
                        </label>
                        <p class="text-xs text-gray-400 mt-0.5">
                            Cuando esté activo, el cliente verá su premio en la tarjeta el día de su cumpleaños.
                        </p>
                    </div>
                </div>

                <div id="birthday-fields"
                     class="space-y-4 pl-7 border-l-2 border-indigo-100 @if(!$birthdayEnabled) hidden @endif">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Premio</label>
                        <input type="text" name="birthday_reward_title"
                               value="{{ old('birthday_reward_title', $program?->birthday_reward_title) }}"
                               placeholder="Ej: Bebida gratis en tu cumpleaños"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('birthday_reward_title') border-red-400 @enderror">
                        @error('birthday_reward_title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
                        <textarea name="birthday_reward_description" rows="2"
                                  placeholder="Ej: Presenta esta tarjeta el día de tu cumpleaños y recibe una bebida completamente gratis."
                                  class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none">{{ old('birthday_reward_description', $program?->birthday_reward_description) }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        {{-- Notificaciones al Wallet --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-1">Notificaciones al Wallet</h2>
            <p class="text-xs text-gray-500 mb-5">
                Configura el mensaje que recibirá el cliente al registrar cada visita.
                <strong>Android</strong> muestra título y cuerpo vía Google Wallet.
                <strong>iPhone</strong> muestra el título como notificación push de Apple Wallet.
                Sin mensaje personalizado, ambas plataformas envían la notificación de sistema estándar.
            </p>

            <div class="space-y-5">
                <div class="flex items-start gap-3">
                    <input type="hidden" name="visit_notification_enabled" value="0">
                    <input type="checkbox"
                           id="visit_notification_enabled"
                           name="visit_notification_enabled"
                           value="1"
                           @checked($notifEnabled)
                           class="mt-0.5 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                           onchange="toggleNotificationFields(this.checked)">
                    <div>
                        <label for="visit_notification_enabled" class="text-sm font-medium text-gray-700">
                            Activar mensaje personalizado por visita
                        </label>
                        <p class="text-xs text-gray-400 mt-0.5">
                            Se enviará a Google Wallet (Android) y Apple Wallet (iPhone) cuando haya pass registrado.
                        </p>
                    </div>
                </div>

                <div id="notification-fields"
                     class="space-y-4 pl-7 border-l-2 border-indigo-100 @if(!$notifEnabled) hidden @endif">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mensaje de visita</label>
                        <textarea name="visit_notification_message"
                                  rows="2"
                                  maxlength="300"
                                  placeholder="Llevas {stamps_collected}/{total_stamps} visitas. ¡Gracias por visitarnos, {first_name}!"
                                  class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none @error('visit_notification_message') border-red-400 @enderror">{{ old('visit_notification_message', $program?->visit_notification_message) }}</textarea>
                        <p class="mt-1 text-xs text-gray-400">
                            Una sola oración. Variables:
                            <code class="bg-gray-100 px-1 rounded">{first_name}</code>
                            <code class="bg-gray-100 px-1 rounded">{stamps_collected}</code>
                            <code class="bg-gray-100 px-1 rounded">{total_stamps}</code>
                            <code class="bg-gray-100 px-1 rounded">{remaining_stamps}</code>
                            <code class="bg-gray-100 px-1 rounded">{business_name}</code>
                            <code class="bg-gray-100 px-1 rounded">{next_reward}</code>
                        </p>
                        @error('visit_notification_message')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <input type="hidden" name="google_wallet_notification_mode" value="custom_message_only">

                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Vista previa</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <p class="text-xs text-gray-400 mb-1.5">Android · Google Wallet</p>
                                <div class="flex items-start gap-2 bg-white border border-gray-200 rounded-lg p-3 shadow-sm">
                                    <div class="w-7 h-7 bg-blue-500 rounded-full flex items-center justify-center flex-shrink-0 text-white text-xs font-bold">G</div>
                                    <div class="min-w-0">
                                        <p id="preview-android-msg" class="text-gray-600 text-xs mt-0.5 line-clamp-2">Llevas {stamps_collected}/{total_stamps} visitas.</p>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 mb-1.5">iPhone · Apple Wallet</p>
                                <div class="flex items-start gap-2 bg-white border border-gray-200 rounded-lg p-3 shadow-sm">
                                    <div class="w-7 h-7 bg-black rounded-full flex items-center justify-center flex-shrink-0 text-white text-xs font-bold">A</div>
                                    <div class="min-w-0">
                                        <p class="font-semibold text-gray-800 text-xs">Apple Wallet</p>
                                        <p id="preview-apple-msg" class="text-gray-500 text-xs mt-0.5 truncate">Llevas {stamps_collected}/{total_stamps} visitas.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Google Wallet --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-1">Google Wallet</h2>
            <p class="text-xs text-gray-500 mb-5">Configuración avanzada para la integración con Google Wallet.</p>

            <div class="max-w-sm">
                <label class="block text-sm font-medium text-gray-700 mb-1">Class Suffix</label>
                <input type="text" name="google_class_suffix"
                       value="{{ old('google_class_suffix', $program?->google_class_suffix) }}"
                       placeholder="Ej: mi-negocio-lealtad"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <p class="mt-1 text-xs text-gray-400">Opcional. Identificador único de la clase en Google Wallet. Si no se especifica, se genera automáticamente.</p>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 px-8 rounded-lg text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                Guardar programa
            </button>
        </div>
    </form>

</div>
@endsection

@push('scripts')
<script>
// ── Version switch de imágenes ────────────────────────────────────────────────

function switchImgVersion(v) {
    const v1Panel  = document.getElementById('panel-v1');
    const v2Panel  = document.getElementById('panel-v2');
    const labelV1  = document.getElementById('label_v1');
    const labelV2  = document.getElementById('label_v2');

    if (v === '1') {
        v1Panel.classList.remove('hidden');
        v2Panel.classList.add('hidden');
        labelV1.classList.add('border-indigo-500', 'bg-indigo-50');
        labelV1.classList.remove('border-gray-200');
        labelV2.classList.remove('border-indigo-500', 'bg-indigo-50');
        labelV2.classList.add('border-gray-200');
    } else {
        v2Panel.classList.remove('hidden');
        v1Panel.classList.add('hidden');
        labelV2.classList.add('border-indigo-500', 'bg-indigo-50');
        labelV2.classList.remove('border-gray-200');
        labelV1.classList.remove('border-indigo-500', 'bg-indigo-50');
        labelV1.classList.add('border-gray-200');
    }
}

// ── Premio de cumpleaños ──────────────────────────────────────────────────────

function toggleBirthdayFields(enabled) {
    const fields = document.getElementById('birthday-fields');
    if (fields) fields.classList.toggle('hidden', !enabled);
}

// ── Notificaciones Wallet ─────────────────────────────────────────────────────

function toggleNotificationFields(enabled) {
    const fields = document.getElementById('notification-fields');
    if (fields) fields.classList.toggle('hidden', !enabled);
}

(function initNotificationPreview() {
    const msgInput   = document.querySelector('[name="visit_notification_message"]');
    const androidMsg = document.getElementById('preview-android-msg');
    const appleMsg   = document.getElementById('preview-apple-msg');

    if (!msgInput) return;

    function updatePreview() {
        const m = msgInput.value.trim() || 'Llevas {stamps_collected}/{total_stamps} visitas.';
        if (androidMsg) androidMsg.textContent = m;
        if (appleMsg)   appleMsg.textContent   = m;
    }

    msgInput.addEventListener('input', updatePreview);
    updatePreview();
})();

// ── Milestones ────────────────────────────────────────────────────────────────

const addBtn = document.getElementById('add-milestone');
let milestoneIndex = parseInt(addBtn?.dataset.milestoneCount ?? '0', 10);

addBtn.addEventListener('click', function () {
    const container = document.getElementById('milestones-container');
    const hint      = document.getElementById('no-milestones-hint');
    if (hint) hint.remove();

    const row = document.createElement('div');
    row.className = 'milestone-row grid grid-cols-1 md:grid-cols-4 gap-3 p-4 bg-gray-50 rounded-lg border border-gray-200';
    row.innerHTML = `
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Visita #</label>
            <input type="number" name="milestones[${milestoneIndex}][stamp_count]" min="1"
                   class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Premio *</label>
            <input type="text" name="milestones[${milestoneIndex}][reward_title]"
                   class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Descripción</label>
            <input type="text" name="milestones[${milestoneIndex}][reward_description]"
                   class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div class="flex items-end gap-3">
            <label class="flex items-center gap-1.5 text-xs text-gray-600 mb-2">
                <input type="checkbox" name="milestones[${milestoneIndex}][is_repeatable]" value="1"
                       class="h-3.5 w-3.5 rounded border-gray-300 text-indigo-600">
                Repetible
            </label>
            <button type="button" class="remove-milestone mb-2 text-red-500 hover:text-red-700">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
            </button>
        </div>
    `;
    container.appendChild(row);
    milestoneIndex++;

    row.querySelector('.remove-milestone').addEventListener('click', () => row.remove());
});

document.querySelectorAll('.remove-milestone').forEach(btn => {
    btn.addEventListener('click', () => btn.closest('.milestone-row').remove());
});
</script>
@endpush
