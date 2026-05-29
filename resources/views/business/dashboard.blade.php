@extends('business.layout')

@section('title', 'Dashboard')
@section('heading', 'Bienvenido, ' . auth()->guard('business')->user()->name)

@section('content')
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <p class="text-sm text-gray-500 font-medium">Programas Activos</p>
        <p class="text-3xl font-bold text-gray-900 mt-1">{{ $business->active_programs_count }}</p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <p class="text-sm text-gray-500 font-medium">Total Clientes</p>
        <p class="text-3xl font-bold text-gray-900 mt-1">{{ $totalCards }}</p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <p class="text-sm text-gray-500 font-medium">Tarjetas Completadas</p>
        <p class="text-3xl font-bold text-indigo-600 mt-1">{{ $completedCards }}</p>
    </div>
</div>

<div class="bg-white rounded-xl border border-gray-200">
    <div class="px-6 py-4 border-b border-gray-100">
        <h2 class="text-base font-semibold text-gray-900">Últimos clientes registrados</h2>
    </div>

    @if($recentCards->isEmpty())
        <div class="px-6 py-12 text-center text-gray-400 text-sm">
            Aún no hay clientes registrados. Comparte tu código QR para empezar.
        </div>
    @else
        <div class="divide-y divide-gray-100">
            @foreach($recentCards as $card)
                <div class="px-6 py-4 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-900">{{ $card->fullName() }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">{{ $card->loyaltyProgram->name }} · {{ $card->created_at->diffForHumans() }}</p>
                    </div>
                    <div class="text-right">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                     {{ $card->is_completed ? 'bg-green-100 text-green-800' : 'bg-indigo-100 text-indigo-800' }}">
                            {{ $card->progressText() }}
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="px-6 py-3 border-t border-gray-100">
            <a href="{{ route('business.customers') }}" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                Ver todos los clientes →
            </a>
        </div>
    @endif
</div>
@endsection
