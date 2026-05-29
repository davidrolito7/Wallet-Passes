@extends('business.layout')

@section('title', 'Clientes')
@section('heading', 'Clientes')

@section('content')
{{-- Búsqueda --}}
<form method="GET" action="{{ route('business.customers') }}" class="mb-6 flex gap-3">
    <input type="text" name="search" value="{{ $search }}" placeholder="Buscar por nombre o apellido..."
           class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
    <button type="submit"
            class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-lg text-sm font-medium transition-colors">
        Buscar
    </button>
    @if($search)
        <a href="{{ route('business.customers') }}"
           class="px-5 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
            Limpiar
        </a>
    @endif
</form>

<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    @if($cards->isEmpty())
        <div class="px-6 py-16 text-center text-gray-400 text-sm">
            @if($search)
                No se encontraron clientes con "{{ $search }}".
            @else
                Aún no hay clientes registrados. Comparte tu código QR para empezar.
            @endif
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Nombre</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Nacimiento</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Programa</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Sellos</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Estado</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Último sello</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Registro</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($cards as $card)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 font-medium text-gray-900">{{ $card->fullName() }}</td>
                            <td class="px-6 py-4 text-gray-500">
                                {{ $card->birth_date?->format('d/m/Y') ?? '—' }}
                            </td>
                            <td class="px-6 py-4 text-gray-600">{{ $card->loyaltyProgram->name }}</td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 bg-gray-200 rounded-full h-1.5 w-20">
                                        <div class="bg-indigo-500 h-1.5 rounded-full"
                                             style="width: {{ $card->loyaltyProgram->total_stamps > 0 ? min(100, round($card->stamps_collected / $card->loyaltyProgram->total_stamps * 100)) : 0 }}%">
                                        </div>
                                    </div>
                                    <span class="text-xs text-gray-500 whitespace-nowrap">
                                        {{ $card->stamps_collected }}/{{ $card->loyaltyProgram->total_stamps }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                @if($card->is_completed)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        Completada
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                                        Activa
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-gray-500 text-xs">
                                {{ $card->last_stamp_at?->format('d/m/Y H:i') ?? '—' }}
                            </td>
                            <td class="px-6 py-4 text-gray-500 text-xs">
                                {{ $card->created_at->format('d/m/Y') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($cards->hasPages())
            <div class="px-6 py-4 border-t border-gray-100">
                {{ $cards->links() }}
            </div>
        @endif
    @endif
</div>
@endsection
