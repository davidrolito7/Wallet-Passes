@extends('business.layout')

@section('title', 'Mi Negocio')
@section('heading', 'Mi Negocio')

@section('content')
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

        {{-- Vista previa de colores --}}
        <div class="mt-5 border border-gray-200 rounded-xl overflow-hidden">
            <div id="color-preview" class="p-4 flex items-center justify-between transition-colors"
                 style="background-color: {{ $business->primary_color ?? '#1a1a2e' }}">
                <div>
                    <p class="text-xs font-medium opacity-70 transition-colors" id="preview-label"
                       style="color: {{ $business->label_color ?? '#cccccc' }}">PROGRAMA</p>
                    <p class="text-base font-bold transition-colors" id="preview-name"
                       style="color: {{ $business->secondary_color ?? '#ffffff' }}">{{ $business->name }}</p>
                </div>
                <div class="w-10 h-10 rounded-full border-2 flex items-center justify-center"
                     style="border-color: {{ $business->secondary_color ?? '#ffffff' }}20">
                    <svg class="w-5 h-5" fill="none" stroke="{{ $business->secondary_color ?? '#ffffff' }}" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3l14 9-14 9V3z"/>
                    </svg>
                </div>
            </div>
            <p class="text-xs text-gray-400 text-center py-1.5 bg-gray-50">Vista previa de colores</p>
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
</script>
@endpush
