@extends('business.layout')

@section('title', 'Inicio')
@section('heading', 'Bienvenido, ' . auth()->guard('business')->user()->name)

@section('content')
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <p class="text-sm text-gray-500 font-medium">Programas Activos</p>
        <p class="text-3xl font-bold text-gray-900 mt-1">{{ $business->active_programs_count }}</p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <p class="text-sm text-gray-500 font-medium">Total Clientes</p>
        <p class="text-3xl font-bold text-gray-900 mt-1">{{ $totalCards }}</p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <p class="text-sm text-gray-500 font-medium">Tarjetas Completadas</p>
        <p class="text-3xl font-bold text-indigo-600 mt-1">{{ $completedCards }}</p>
    </div>
</div>

{{-- Accesos rápidos --}}
<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
    <a href="{{ route('business.scanner') }}"
       class="bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl p-4 flex flex-col items-center gap-2 transition-colors text-center">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
        </svg>
        <span class="text-xs font-semibold">Escanear</span>
    </a>
    <a href="{{ route('business.qr') }}"
       class="bg-white hover:bg-gray-50 border border-gray-200 text-gray-700 rounded-xl p-4 flex flex-col items-center gap-2 transition-colors text-center">
        <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
        </svg>
        <span class="text-xs font-semibold">Código QR</span>
    </a>
    <a href="{{ route('business.customers') }}"
       class="bg-white hover:bg-gray-50 border border-gray-200 text-gray-700 rounded-xl p-4 flex flex-col items-center gap-2 transition-colors text-center">
        <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
        </svg>
        <span class="text-xs font-semibold">Clientes</span>
    </a>
    <a href="{{ route('business.loyalty-program') }}"
       class="bg-white hover:bg-gray-50 border border-gray-200 text-gray-700 rounded-xl p-4 flex flex-col items-center gap-2 transition-colors text-center">
        <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span class="text-xs font-semibold">Programa</span>
    </a>
</div>

{{-- Últimos clientes --}}
<div class="bg-white rounded-xl border border-gray-200">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-gray-900">Últimos clientes registrados</h2>
        <a href="{{ route('business.customers') }}" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">Ver todos →</a>
    </div>

    @if($recentCards->isEmpty())
        <div class="px-5 py-12 text-center text-gray-400 text-sm">
            Aún no hay clientes. Comparte tu código QR para empezar.
        </div>
    @else
        <div class="divide-y divide-gray-100">
            @foreach($recentCards as $card)
                <div class="px-5 py-3.5 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-900 truncate">{{ $card->fullName() }}</p>
                        <p class="text-xs text-gray-400 mt-0.5 truncate">{{ $card->loyaltyProgram->name }} · {{ $card->created_at->diffForHumans() }}</p>
                    </div>
                    <span class="flex-shrink-0 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                 {{ $card->is_completed ? 'bg-green-100 text-green-800' : 'bg-indigo-100 text-indigo-800' }}">
                        {{ $card->progressText() }}
                    </span>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
if (/Mobi|Android|iPhone|iPad|iPod/i.test(navigator.userAgent)) {
    window.location.replace('{{ route("business.scanner") }}');
}
</script>
@endpush
