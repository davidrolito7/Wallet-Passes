<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Únete — {{ $program->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        @keyframes pop-in {
            0%   { transform: scale(0.5); opacity: 0; }
            70%  { transform: scale(1.1); }
            100% { transform: scale(1);   opacity: 1; }
        }
        .pop-in { animation: pop-in 0.45s ease-out both; }
    </style>
</head>
<body class="min-h-screen flex flex-col" style="background-color: {{ $business->primary_color ?? '#1a1a2e' }}">

<main class="flex-1 flex items-center justify-center p-4 py-10">
    <div class="w-full max-w-md">

        {{-- Header del negocio --}}
        <div class="text-center mb-8">
            @if($business->logoPublicUrl())
                <img src="{{ $business->logoPublicUrl() }}" alt="{{ $business->name }}" class="h-16 mx-auto object-contain mb-4">
            @endif
            <h1 class="text-2xl font-bold text-white">{{ $business->name }}</h1>
            <p class="text-sm mt-1" style="color: {{ $business->label_color ?? '#cccccc' }}">
                Únete a nuestro programa de lealtad
            </p>
        </div>

        @if(session('card_added'))
            {{-- ── Estado de éxito ── --}}
            <div class="bg-white rounded-2xl shadow-xl overflow-hidden text-center px-8 py-10">
                <div class="pop-in flex items-center justify-center w-20 h-20 rounded-full mx-auto mb-5"
                     style="background-color: {{ $business->primary_color ?? '#4f46e5' }}1a">
                    <svg class="w-10 h-10" fill="none" stroke="{{ $business->primary_color ?? '#4f46e5' }}" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>

                <h2 class="text-xl font-bold text-gray-900">¡Tarjeta agregada exitosamente!</h2>
                <p class="text-gray-500 text-sm mt-2 leading-relaxed">
                    Visítanos pronto y empieza a acumular visitas.<br>
                    Recuerda presentar tu tarjeta en cada visita.
                </p>
            </div>

        @else
            {{-- ── Formulario de registro ── --}}
            <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
                <div class="px-6 py-5 border-b border-gray-100">
                    <h2 class="text-lg font-semibold text-gray-900">{{ $program->name }}</h2>

                    <p class="text-sm text-gray-500 mt-1">
                        Acumula visitas y gana descuentos o productos gratis
                    </p>

                    <div class="mt-3 flex items-center gap-2 text-sm text-indigo-700 bg-indigo-50 rounded-lg px-3 py-2">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Próximo premio: <strong>{{ $program->milestones->first()?->reward_title ?? $program->reward_title }}</strong>
                    </div>
                </div>

                <form id="register-form"
                      method="POST"
                      action="{{ route('public.loyalty.register.submit', ['slug' => $business->slug, 'program' => $program->id]) }}"
                      class="p-6 space-y-4">
                    @csrf

                    @if($errors->any())
                        <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
                            <ul class="list-disc list-inside space-y-0.5">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
                            <input type="text" name="first_name" value="{{ old('first_name') }}" required autofocus
                                   class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500
                                          @error('first_name') border-red-400 @enderror">
                            @error('first_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Apellido *</label>
                            <input type="text" name="last_name" value="{{ old('last_name') }}" required
                                   class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500
                                          @error('last_name') border-red-400 @enderror">
                            @error('last_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Fecha de nacimiento *</label>
                        <input type="date" name="birth_date" value="{{ old('birth_date') }}" required
                               max="{{ now()->subDay()->format('Y-m-d') }}"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500
                                      @error('birth_date') border-red-400 @enderror">
                        @error('birth_date') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <button id="submit-btn"
                            type="submit"
                            class="w-full text-white font-semibold py-3 px-4 rounded-lg text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-60 disabled:cursor-not-allowed"
                            style="background-color: {{ $business->primary_color ?? '#4f46e5' }}">
                        <span id="btn-text">Obtener mi tarjeta de lealtad</span>
                        <span id="btn-loading" class="hidden">Procesando…</span>
                    </button>

                    <p class="text-xs text-gray-400 text-center">
                        Tu información solo se usa para identificar tu tarjeta de lealtad.
                    </p>
                </form>
            </div>
        @endif

    </div>
</main>

<footer class="py-6 text-center">
    <p class="text-xs text-white/30">
        Desarrollado por <span class="font-semibold text-white/50">Fidelight</span> · © {{ date('Y') }}
    </p>
</footer>

@unless(session('card_added'))
<script>
document.getElementById('register-form').addEventListener('submit', function () {
    const btn     = document.getElementById('submit-btn');
    const txtNorm = document.getElementById('btn-text');
    const txtLoad = document.getElementById('btn-loading');

    btn.disabled = true;
    txtNorm.classList.add('hidden');
    txtLoad.classList.remove('hidden');
});
</script>
@endunless

</body>
</html>
