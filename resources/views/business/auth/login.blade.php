<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal de Negocios — Iniciar Sesión</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="min-h-screen bg-gradient-to-br from-gray-900 to-indigo-900 flex items-center justify-center p-4">

<div class="w-full max-w-md">
    <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-white">Portal de Negocios</h1>
        <p class="text-indigo-300 mt-2 text-sm">Accede para gestionar tu programa de lealtad</p>
    </div>

    <div class="bg-white rounded-2xl shadow-xl p-8">
        <form method="POST" action="{{ route('business.login.submit') }}" class="space-y-5">
            @csrf

            <div>
                <label for="login_email" class="block text-sm font-medium text-gray-700 mb-1">
                    Correo electrónico
                </label>
                <input
                    id="login_email"
                    name="login_email"
                    type="email"
                    value="{{ old('login_email') }}"
                    required
                    autofocus
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent
                           @error('login_email') border-red-400 @enderror"
                    placeholder="tu@negocio.com"
                >
                @error('login_email')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                    Contraseña
                </label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    required
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                    placeholder="••••••••"
                >
            </div>

            <div class="flex items-center">
                <input id="remember" name="remember" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                <label for="remember" class="ml-2 text-sm text-gray-600">Recuérdame</label>
            </div>

            <button
                type="submit"
                class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 px-4 rounded-lg text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
            >
                Iniciar sesión
            </button>
        </form>
    </div>
</div>

</body>
</html>
