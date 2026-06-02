@extends('business.layout')

@section('title', 'Programa de Lealtad')
@section('heading', 'Programa de Lealtad')

@section('content')
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

    {{-- Imágenes para Wallet --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="text-base font-semibold text-gray-900 mb-1">Imágenes para Wallet</h2>
        <p class="text-xs text-gray-500 mb-5">Solo sube las imágenes que deseas cambiar. Las actuales se conservan si no seleccionas un archivo nuevo.</p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            @foreach([
                ['pass_background_image', 'Imagen de Fondo', 'Fondo del pase en Wallet. PNG/JPEG/WebP.'],
                ['filled_stamp_image',    'Sello Lleno',     'PNG con fondo transparente. 150×150 px recomendado.'],
                ['empty_stamp_image',     'Sello Vacío',     'PNG con fondo transparente. 150×150 px recomendado.'],
                ['reward_badge_image',    'Badge de Premio', 'PNG con fondo transparente. 150×150 px recomendado.'],
            ] as [$field, $label, $hint])
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ $label }}</label>
                    <input type="file" name="{{ $field }}" accept="image/png,image/jpeg,image/webp"
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

    {{-- Notificaciones Wallet (Google + Apple) --}}
    @php
        $notifEnabled = old('visit_notification_enabled', $program?->visit_notification_enabled ?? false);
        $currentMode  = old('google_wallet_notification_mode', $program?->google_wallet_notification_mode ?? 'custom_message_only');
    @endphp

    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="text-base font-semibold text-gray-900 mb-1">Notificaciones al Wallet</h2>
        <p class="text-xs text-gray-500 mb-5">
            Configura el mensaje que recibirá el cliente al registrar cada visita.
            <strong>Android</strong> muestra título y cuerpo vía Google Wallet.
            <strong>iPhone</strong> muestra el título como notificación push de Apple Wallet.
            Sin mensaje personalizado, ambas plataformas envían la notificación de sistema estándar.
        </p>

        <div class="space-y-5">

            {{-- Toggle principal --}}
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

            {{-- Campos condicionales: ocultos/visibles con JS --}}
            <div id="notification-fields"
                 class="space-y-4 pl-7 border-l-2 border-indigo-100 @if(!$notifEnabled) hidden @endif">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                    {{-- Título --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Título del mensaje
                            <span class="text-xs font-normal text-gray-400 ml-1">— notificación push en ambas plataformas</span>
                        </label>
                        <input type="text"
                               name="visit_notification_title"
                               value="{{ old('visit_notification_title', $program?->visit_notification_title) }}"
                               maxlength="100"
                               placeholder="Nueva visita registrada"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('visit_notification_title') border-red-400 @enderror">
                        <p class="mt-1 text-xs text-gray-400">
                            Variables:
                            <code class="bg-gray-100 px-1 rounded">{first_name}</code>
                            <code class="bg-gray-100 px-1 rounded">{stamps_collected}</code>
                            <code class="bg-gray-100 px-1 rounded">{total_stamps}</code>
                            <code class="bg-gray-100 px-1 rounded">{business_name}</code>
                        </p>
                        @error('visit_notification_title')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Modo Android --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Modo de notificación Android
                            <span class="text-xs font-normal text-gray-400 ml-1">— solo afecta Google Wallet</span>
                        </label>
                        <select name="google_wallet_notification_mode"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                            <option value="custom_message_only" @selected($currentMode === 'custom_message_only')>
                                Solo mensaje personalizado (recomendado — 1 notificación)
                            </option>
                            <option value="both" @selected($currentMode === 'both')>
                                Ambas — balance del sistema + mensaje (2 notificaciones)
                            </option>
                        </select>
                        <p class="mt-1 text-xs text-gray-400">
                            iPhone siempre usa el título como `changeMessage` (1 notificación). Este ajuste solo controla Android.
                        </p>
                    </div>

                </div>

                {{-- Cuerpo del mensaje --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Cuerpo del mensaje
                        <span class="text-xs font-normal text-gray-400 ml-1">— solo visible en Google Wallet (Android)</span>
                    </label>
                    <textarea name="visit_notification_message"
                              rows="3"
                              maxlength="500"
                              placeholder="Llevas {stamps_collected}/{total_stamps} visitas. ¡Gracias por visitarnos, {first_name}!"
                              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none @error('visit_notification_message') border-red-400 @enderror">{{ old('visit_notification_message', $program?->visit_notification_message) }}</textarea>
                    <p class="mt-1 text-xs text-gray-400">
                        Variables: <code class="bg-gray-100 px-1 rounded">{first_name}</code>
                        <code class="bg-gray-100 px-1 rounded">{full_name}</code>
                        <code class="bg-gray-100 px-1 rounded">{stamps_collected}</code>
                        <code class="bg-gray-100 px-1 rounded">{total_stamps}</code>
                        <code class="bg-gray-100 px-1 rounded">{remaining_stamps}</code>
                        <code class="bg-gray-100 px-1 rounded">{business_name}</code>
                        <code class="bg-gray-100 px-1 rounded">{next_reward}</code>
                        <code class="bg-gray-100 px-1 rounded">{reward_title}</code>
                    </p>
                    @error('visit_notification_message')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Vista previa --}}
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Vista previa</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                        {{-- Android preview --}}
                        <div>
                            <p class="text-xs text-gray-400 mb-1.5">Android · Google Wallet</p>
                            <div class="flex items-start gap-2 bg-white border border-gray-200 rounded-lg p-3 shadow-sm">
                                <div class="w-7 h-7 bg-blue-500 rounded-full flex items-center justify-center flex-shrink-0 text-white text-xs font-bold">G</div>
                                <div class="min-w-0">
                                    <p id="preview-android-title" class="font-semibold text-gray-800 text-xs truncate">Nueva visita registrada</p>
                                    <p id="preview-android-body" class="text-gray-500 text-xs mt-0.5 line-clamp-2">Llevas {stamps_collected}/{total_stamps} visitas.</p>
                                </div>
                            </div>
                        </div>

                        {{-- iPhone preview --}}
                        <div>
                            <p class="text-xs text-gray-400 mb-1.5">iPhone · Apple Wallet</p>
                            <div class="flex items-start gap-2 bg-white border border-gray-200 rounded-lg p-3 shadow-sm">
                                <div class="w-7 h-7 bg-black rounded-full flex items-center justify-center flex-shrink-0 text-white text-xs font-bold">A</div>
                                <div class="min-w-0">
                                    <p class="font-semibold text-gray-800 text-xs">Apple Wallet</p>
                                    <p id="preview-apple-title" class="text-gray-500 text-xs mt-0.5 truncate">Nueva visita registrada</p>
                                    <p class="text-gray-400 text-xs italic mt-0.5">(solo muestra el título)</p>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

            </div>

        </div>
    </div>

    <div class="flex justify-end">
        <button type="submit"
                class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 px-8 rounded-lg text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
            Guardar programa
        </button>
    </div>
</form>
@endsection

@push('scripts')
<script>
// ── Notificaciones Wallet ─────────────────────────────────────────────────────

function toggleNotificationFields(enabled) {
    const fields = document.getElementById('notification-fields');
    if (fields) {
        fields.classList.toggle('hidden', !enabled);
    }
}

(function initNotificationPreview() {
    const titleInput = document.querySelector('[name="visit_notification_title"]');
    const bodyInput  = document.querySelector('[name="visit_notification_message"]');
    const androidTitle = document.getElementById('preview-android-title');
    const androidBody  = document.getElementById('preview-android-body');
    const appleTitle   = document.getElementById('preview-apple-title');

    if (!titleInput) return;

    function updatePreview() {
        const t = titleInput.value.trim() || 'Nueva visita registrada';
        const b = bodyInput ? (bodyInput.value.trim() || 'Llevas {stamps_collected}/{total_stamps} visitas.') : '';

        if (androidTitle) androidTitle.textContent = t;
        if (androidBody)  androidBody.textContent  = b;
        if (appleTitle)   appleTitle.textContent   = t;
    }

    titleInput.addEventListener('input', updatePreview);
    if (bodyInput) bodyInput.addEventListener('input', updatePreview);
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
