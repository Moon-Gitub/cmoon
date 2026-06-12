<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'usuario' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [], [
            'usuario' => 'usuario',
            'password' => 'contraseña',
        ]);

        $throttleKey = Str::lower($request->input('usuario')).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $segundos = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'usuario' => "Demasiados intentos. Probá de nuevo en {$segundos} segundos.",
            ]);
        }

        // Permite ingresar con nombre de usuario o con email
        $campo = filter_var($request->input('usuario'), FILTER_VALIDATE_EMAIL) ? 'email' : 'usuario';

        $credenciales = [
            $campo => $request->input('usuario'),
            'password' => $request->input('password'),
        ];

        if (! Auth::attempt($credenciales, $request->boolean('recordarme'))) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'usuario' => 'Usuario o contraseña incorrectos.',
            ]);
        }

        if (! $request->user()->activo) {
            Auth::logout();

            throw ValidationException::withMessages([
                'usuario' => 'El usuario está desactivado. Contactá al administrador.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        $request->session()->regenerate();

        $request->user()->forceFill(['ultimo_acceso_at' => now()])->save();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
