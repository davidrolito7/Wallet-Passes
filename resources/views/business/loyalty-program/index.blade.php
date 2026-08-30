@extends('business.layout')

@section('title', 'Programa de Lealtad')
@section('heading', 'Programa de Lealtad')

@section('content')
@php
    $birthdayEnabled = old('birthday_reward_enabled', $program?->birthday_reward_enabled ?? false);

    // Build prize systems array for rendering
    $rawSystems = old('prize_systems', null);
    if ($rawSystems === null && $program) {
        $rawSystems = $program->prizeSystems()->with('milestones')->get()->map(fn($s) => [
            'id'                  => $s->id,
            'reward_title'        => $s->reward_title,
            'reward_description'  => $s->reward_description ?? '',
            'milestones'          => $s->milestones->map(fn($m) => [
                'stamp_count'        => $m->stamp_count,
                'reward_title'       => $m->reward_title,
                'reward_description' => $m->reward_description ?? '',
            ])->toArray(),
        ])->toArray();
    }
    if (empty($rawSystems)) {
        $rawSystems = [['id' => '', 'reward_title' => '', 'reward_description' => '', 'milestones' => []]];
    }
    // Solo para mostrar "Premio Final — X visitas". El total de sellos ahora se
    // configura en Mi Negocio, junto con las imágenes para Wallet.
    $totalStamps = $program?->total_stamps ?? 10;

    // Las imágenes para Wallet (y con ellas, la versión de tarjeta 1 o 2 y el total de
    // sellos real) se configuran en Mi Negocio, pero ese formulario solo puede guardarlas si
    // el programa ya existe. Por eso el aviso solo aplica cuando el programa ya se creó pero
    // aún no tiene imágenes — a un negocio nuevo (sin programa) hay que dejarlo crear el
    // programa aquí primero; mandarlo a Mi Negocio antes lo dejaría subiendo imágenes que se
    // ignoran en silencio porque todavía no hay dónde guardarlas.
    $needsImagesWarning = $program && ! (
        filled($program->filled_stamp_image)
        || filled($program->empty_stamp_image)
        || filled($program->pass_background_image)
    );
@endphp

