<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Portal de Negocios') — {{ auth()->guard('business')->user()?->name ?? 'Portal' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        #sidebar { transition: transform 0.28s cubic-bezier(.4,0,.2,1); }
        #sidebar-overlay { transition: opacity 0.28s ease; }
    </style>
    @stack('head')
</head>
<body class="bg-gray-50 text-gray-900 min-h-screen">

@php
    $authBusiness = auth()->guard('business')->user();
    $brandColor       = '#0774c3';
    $brandColorActive = '#054686';
@endphp

{{-- ── Mobile overlay ───────────────────────────────────────────────────── --}}
<div id="sidebar-overlay"
     class="fixed inset-0 z-20 bg-black/50 hidden lg:hidden"
     onclick="closeSidebar()"></div>

<div class="flex min-h-screen">

    {{-- ── Sidebar ──────────────────────────────────────────────────────── --}}
    <aside id="sidebar"
           class="fixed inset-y-0 left-0 z-30 w-64 text-white flex flex-col
                  -translate-x-full lg:translate-x-0"
           style="background-color: {{ $brandColor }};">

        {{-- Brand / close --}}
        <div class="px-5 py-4 border-b border-white/15 flex items-start gap-2">
            <div class="flex-1 min-w-0">
                @if($authBusiness?->logoPublicUrl())
                    <img src="{{ $authBusiness->logoPublicUrl() }}" alt="{{ $authBusiness->name }}"
                         class="h-9 object-contain mb-2 max-w-full">
                @endif
                <p class="text-sm font-semibold text-white truncate">{{ $authBusiness?->name }}</p>
                <p class="text-xs text-gray-400">Portal de Negocios</p>
            </div>
            <button onclick="closeSidebar()"
                    class="lg:hidden flex-shrink-0 mt-1 text-gray-400 hover:text-white p-1 rounded-md hover:bg-white/10">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Nav --}}
        <nav class="flex-1 p-3 space-y-0.5 overflow-y-auto">

            <a href="{{ route('business.dashboard') }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                      {{ request()->routeIs('business.dashboard') ? 'text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}"
               style="{{ request()->routeIs('business.dashboard') ? 'background-color: '.$brandColorActive.';' : '' }}"
               onclick="closeSidebar()">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                Inicio
            </a>

            <a href="{{ route('business.profile') }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                      {{ request()->routeIs('business.profile*') ? 'text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}"
               style="{{ request()->routeIs('business.profile*') ? 'background-color: '.$brandColorActive.';' : '' }}"
               onclick="closeSidebar()">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
                Mi Negocio
            </a>

            <a href="{{ route('business.loyalty-program') }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                      {{ request()->routeIs('business.loyalty-program*') ? 'text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}"
               style="{{ request()->routeIs('business.loyalty-program*') ? 'background-color: '.$brandColorActive.';' : '' }}"
               onclick="closeSidebar()">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Programa de Lealtad
            </a>

            <a href="{{ route('business.customers') }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                      {{ request()->routeIs('business.customers*') ? 'text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}"
               style="{{ request()->routeIs('business.customers*') ? 'background-color: '.$brandColorActive.';' : '' }}"
               onclick="closeSidebar()">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                Clientes
            </a>

            <div class="pt-3 pb-1 px-3">
                <p class="text-xs font-semibold text-white/60 uppercase tracking-wider">Operación</p>
            </div>

            <a href="{{ route('business.scanner') }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                      {{ request()->routeIs('business.scanner*') ? 'text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}"
               style="{{ request()->routeIs('business.scanner*') ? 'background-color: '.$brandColorActive.';' : '' }}"
               onclick="closeSidebar()">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                Escanear Tarjeta
            </a>

            <a href="{{ route('business.qr') }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                      {{ request()->routeIs('business.qr*') ? 'text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}"
               style="{{ request()->routeIs('business.qr*') ? 'background-color: '.$brandColorActive.';' : '' }}"
               onclick="closeSidebar()">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                </svg>
                Código QR
            </a>
        </nav>

        {{-- Logout --}}
        <div class="p-3 border-t border-white/15">
            <form method="POST" action="{{ route('business.logout') }}">
                @csrf
                <button type="submit"
                        class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-gray-300 hover:bg-white/10 hover:text-white transition-colors">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    Cerrar sesión
                </button>
            </form>
        </div>
    </aside>

    {{-- ── Main ──────────────────────────────────────────────────────────── --}}
    <main class="flex-1 min-w-0 flex flex-col lg:ml-64">

        {{-- Sticky header --}}
        <header class="sticky top-0 z-10 bg-white border-b border-gray-200 px-4 sm:px-6 py-3 flex items-center gap-3">
            {{-- Hamburger (mobile) --}}
            <button onclick="openSidebar()"
                    class="lg:hidden flex-shrink-0 p-2 -ml-1 rounded-lg text-gray-500 hover:text-gray-900 hover:bg-gray-100 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
            <h1 class="text-lg font-semibold text-gray-900 truncate">@yield('heading', 'Dashboard')</h1>
        </header>

        {{-- Page content --}}
        <div class="flex-1 p-4 sm:p-6 lg:p-8">

            @if(session('success'))
                <div class="mb-6 bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3 text-sm flex items-center gap-2">
                    <svg class="w-4 h-4 flex-shrink-0 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="mb-6 bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3 text-sm flex items-center gap-2">
                    <svg class="w-4 h-4 flex-shrink-0 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.07 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                    </svg>
                    {{ session('error') }}
                </div>
            @endif

            @yield('content')
        </div>
    </main>
</div>

<script>
function openSidebar() {
    document.getElementById('sidebar').classList.remove('-translate-x-full');
    document.getElementById('sidebar-overlay').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function closeSidebar() {
    document.getElementById('sidebar').classList.add('-translate-x-full');
    document.getElementById('sidebar-overlay').classList.add('hidden');
    document.body.style.overflow = '';
}
// Close on Escape
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeSidebar(); });
</script>

@stack('scripts')
</body>
</html>
