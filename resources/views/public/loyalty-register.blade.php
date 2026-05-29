<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Únete — {{ $program->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
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

        {{-- Card del programa --}}
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
            <div class="px-6 py-5 border-b border-gray-100">
                <h2 class="text-lg font-semibold text-gray-900">{{ $program->name }}</h2>
                @if($program->description)
                    <p class="text-sm text-gray-500 mt-1">{{ $program->description }}</p>
                @endif
                <div class="mt-3 flex items-center gap-2 text-sm text-indigo-700 bg-indigo-50 rounded-lg px-3 py-2">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Acumula {{ $program->total_stamps }} visitas y gana: <strong>{{ $program->reward_title }}</strong>
                </div>
            </div>

            <form method="POST" action="{{ route('public.loyalty.register.submit', ['slug' => $business->slug, 'program' => $program->id]) }}"
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

                <button type="submit"
                        class="w-full text-white font-semibold py-3 px-4 rounded-lg text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2"
                        style="background-color: {{ $business->primary_color ?? '#4f46e5' }}">
                    Obtener mi tarjeta de lealtad
                </button>

                <p class="text-xs text-gray-400 text-center">
                    Tu información solo se usa para identificar tu tarjeta de lealtad.
                </p>
            </form>
        </div>
    </div>
</main>

</body>
</html>
