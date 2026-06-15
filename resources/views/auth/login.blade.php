<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ingresar · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen items-center justify-center bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 p-4 antialiased">

    <div class="w-full max-w-sm">
        <div class="mb-6 text-center">
            <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-500 text-2xl font-bold text-white shadow-lg shadow-indigo-500/30">C</div>
            <h1 class="text-2xl font-bold text-white">POSMoon</h1>
            <p class="text-sm text-slate-400">Ingresá con tu usuario para continuar</p>
        </div>

        <form method="POST" action="{{ route('login.attempt') }}"
              class="space-y-4 rounded-2xl bg-white p-6 shadow-2xl">
            @csrf

            <div>
                <label for="usuario" class="mb-1 block text-sm font-medium text-slate-700">Usuario o email</label>
                <input id="usuario" name="usuario" type="text" value="{{ old('usuario') }}" required autofocus
                       autocomplete="username"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
            </div>

            <div>
                <label for="password" class="mb-1 block text-sm font-medium text-slate-700">Contraseña</label>
                <input id="password" name="password" type="password" required
                       autocomplete="current-password"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
            </div>

            @error('usuario')
                <p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</p>
            @enderror

            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="recordarme" value="1"
                       class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                Mantener sesión iniciada
            </label>

            <button type="submit"
                    class="w-full rounded-lg bg-indigo-600 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-300">
                Ingresar
            </button>
        </form>

        <p class="mt-4 text-center text-xs text-slate-500">POS Moon · sistema de gestión de ventas</p>
    </div>
</body>
</html>
