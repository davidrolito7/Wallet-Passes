@extends('business.layout')

@section('title', 'Clientes')
@section('heading', 'Clientes')

@section('content')
{{-- Búsqueda --}}
<form method="GET" action="{{ route('business.customers') }}" class="mb-5 flex flex-wrap gap-2">
    <input type="text" name="search" value="{{ $search }}" placeholder="Buscar por nombre o apellido..."
           class="flex-1 min-w-[180px] px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
    <button type="submit"
            class="flex-shrink-0 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-lg text-sm font-medium transition-colors">
        Buscar
    </button>
    @if($search)
        <a href="{{ route('business.customers') }}"
           class="flex-shrink-0 px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
            Limpiar
        </a>
    @endif
    <button type="button" onclick="openMessageModal()"
            class="flex-shrink-0 ml-auto inline-flex items-center gap-2 bg-gray-900 hover:bg-gray-800 text-white px-4 py-2.5 rounded-lg text-sm font-medium transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-6l-4 4v-4z"/>
        </svg>
        Enviar mensaje
    </button>
</form>

@if($errors->any())
    <div class="mb-5 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
        <ul class="list-disc list-inside space-y-0.5">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('business.customers.message') }}" id="message-form">
    @csrf
    <input type="hidden" name="target" id="message-target" value="{{ old('target', 'all') }}">

    @if($cards->isEmpty())
        <div class="bg-white rounded-xl border border-gray-200 px-6 py-16 text-center text-gray-400 text-sm">
            @if($search)
                No se encontraron clientes con "{{ $search }}".
            @else
                Aún no hay clientes registrados. Comparte tu código QR para empezar.
            @endif
        </div>
    @else
        {{-- Desktop table --}}
        <div class="hidden sm:block bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="w-10 px-5 py-3">
                                <input type="checkbox" id="select-all-checkbox" onchange="toggleAllCheckboxes(this)"
                                       class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            </th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Nombre</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Nacimiento</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Programa</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Sellos</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Estado</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide hidden lg:table-cell">Último sello</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide hidden lg:table-cell">Registro</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($cards as $card)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-5 py-3.5">
                                    <input type="checkbox" name="card_ids[]" value="{{ $card->id }}"
                                           class="customer-checkbox h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                           onchange="onCheckboxChange()"
                                           {{ in_array($card->id, old('card_ids', [])) ? 'checked' : '' }}>
                                </td>
                                <td class="px-5 py-3.5 font-medium text-gray-900">{{ $card->fullName() }}</td>
                                <td class="px-5 py-3.5 text-gray-500 text-xs">
                                    {{ $card->birth_date?->format('d/m/Y') ?? '—' }}
                                </td>
                                <td class="px-5 py-3.5 text-gray-600 text-xs">{{ $card->loyaltyProgram->name }}</td>
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-2">
                                        <div class="w-16 bg-gray-200 rounded-full h-1.5">
                                            <div class="bg-indigo-500 h-1.5 rounded-full"
                                                 style="width: {{ $card->loyaltyProgram->total_stamps > 0 ? min(100, round($card->stamps_collected / $card->loyaltyProgram->total_stamps * 100)) : 0 }}%">
                                            </div>
                                        </div>
                                        <span class="text-xs text-gray-500 whitespace-nowrap">
                                            {{ $card->stamps_collected }}/{{ $card->loyaltyProgram->total_stamps }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-5 py-3.5">
                                    @if($card->is_completed)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Completada</span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Activa</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-gray-400 text-xs hidden lg:table-cell">
                                    {{ $card->last_stamp_at?->format('d/m/Y H:i') ?? '—' }}
                                </td>
                                <td class="px-5 py-3.5 text-gray-400 text-xs hidden lg:table-cell">
                                    {{ $card->created_at->format('d/m/Y') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($cards->hasPages())
                <div class="px-5 py-4 border-t border-gray-100">
                    {{ $cards->links() }}
                </div>
            @endif
        </div>

        {{-- Mobile cards --}}
        <div class="sm:hidden space-y-3">
            @foreach($cards as $card)
                <div class="bg-white rounded-xl border border-gray-200 p-4">
                    <div class="flex items-start justify-between gap-2 mb-3">
                        <div class="flex items-start gap-2 min-w-0">
                            <input type="checkbox" name="card_ids[]" value="{{ $card->id }}"
                                   class="customer-checkbox mt-0.5 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                   onchange="onCheckboxChange()"
                                   {{ in_array($card->id, old('card_ids', [])) ? 'checked' : '' }}>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900 truncate">{{ $card->fullName() }}</p>
                                <p class="text-xs text-gray-400 mt-0.5">{{ $card->loyaltyProgram->name }}</p>
                            </div>
                        </div>
                        @if($card->is_completed)
                            <span class="flex-shrink-0 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Completa</span>
                        @else
                            <span class="flex-shrink-0 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">Activa</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-2 mb-2">
                        <div class="flex-1 bg-gray-200 rounded-full h-2">
                            <div class="bg-indigo-500 h-2 rounded-full"
                                 style="width: {{ $card->loyaltyProgram->total_stamps > 0 ? min(100, round($card->stamps_collected / $card->loyaltyProgram->total_stamps * 100)) : 0 }}%">
                            </div>
                        </div>
                        <span class="text-xs text-gray-600 font-medium whitespace-nowrap">
                            {{ $card->stamps_collected }}/{{ $card->loyaltyProgram->total_stamps }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between text-xs text-gray-400">
                        <span>Nac: {{ $card->birth_date?->format('d/m/Y') ?? '—' }}</span>
                        <span>{{ $card->created_at->format('d/m/Y') }}</span>
                    </div>
                </div>
            @endforeach

            @if($cards->hasPages())
                <div class="pt-2">{{ $cards->links() }}</div>
            @endif
        </div>
    @endif

    {{-- ── Modal: enviar mensaje ──────────────────────────────────────────── --}}
    <div id="message-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-base font-semibold text-gray-900">Enviar mensaje a tus clientes</h2>
                <button type="button" onclick="closeMessageModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="space-y-2 mb-4">
                <label class="flex items-start gap-2.5 p-3 rounded-lg border border-gray-200 cursor-pointer has-[:checked]:border-indigo-400 has-[:checked]:bg-indigo-50">
                    <input type="radio" name="target-choice" value="all" onchange="setTarget('all')"
                           {{ old('target', 'all') === 'all' ? 'checked' : '' }}
                           class="mt-0.5 h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500">
                    <span class="text-sm">
                        <span class="block font-medium text-gray-800">Todos mis clientes</span>
                        <span class="block text-xs text-gray-500 mt-0.5">Se envía a todas tus tarjetas con Wallet activo, sin importar la página o el filtro de búsqueda actual.</span>
                    </span>
                </label>
                <label class="flex items-start gap-2.5 p-3 rounded-lg border border-gray-200 cursor-pointer has-[:checked]:border-indigo-400 has-[:checked]:bg-indigo-50">
                    <input type="radio" name="target-choice" value="selected" onchange="setTarget('selected')"
                           {{ old('target') === 'selected' ? 'checked' : '' }}
                           class="mt-0.5 h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500">
                    <span class="text-sm">
                        <span class="block font-medium text-gray-800">Solo los que selecciones en la tabla</span>
                        <span class="block text-xs text-gray-500 mt-0.5" id="selected-count-label">0 clientes seleccionados en esta página.</span>
                    </span>
                </label>
            </div>

            <label class="block text-sm font-medium text-gray-700 mb-1">Mensaje</label>
            <textarea name="message" id="message-textarea" rows="3" maxlength="150" required
                      oninput="document.getElementById('message-count').textContent = this.value.length"
                      placeholder="Ej: ¡Hoy tenemos 2×1 en todas las bebidas!"
                      class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none">{{ old('message') }}</textarea>
            <p class="mt-1 text-xs text-gray-400 text-right">
                <span id="message-count">{{ strlen(old('message', '')) }}</span>/150 · Se envía como notificación push en Google Wallet (Android) y Apple Wallet (iPhone). Mientras más corto, mejor se lee en la notificación.
            </p>

            <div class="flex justify-end gap-2 mt-5">
                <button type="button" onclick="closeMessageModal()"
                        class="px-4 py-2.5 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-100 transition-colors">
                    Cancelar
                </button>
                <button type="submit"
                        class="bg-gray-900 hover:bg-gray-800 text-white font-semibold px-5 py-2.5 rounded-lg text-sm transition-colors">
                    Enviar mensaje
                </button>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
function openMessageModal() {
    document.getElementById('message-modal').classList.remove('hidden');
    document.getElementById('message-modal').classList.add('flex');
}
function closeMessageModal() {
    document.getElementById('message-modal').classList.add('hidden');
    document.getElementById('message-modal').classList.remove('flex');
}

function setTarget(value) {
    document.getElementById('message-target').value = value;
}

function toggleAllCheckboxes(source) {
    document.querySelectorAll('.customer-checkbox').forEach(cb => cb.checked = source.checked);
    onCheckboxChange();
}

function onCheckboxChange() {
    const checked = document.querySelectorAll('.customer-checkbox:checked').length;
    const label = document.getElementById('selected-count-label');
    if (label) {
        label.textContent = checked + (checked === 1 ? ' cliente seleccionado en esta página.' : ' clientes seleccionados en esta página.');
    }

    const selectAll = document.getElementById('select-all-checkbox');
    const total = document.querySelectorAll('.customer-checkbox').length;
    if (selectAll) selectAll.checked = total > 0 && checked === total;

    // Elegir un cliente específico cambia automáticamente el destino a "seleccionados".
    if (checked > 0) {
        document.querySelectorAll('input[name="target-choice"]').forEach(r => r.checked = r.value === 'selected');
        setTarget('selected');
    }
}

@if($errors->any())
    document.addEventListener('DOMContentLoaded', function () {
        onCheckboxChange();
        openMessageModal();
    });
@endif
</script>
@endpush