<div class="space-y-8">

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 rounded-xl p-4">
            <p class="text-sm font-semibold text-red-800 mb-2">Por favor corrige los siguientes errores:</p>
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $error)
                    <li class="text-sm text-red-700">{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('business.loyalty-program.save') }}" enctype="multipart/form-data" class="space-y-8">
        @csrf

        {{-- ─── 1. Información General ────────────────────────────────────── --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-5">Información General</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Nombre del programa <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="name"
                           value="{{ old('name', $program?->name) }}"
                           required
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('name') border-red-400 @enderror">
                    @error('name')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Términos y Condiciones</label>
                    <textarea name="description" rows="3"
                              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none">{{ old('description', $program?->description) }}</textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Vigencia de la tarjeta (Meses)</label>
                    <input type="number"
                           name="validity_months"
                           value="{{ old('validity_months', $program?->validity_months ?? 12) }}"
                           min="1" max="60"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('validity_months') border-red-400 @enderror">
                    <p class="mt-1 text-xs text-gray-400">Meses desde la emisión hasta que vence. Se muestra en Apple Wallet y Google Wallet.</p>
                    @error('validity_months')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center sm:pt-6">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox"
                           id="is_active"
                           name="is_active"
                           value="1"
                           {{ old('is_active', $program?->is_active ?? true) ? 'checked' : '' }}
                           class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <label for="is_active" class="ml-2 text-sm font-medium text-gray-700">Programa activo</label>
                </div>
            </div>
        </div>

        {{-- ─── 2. Sistemas de Premios ─────────────────────────────────────── --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-base font-semibold text-gray-900">Sistemas de Premios</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Define uno o varios sistemas de premios con hitos intermedios y un premio final.</p>
                </div>
            </div>

            @if($needsImagesWarning)
                <div class="mb-6 flex items-start gap-3 bg-amber-50 border border-amber-200 rounded-xl p-4">
                    <svg class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.07 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                    </svg>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-amber-800">Primero configura las imágenes y la versión de tu tarjeta</p>
                        <p class="text-xs text-amber-700 mt-0.5">
                            En "Mi Negocio" eliges la Versión 1 (imagen de fondo) o Versión 2 (sellos visuales) y el total de visitas/sellos.
                            Ese total es el que define hasta qué visita puedes poner premios intermedios aquí. Configúralo primero y luego regresa a esta página.
                        </p>
                        <a href="{{ route('business.profile') }}"
                           class="inline-flex items-center gap-1.5 mt-3 bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold px-3 py-2 rounded-lg transition-colors">
                            Ir a Mi Negocio
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                            </svg>
                        </a>
                    </div>
                </div>
            @endif

            <div id="systems-container" class="space-y-6">
                @foreach($rawSystems as $si => $system)
                    <div class="system-card rounded-xl border border-gray-200 overflow-hidden" data-system-index="{{ $si }}">
                        {{-- Card header --}}
                        <div class="bg-gradient-to-r from-indigo-600 to-indigo-500 px-5 py-3 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="system-number inline-flex items-center justify-center w-6 h-6 rounded-full bg-white text-indigo-700 text-xs font-bold">{{ $si + 1 }}</span>
                                <span class="text-sm font-semibold text-white">Sistema de premios</span>
                            </div>
                            <button type="button"
                                    onclick="removeSystem(this)"
                                    class="remove-system-btn text-indigo-200 hover:text-white transition-colors {{ count($rawSystems) <= 1 ? 'hidden' : '' }}"
                                    title="Eliminar sistema">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>

                        <div class="p-5 space-y-5">
                            <input type="hidden" name="prize_systems[{{ $si }}][id]" value="{{ $system['id'] ?? '' }}">

                            {{-- Intermediate milestones --}}
                            <div>
                                <div class="flex items-center justify-between mb-3">
                                    <h3 class="text-sm font-semibold text-gray-700">Premios intermedios (hitos)</h3>
                                    <button type="button"
                                            onclick="addMilestone(this.closest('.system-card'))"
                                            class="inline-flex items-center gap-1 text-xs font-medium text-white bg-indigo-600 hover:bg-indigo-700 px-3 py-1.5 rounded-lg transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                        </svg>
                                        Agregar premio
                                    </button>
                                </div>

                                <div class="milestones-container space-y-2" data-milestone-count="{{ count($system['milestones'] ?? []) }}">
                                    @if(!empty($system['milestones']))
                                        @foreach($system['milestones'] as $mi => $milestone)
                                            <div class="milestone-row flex flex-wrap items-end gap-2 p-3 bg-gray-50 rounded-lg border border-gray-100">
                                                <div class="w-20 flex-shrink-0">
                                                    <label class="block text-xs font-medium text-gray-500 mb-1">Visita #</label>
                                                    <input type="number"
                                                           name="prize_systems[{{ $si }}][milestones][{{ $mi }}][stamp_count]"
                                                           value="{{ $milestone['stamp_count'] ?? '' }}"
                                                           min="1" max="{{ $totalStamps - 1 }}"
                                                           class="w-full px-2 py-1.5 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                                </div>
                                                <div class="flex-1 min-w-[120px]">
                                                    <label class="block text-xs font-medium text-gray-500 mb-1">Premio</label>
                                                    <input type="text"
                                                           name="prize_systems[{{ $si }}][milestones][{{ $mi }}][reward_title]"
                                                           value="{{ $milestone['reward_title'] ?? '' }}"
                                                           placeholder="Ej: Café gratis"
                                                           class="w-full px-2 py-1.5 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                                </div>
                                                <div class="flex-1 min-w-[120px]">
                                                    <label class="block text-xs font-medium text-gray-500 mb-1">Descripción</label>
                                                    <input type="text"
                                                           name="prize_systems[{{ $si }}][milestones][{{ $mi }}][reward_description]"
                                                           value="{{ $milestone['reward_description'] ?? '' }}"
                                                           placeholder="Opcional"
                                                           class="w-full px-2 py-1.5 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                                </div>
                                                <div class="flex items-center gap-3 pb-0.5">
                                                    <button type="button"
                                                            onclick="removeMilestone(this)"
                                                            class="text-red-400 hover:text-red-600 transition-colors"
                                                            title="Eliminar hito">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>
                                        @endforeach
                                    @else
                                        <p class="milestone-empty-hint text-xs text-gray-400 py-2">Sin hitos intermedios. Agrega uno si deseas premiar antes del objetivo final.</p>
                                    @endif
                                </div>
                            </div>

                            {{-- Final prize --}}
                            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                                <div class="flex items-center gap-2 mb-3">
                                    <svg class="w-4 h-4 text-amber-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                    </svg>
                                    <h3 class="text-sm font-semibold text-amber-800">
                                        Premio Final &mdash; <span class="total-stamps-label">{{ $totalStamps }}</span> visitas
                                    </h3>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-medium text-amber-700 mb-1">
                                            Premio <span class="text-red-500">*</span>
                                        </label>
                                        <input type="text"
                                               name="prize_systems[{{ $si }}][reward_title]"
                                               value="{{ $system['reward_title'] ?? '' }}"
                                               required
                                               placeholder="Ej: Bebida completamente gratis"
                                               class="w-full px-3 py-2 border border-amber-300 bg-white rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-amber-700 mb-1">Descripción</label>
                                        <input type="text"
                                               name="prize_systems[{{ $si }}][reward_description]"
                                               value="{{ $system['reward_description'] ?? '' }}"
                                               placeholder="Opcional"
                                               class="w-full px-3 py-2 border border-amber-300 bg-white rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <button type="button"
                    onclick="addSystem()"
                    class="mt-5 w-full flex items-center justify-center gap-2 px-4 py-3 rounded-xl border-2 border-dashed border-indigo-300 text-indigo-600 hover:border-indigo-400 hover:bg-indigo-50 text-sm font-medium transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Agregar sistema de premios
            </button>
        </div>

        <!-- {{-- ─── 3. Premio de Cumpleaños ────────────────────────────────────── --}}
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
                        @error('birthday_reward_title')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
                        <textarea name="birthday_reward_description" rows="2"
                                  placeholder="Ej: Presenta esta tarjeta el día de tu cumpleaños y recibe una bebida completamente gratis."
                                  class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none">{{ old('birthday_reward_description', $program?->birthday_reward_description) }}</textarea>
                    </div>
                </div>
            </div>
        </div> -->

        {{-- ─── 4. Notificaciones al Wallet ────────────────────────────────── --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-1">Mensaje de notificación al cumplir una visita</h2>
            <p class="text-xs text-gray-500 mb-5">
                <strong>Android</strong> muestra el mensaje vía Google Wallet.
                <strong>iPhone</strong> muestra el mensaje como notificación push de Apple Wallet.
                Sin mensaje personalizado, ambas plataformas envían la notificación de sistema estándar.
            </p>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Mensaje que se le enviará al usuario cada vez que realice una visita
                </label>
                <textarea id="visit-notification-message"
                          name="visit_notification_message"
                          rows="2"
                          maxlength="300"
                          placeholder="Llevas {stamps_collected}/{total_stamps} visitas. ¡Gracias por visitarnos, {first_name}!"
                          class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none @error('visit_notification_message') border-red-400 @enderror">{{ old('visit_notification_message', $program?->visit_notification_message) }}</textarea>
        
                @error('visit_notification_message')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <input type="hidden" name="google_wallet_notification_mode" value="custom_message_only">
        </div>


        {{-- ─── Submit ─────────────────────────────────────────────────────── --}}
        <div class="flex justify-end pb-4">
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
// ── Birthday fields ───────────────────────────────────────────────────────────

function toggleBirthdayFields(enabled) {
    const fields = document.getElementById('birthday-fields');
    if (fields) fields.classList.toggle('hidden', !enabled);
}

// El total de sellos ahora se configura en Mi Negocio junto con las imágenes para
// Wallet. Aquí solo se usa como valor fijo para la etiqueta "Premio Final — X visitas".
const TOTAL_STAMPS = {{ $totalStamps }};

// ── Prize systems ─────────────────────────────────────────────────────────────

let systemCount = {{ count($rawSystems) }};

// Track milestone counts per system index
const milestoneCounts = {
    @foreach($rawSystems as $si => $system)
    {{ $si }}: {{ count($system['milestones'] ?? []) }},
    @endforeach
};

function milestoneTemplate(sysIdx, mIdx) {
    return `<div class="milestone-row flex flex-wrap items-end gap-2 p-3 bg-gray-50 rounded-lg border border-gray-100">
        <div class="w-20 flex-shrink-0">
            <label class="block text-xs font-medium text-gray-500 mb-1">Visita #</label>
            <input type="number"
                   name="prize_systems[${sysIdx}][milestones][${mIdx}][stamp_count]"
                   min="1" max="${TOTAL_STAMPS - 1}"
                   class="w-full px-2 py-1.5 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div class="flex-1 min-w-[120px]">
            <label class="block text-xs font-medium text-gray-500 mb-1">Premio</label>
            <input type="text"
                   name="prize_systems[${sysIdx}][milestones][${mIdx}][reward_title]"
                   placeholder="Ej: Café gratis"
                   class="w-full px-2 py-1.5 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div class="flex-1 min-w-[120px]">
            <label class="block text-xs font-medium text-gray-500 mb-1">Descripción</label>
            <input type="text"
                   name="prize_systems[${sysIdx}][milestones][${mIdx}][reward_description]"
                   placeholder="Opcional"
                   class="w-full px-2 py-1.5 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div class="flex items-center gap-3 pb-0.5">
            <button type="button"
                    onclick="removeMilestone(this)"
                    class="text-red-400 hover:text-red-600 transition-colors"
                    title="Eliminar hito">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
            </button>
        </div>
    </div>`;
}

function systemTemplate(idx) {
    const totalStampsVal = TOTAL_STAMPS;
    return `<div class="system-card rounded-xl border border-gray-200 overflow-hidden" data-system-index="${idx}">
        <div class="bg-gradient-to-r from-indigo-600 to-indigo-500 px-5 py-3 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="system-number inline-flex items-center justify-center w-6 h-6 rounded-full bg-white text-indigo-700 text-xs font-bold"></span>
                <span class="text-sm font-semibold text-white">Sistema de premios</span>
            </div>
            <button type="button"
                    onclick="removeSystem(this)"
                    class="remove-system-btn text-indigo-200 hover:text-white transition-colors"
                    title="Eliminar sistema">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="p-5 space-y-5">
            <input type="hidden" name="prize_systems[${idx}][id]" value="">
            <div>
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold text-gray-700">Premios intermedios (hitos)</h3>
                    <button type="button"
                            onclick="addMilestone(this.closest('.system-card'))"
                            class="inline-flex items-center gap-1 text-xs font-medium text-white bg-indigo-600 hover:bg-indigo-700 px-3 py-1.5 rounded-lg transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Agregar premio
                    </button>
                </div>
                <div class="milestones-container space-y-2" data-milestone-count="0">
                    <p class="milestone-empty-hint text-xs text-gray-400 py-2">Sin hitos intermedios. Agrega uno si deseas premiar antes del objetivo final.</p>
                </div>
            </div>
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                <div class="flex items-center gap-2 mb-3">
                    <svg class="w-4 h-4 text-amber-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                    </svg>
                    <h3 class="text-sm font-semibold text-amber-800">
                        Premio Final &mdash; <span class="total-stamps-label">${totalStampsVal}</span> visitas
                    </h3>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-amber-700 mb-1">
                            Premio <span class="text-red-500">*</span>
                        </label>
                        <input type="text"
                               name="prize_systems[${idx}][reward_title]"
                               required
                               placeholder="Ej: Bebida completamente gratis"
                               class="w-full px-3 py-2 border border-amber-300 bg-white rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-amber-700 mb-1">Descripción</label>
                        <input type="text"
                               name="prize_systems[${idx}][reward_description]"
                               placeholder="Opcional"
                               class="w-full px-3 py-2 border border-amber-300 bg-white rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                    </div>
                </div>
            </div>
        </div>
    </div>`;
}

function updateSystemNumbers() {
    document.querySelectorAll('#systems-container .system-card').forEach((card, idx) => {
        const badge = card.querySelector('.system-number');
        if (badge) badge.textContent = idx + 1;

        const removeBtns = document.querySelectorAll('#systems-container .system-card');
        card.querySelectorAll('.remove-system-btn').forEach(btn => {
            btn.classList.toggle('hidden', removeBtns.length <= 1);
        });
    });
}

function addSystem() {
    const container = document.getElementById('systems-container');
    const idx = systemCount;

    milestoneCounts[idx] = 0;

    const wrapper = document.createElement('div');
    wrapper.innerHTML = systemTemplate(idx);
    container.appendChild(wrapper.firstElementChild);

    systemCount++;
    updateSystemNumbers();
}

function removeSystem(btn) {
    const cards = document.querySelectorAll('#systems-container .system-card');
    if (cards.length <= 1) return;

    btn.closest('.system-card').remove();
    updateSystemNumbers();
}

function addMilestone(systemEl) {
    const container = systemEl.querySelector('.milestones-container');
    const sysIdx = parseInt(systemEl.dataset.systemIndex ?? '0', 10);

    const hint = container.querySelector('.milestone-empty-hint');
    if (hint) hint.remove();

    const currentCount = parseInt(container.dataset.milestoneCount ?? '0', 10);
    const mIdx = milestoneCounts[sysIdx] !== undefined ? milestoneCounts[sysIdx] : currentCount;

    const wrapper = document.createElement('div');
    wrapper.innerHTML = milestoneTemplate(sysIdx, mIdx);
    container.appendChild(wrapper.firstElementChild);

    if (milestoneCounts[sysIdx] !== undefined) {
        milestoneCounts[sysIdx]++;
    }
    container.dataset.milestoneCount = String(parseInt(container.dataset.milestoneCount ?? '0', 10) + 1);
}

function removeMilestone(btn) {
    const row = btn.closest('.milestone-row');
    if (!row) return;
    const container = row.closest('.milestones-container');
    row.remove();

    const remaining = container.querySelectorAll('.milestone-row').length;
    container.dataset.milestoneCount = String(remaining);

    if (remaining === 0) {
        const hint = document.createElement('p');
        hint.className = 'milestone-empty-hint text-xs text-gray-400 py-2';
        hint.textContent = 'Sin hitos intermedios. Agrega uno si deseas premiar antes del objetivo final.';
        container.appendChild(hint);
    }
}

// Initialize system numbers on page load
updateSystemNumbers();
</script>
@endpush
