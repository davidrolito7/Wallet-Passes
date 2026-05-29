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
let milestoneIndex = {{ count($milestones ?? []) }};

document.getElementById('add-milestone').addEventListener('click', function () {
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
