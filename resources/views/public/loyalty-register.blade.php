<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Únete — {{ $program->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        @keyframes pop-in {
            0% {
                transform: scale(0.5);
                opacity: 0;
            }

            70% {
                transform: scale(1.1);
            }

            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        .pop-in {
            animation: pop-in 0.45s ease-out both;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .spinner-ring {
            width: 52px;
            height: 52px;
            border: 4px solid rgba(255, 255, 255, 0.25);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.85s linear infinite;
        }

        #loading-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 9999;
            background: rgba(0, 0, 0, 0.55);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            align-items: center;
            justify-content: center;
        }

        #loading-overlay.active {
            display: flex;
        }

        /* iOS Safari dibuja el control de type="date" (texto + ícono de calendario) con su
           propio chrome nativo, que no siempre respeta el border-radius/width del input y se
           sale por la derecha. appearance:none lo desactiva y lo deja usar el box model normal. */
        input[type="date"] {
            -webkit-appearance: none;
            appearance: none;
            min-width: 0;
            /* Sin appearance nativo, iOS calcula la altura desde el contenido interno del
               control, que mide distinto vacío que con un valor puesto (por eso "saltaba" al
               tocarlo). Se fija la altura para que sea igual en ambos estados: 2×py-2.5 (20px)
               + line-height de text-sm (20px) + borde (2px) = 42px, igual que los inputs de texto. */
            height: 2.625rem;
            line-height: 1.25rem;
        }
    </style>
</head>

<body class="min-h-screen flex flex-col" style="background-color: {{ $business->primary_color ?? '#1a1a2e' }}">

    <main class="flex-1 flex items-start sm:items-center justify-center p-4 pt-6 sm:py-6">
        <div class="w-full max-w-md">

            {{-- Header del negocio --}}
            <div class="text-center mb-6">
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
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        @php
                            // El cliente arranca en el primer sistema de premios (mismo criterio
                            // que LoyaltyService::createCard()) — el "próximo premio" debe ser
                            // el primer hito de ESE sistema, no el de menor stamp_count entre
                            // todos los sistemas mezclados.
                            $firstPrizeSystem = $program->prizeSystems->first();
                            $nextRewardTitle = $firstPrizeSystem?->milestones->first()?->reward_title
                                ?? $firstPrizeSystem?->reward_title
                                ?? $program->reward_title;
                        @endphp
                        Próximo premio: <strong>{{ $nextRewardTitle }}</strong>
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
                            <input type="text" name="first_name" value="{{ old('first_name') }}" required
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
                        class="w-full bg-gray-900 hover:bg-gray-800 text-white font-semibold py-3 px-4 rounded-lg text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 disabled:opacity-60 disabled:cursor-not-allowed">
                        Obtener mi tarjeta de lealtad
                    </button>

                    <p class="text-xs text-gray-400 text-center">
                        Tu información solo se usa para identificar tu tarjeta de lealtad.
                    </p>
                </form>
            </div>
            @endif

        </div>
    </main>

    <footer class="mt-8 bg-gray-950 border-t border-white/10 py-4 text-center">
        <p class="text-xs text-white/50">
            Desarrollado por
            <a href="https://uzumakimx.com/"
                class="font-semibold text-white/80"
                target="_blank"
                rel="noopener noreferrer">
                Uzumaki
            </a>
            · © {{ date('Y') }}
        </p>
    </footer>

    @unless(session('card_added'))
    {{-- Overlay only exists while the form is active; removed from DOM on the success page --}}
    <div id="loading-overlay" role="status" aria-live="polite" aria-label="Cargando">
        <div class="flex flex-col items-center gap-5 text-white text-center px-6">
            <div class="spinner-ring"></div>
            <p class="text-base font-semibold leading-snug">
                Estamos creando tu tarjeta<br>
                <span class="text-sm font-normal opacity-80">Por favor espera…</span>
            </p>
        </div>
    </div>

    <script>
        (function() {
            var overlay = document.getElementById('loading-overlay');
            var form = document.getElementById('register-form');
            var btn = document.getElementById('submit-btn');
            var submitted = false;

            function hideSpinner() {
                if (!submitted) return;
                submitted = false;
                overlay.classList.remove('active');
                btn.disabled = false;
                form.reset();
            }

            form.addEventListener('submit', function() {
                btn.disabled = true;
                overlay.classList.add('active');
                submitted = true;
            });

            // iOS: Safari queda debajo del sheet de Apple Wallet.
            // Cuando el sheet se cierra la página recupera visibilidad → ocultamos el spinner.
            document.addEventListener('visibilitychange', function() {
                if (document.visibilityState === 'visible') hideSpinner();
            });

            // Respaldo: foco de ventana al regresar a Safari desde otra app.
            window.addEventListener('focus', hideSpinner);

            // Bfcache: botón «atrás» después de una redirección completa (ej. Google Wallet).
            window.addEventListener('pageshow', function(e) {
                if (e.persisted) hideSpinner();
            });
        })();
    </script>
    @endunless

</body>

</html>