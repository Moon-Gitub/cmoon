<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verificación · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen items-center justify-center bg-slate-900 p-4 antialiased">
    <div class="w-full max-w-sm">
        <div class="mb-6 text-center">
            <h1 class="text-2xl font-bold text-white">Segundo factor</h1>
            <p class="text-sm text-slate-400">Ingresá el código de 6 dígitos o un código de recuperación</p>
        </div>

        <form method="POST" action="{{ route('login.2fa.attempt') }}"
              class="space-y-4 rounded-2xl bg-white p-6 shadow-2xl">
            @csrf
            <div>
                <label for="codigo" class="mb-1 block text-sm font-medium text-slate-700">Código</label>
                <input id="codigo" name="codigo" type="text" inputmode="numeric" autocomplete="one-time-code"
                       required autofocus
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm tracking-widest focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
            </div>
            @error('codigo')
                <p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</p>
            @enderror
            <button type="submit"
                    class="w-full rounded-lg bg-indigo-600 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">
                Verificar
            </button>
        </form>
        <p class="mt-4 text-center text-xs text-slate-500">
            <a href="{{ route('login') }}" class="underline">Volver al ingreso</a>
        </p>
    </div>
</body>
</html>
