<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Portal de Negocios') — {{ auth()->guard('business')->user()?->name ?? 'Portal' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
    @stack('head')
</head>
<body class="bg-gray-50 text-gray-900 min-h-screen">

{{-- Sidebar --}}
<div class="flex min-h-screen">
    <aside class="w-64 bg-gray-900 text-white flex flex-col fixed inset-y-0 left-0 z-30">
        <div class="p-6 border-b border-gray-700">
            @php $business = auth()->guard('business')->user(); @endphp
            @if($business?->logoPublicUrl())
                <img src="{{ $business->logoPublicUrl() }}" alt="{{ $business->name }}" class="h-10 object-contain mb-2">
            @endif
            <p class="text-sm font-semibold text-white truncate">{{ $business?->name }}</p>
            <p class="text-xs text-gray-400">Portal de Negocios</p>
        </div>

        <nav class="flex-1 p-4 space-y-1">
            <a href="{{ route('business.dashboard') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors
                      {{ request()->routeIs('business.dashboard') ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:bg-gray-800' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                Inicio
            </a>

            <a href="{{ route('business.loyalty-program') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors
                      {{ request()->routeIs('business.loyalty-program*') ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:bg-gray-800' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Programa de Lealtad
            </a>

            <a href="{{ route('business.customers') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors
                      {{ request()->routeIs('business.customers*') ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:bg-gray-800' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                Clientes
            </a>

            <a href="{{ route('business.qr') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors
                      {{ request()->routeIs('business.qr*') ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:bg-gray-800' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                </svg>
                Código QR
            </a>
        </nav>

        <div class="p-4 border-t border-gray-700">
            <form method="POST" action="{{ route('business.logout') }}">
                @csrf
                <button type="submit"
                        class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-gray-300 hover:bg-gray-800 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    Cerrar sesión
                </button>
            </form>
        </div>
    </aside>

    {{-- Main content --}}
    <main class="flex-1 ml-64">
        <header class="bg-white border-b border-gray-200 px-8 py-4">
            <h1 class="text-xl font-semibold text-gray-900">@yield('heading', 'Dashboard')</h1>
        </header>

        <div class="p-8">
            @if(session('success'))
                <div class="mb-6 bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="mb-6 bg-red-50 border border-red-200 text-red-800 rounded-lg px-4 py-3 text-sm">
                    {{ session('error') }}
                </div>
            @endif

            @yield('content')
        </div>
    </main>
</div>

@stack('scripts')
</body>
</html>
